<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OAT;

use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

#[OAT\OpenApi(
    info: new OAT\Info(
        title: 'Book Service API',
        version: '1.0.0',
        description: 'Microservice responsible for managing the book catalogue in the book rental SOA system. Owns the `books_db` MySQL database and runs on port 8002. ## Authentication - **Public endpoints** — no auth required. - **Admin endpoints** — require a Bearer JWT (RS256) issued by the User Service. The token payload must include `role: admin`. - **Internal endpoints** — require `X-Service-Key` header matching the `SERVICE_KEY` env variable. Called by the Checkout Service. ## Response Format All responses follow the unified envelope: ```json { "success": true, "message": "...", "data": { ... }, "errors": null } ```',
        contact: new OAT\Contact(name: 'Book Service', email: 'admin@example.com'),
        license: new OAT\License(name: 'MIT', url: 'https://opensource.org/licenses/MIT')
    ),
    servers: [
        new OAT\Server(url: 'http://localhost:8002', description: 'Local development server (php artisan serve --port=8002)'),
    ],
    tags: [
        new OAT\Tag(name: 'Books', description: 'Public book catalogue — no authentication required'),
        new OAT\Tag(name: 'Books (Admin)', description: 'Admin book management — requires Bearer JWT with `role=admin`'),
        new OAT\Tag(name: 'Internal Stock', description: 'Service-to-service stock management — requires `X-Service-Key` header'),
    ]
)]
#[OAT\SecurityScheme(
    securityScheme: 'BearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'RS256 JWT issued by the User Service. Include as: `Authorization: Bearer <token>`. Token payload must contain `role: admin` for admin endpoints.'
)]
#[OAT\SecurityScheme(
    securityScheme: 'ServiceKey',
    type: 'apiKey',
    in: 'header',
    name: 'X-Service-Key',
    description: 'Shared secret for internal service-to-service calls. Must match the SERVICE_KEY environment variable.'
)]
class BookController extends Controller
{
    // ── Helpers ──────────────────────────────────────────────────────────

    private function success(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'errors'  => null,
        ], $status);
    }

    private function error(string $message, int $status, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ], $status);
    }

    // ── Public Endpoints ─────────────────────────────────────────────────

    /**
     * GET /api/books
     * Supports: search, genre, available, sort_by, sort_dir, per_page
     *
     * @OA\Get(
     *     path="/api/books",
     *     operationId="listBooks",
     *     tags={"Books"},
     *     summary="List books (paginated)",
     *     description="Returns a paginated, filterable, sortable list of books. No authentication required.",
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         required=false,
     *         description="Search in title and author (case-insensitive)",
     *         @OA\Schema(type="string", example="tolkien")
     *     ),
     *     @OA\Parameter(
     *         name="filter[genre]",
     *         in="query",
     *         required=false,
     *         description="Exact genre match",
     *         @OA\Schema(type="string", example="fiction")
     *     ),
     *     @OA\Parameter(
     *         name="filter[available]",
     *         in="query",
     *         required=false,
     *         description="Filter by availability (`true` = has stock)",
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         required=false,
     *         description="Sort field. Prefix with `-` for descending. Allowed: `title`, `price_per_day`, `published_year`",
     *         @OA\Schema(type="string", example="price_per_day")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Items per page (default 15, max 50)",
     *         @OA\Schema(type="integer", default=15, maximum=50)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of books",
     *         @OA\JsonContent(ref="#/components/schemas/BookListResponse")
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 50);

        $query = QueryBuilder::for(Book::class)
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $search = '%' . strtolower($value) . '%';
                    $query->where(function ($q) use ($search) {
                        $q->whereRaw('LOWER(title) LIKE ?', [$search])
                          ->orWhereRaw('LOWER(author) LIKE ?', [$search]);
                    });
                }),
                AllowedFilter::exact('genre'),
                AllowedFilter::callback('available', function ($query, $value) {
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        $query->where('stock_available', '>', 0);
                    } else {
                        $query->where('stock_available', '=', 0);
                    }
                }),
            ])
            ->allowedSorts([
                AllowedSort::field('title'),
                AllowedSort::field('price_per_day'),
                AllowedSort::field('published_year'),
            ])
            ->defaultSort('title');

        // Manual sort_by / sort_dir fallback (non-Spatie query params)
        if ($request->has('sort_by') && !$request->has('sort')) {
            $sortBy  = in_array($request->sort_by, ['title', 'price_per_day', 'published_year'])
                ? $request->sort_by : 'title';
            $sortDir = in_array(strtolower($request->sort_dir ?? 'asc'), ['asc', 'desc'])
                ? strtolower($request->sort_dir) : 'asc';
            $query->reorder($sortBy, $sortDir);
        }

        $books = $query->paginate($perPage);

        $items = $books->map(fn ($book) => [
            'id'              => $book->id,
            'title'           => $book->title,
            'author'          => $book->author,
            'genre'           => $book->genre,
            'isbn'            => $book->isbn,
            'price_per_day'   => $book->price_per_day,
            'stock_available' => $book->stock_available,
            'is_available'    => $book->is_available,
            'cover_image_url' => $book->cover_image_url,
        ]);

        return $this->success('Books retrieved successfully.', [
            'items' => $items,
            'meta'  => [
                'current_page' => $books->currentPage(),
                'per_page'     => $books->perPage(),
                'total'        => $books->total(),
                'last_page'    => $books->lastPage(),
                'from'         => $books->firstItem(),
                'to'           => $books->lastItem(),
            ],
            'links' => [
                'first' => $books->url(1),
                'last'  => $books->url($books->lastPage()),
                'prev'  => $books->previousPageUrl(),
                'next'  => $books->nextPageUrl(),
            ],
        ]);
    }

    /**
     * GET /api/books/{id}
     *
     * @OA\Get(
     *     path="/api/books/{id}",
     *     operationId="getBook",
     *     tags={"Books"},
     *     summary="Get book detail",
     *     description="Returns the full detail of a single book by UUID.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Book UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Book found",
     *         @OA\JsonContent(ref="#/components/schemas/BookResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Book not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        $book = Book::find($id);

        if (!$book) {
            return $this->error('Book not found.', 404);
        }

        return $this->success('Book retrieved successfully.', $book->append('is_available'));
    }

    // ── Admin Endpoints ──────────────────────────────────────────────────

    /**
     * POST /api/books
     *
     * @OA\Post(
     *     path="/api/books",
     *     operationId="createBook",
     *     tags={"Books (Admin)"},
     *     summary="Create a book",
     *     description="Creates a new book. `stock_available` is automatically set equal to `stock_total` on creation. Requires Bearer JWT with `role=admin`.",
     *     security={{"BearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title","author","price_per_day","stock_total"},
     *             @OA\Property(property="title", type="string", example="Clean Code"),
     *             @OA\Property(property="author", type="string", example="Robert C. Martin"),
     *             @OA\Property(property="price_per_day", type="number", format="float", example=12000.00),
     *             @OA\Property(property="stock_total", type="integer", example=5),
     *             @OA\Property(property="genre", type="string", nullable=true, example="technology"),
     *             @OA\Property(property="isbn", type="string", nullable=true, example="978-0-13-235088-4"),
     *             @OA\Property(property="description", type="string", nullable=true, example="A guide to writing clean code."),
     *             @OA\Property(property="cover_image_url", type="string", format="url", nullable=true, example=null),
     *             @OA\Property(property="published_year", type="integer", nullable=true, example=2008)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Book created",
     *         @OA\JsonContent(ref="#/components/schemas/BookResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Not admin",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'           => 'required|string|max:255',
            'author'          => 'required|string|max:255',
            'price_per_day'   => 'required|numeric|min:0',
            'stock_total'     => 'required|integer|min:0',
            'genre'           => 'nullable|string|max:100',
            'isbn'            => 'nullable|string|unique:books,isbn',
            'description'     => 'nullable|string',
            'cover_image_url' => 'nullable|url',
            'published_year'  => 'nullable|integer|min:1000|max:9999',
        ]);

        $book = Book::create([
            ...$validated,
            'stock_available' => $validated['stock_total'],
        ]);

        return $this->success('Book created successfully.', $book->append('is_available'), 201);
    }

    /**
     * PUT /api/books/{id}
     *
     * @OA\Put(
     *     path="/api/books/{id}",
     *     operationId="updateBook",
     *     tags={"Books (Admin)"},
     *     summary="Update a book",
     *     description="Partially updates a book. If `stock_total` is changed, `stock_available` is recalculated as: `stock_available + (new_stock_total - old_stock_total)`, clamped to [0, new_stock_total]. Requires Bearer JWT with `role=admin`.",
     *     security={{"BearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Book UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Clean Code (Updated)"),
     *             @OA\Property(property="author", type="string", example="Robert C. Martin"),
     *             @OA\Property(property="price_per_day", type="number", format="float", example=11000.00),
     *             @OA\Property(property="stock_total", type="integer", example=8),
     *             @OA\Property(property="genre", type="string", nullable=true, example="technology"),
     *             @OA\Property(property="isbn", type="string", nullable=true, example="978-0-13-235088-4"),
     *             @OA\Property(property="description", type="string", nullable=true),
     *             @OA\Property(property="cover_image_url", type="string", format="url", nullable=true),
     *             @OA\Property(property="published_year", type="integer", nullable=true, example=2008)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Book updated",
     *         @OA\JsonContent(ref="#/components/schemas/BookResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Not admin",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Book not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     )
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $book = Book::find($id);

        if (!$book) {
            return $this->error('Book not found.', 404);
        }

        $validated = $request->validate([
            'title'           => 'sometimes|string|max:255',
            'author'          => 'sometimes|string|max:255',
            'price_per_day'   => 'sometimes|numeric|min:0',
            'stock_total'     => 'sometimes|integer|min:0',
            'genre'           => 'nullable|string|max:100',
            'isbn'            => 'nullable|string|unique:books,isbn,' . $book->id,
            'description'     => 'nullable|string',
            'cover_image_url' => 'nullable|url',
            'published_year'  => 'nullable|integer|min:1000|max:9999',
        ]);

        // Recalculate stock_available when stock_total changes
        if (isset($validated['stock_total'])) {
            $delta                        = $validated['stock_total'] - $book->stock_total;
            $newAvailable                 = $book->stock_available + $delta;
            $validated['stock_available'] = max(0, min($newAvailable, $validated['stock_total']));
        }

        $book->update($validated);

        return $this->success('Book updated successfully.', $book->fresh()->append('is_available'));
    }

    /**
     * DELETE /api/books/{id}
     *
     * @OA\Delete(
     *     path="/api/books/{id}",
     *     operationId="deleteBook",
     *     tags={"Books (Admin)"},
     *     summary="Soft-delete a book",
     *     description="Soft-deletes a book. Returns **409 Conflict** if any copies are currently rented (i.e. `stock_available < stock_total`). Requires Bearer JWT with `role=admin`.",
     *     security={{"BearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Book UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Book deleted",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Not admin",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Book not found",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Copies currently rented — cannot delete",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $book = Book::find($id);

        if (!$book) {
            return $this->error('Book not found.', 404);
        }

        // Cannot delete if copies are currently rented
        if ($book->stock_available !== $book->stock_total) {
            return $this->error(
                'Cannot delete book: some copies are currently rented.',
                409
            );
        }

        $book->delete();

        return $this->success('Book deleted successfully.');
    }
}
