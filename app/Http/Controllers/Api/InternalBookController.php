<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InternalBookController extends Controller
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

    // ── Endpoints ────────────────────────────────────────────────────────

    /**
     * GET /api/internal/books/{id}/stock
     * Returns current stock info for a book (called by Checkout Service).
     *
     * @OA\Get(
     *     path="/api/internal/books/{id}/stock",
     *     operationId="getBookStock",
     *     tags={"Internal Stock"},
     *     summary="Get book stock info",
     *     description="Returns current stock availability and price for a book. Called by the Checkout Service before processing a rental. Requires `X-Service-Key` header.",
     *     security={{"ServiceKey":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Book UUID", @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")),
     *     @OA\Parameter(name="X-Service-Key", in="header", required=true, description="Shared service key matching SERVICE_KEY env var", @OA\Schema(type="string", example="super-secret-service-key")),
     *     @OA\Response(
     *         response=200,
     *         description="Stock info retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Stock retrieved successfully."),
     *             @OA\Property(property="data", ref="#/components/schemas/StockInfo"),
     *             @OA\Property(property="errors", type="object", nullable=true, example=null)
     *         )
     *     ),
     *     @OA\Response(response=403, description="Invalid or missing service key", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *     @OA\Response(response=404, description="Book not found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function getStock(string $id): JsonResponse
    {
        $book = Book::find($id);

        if (!$book) {
            return $this->error('Book not found.', 404);
        }

        return $this->success('Stock retrieved successfully.', [
            'id'              => $book->id,
            'title'           => $book->title,
            'stock_available' => $book->stock_available,
            'price_per_day'   => $book->price_per_day,
        ]);
    }

    /**
     * PATCH /api/internal/books/{id}/stock
     * Body: { action: 'reserve'|'release', quantity: int }
     *
     * Uses DB transaction + pessimistic locking to prevent race conditions.
     *
     * @OA\Patch(
     *     path="/api/internal/books/{id}/stock",
     *     operationId="updateBookStock",
     *     tags={"Internal Stock"},
     *     summary="Reserve or release stock",
     *     description="Atomically reserves or releases book stock using a DB transaction with pessimistic locking (`SELECT FOR UPDATE`). Called by the Checkout Service during rental creation (`reserve`) and rental return (`release`).\n\n- **reserve**: decrements `stock_available` by `quantity`. Returns 409 if insufficient stock.\n- **release**: increments `stock_available` by `quantity`. Returns 409 if result exceeds `stock_total`.",
     *     security={{"ServiceKey":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Book UUID", @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="X-Service-Key", in="header", required=true, description="Shared service key", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/StockUpdateRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Stock updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Stock reserved successfully."),
     *             @OA\Property(property="data", ref="#/components/schemas/StockUpdateResult"),
     *             @OA\Property(property="errors", type="object", nullable=true, example=null)
     *         )
     *     ),
     *     @OA\Response(response=403, description="Invalid or missing service key", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *     @OA\Response(response=404, description="Book not found", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *     @OA\Response(response=409, description="Stock conflict (insufficient stock or over-release)", @OA\JsonContent(ref="#/components/schemas/ErrorResponse")),
     *     @OA\Response(response=422, description="Validation error (invalid action or quantity)", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse"))
     * )
     */
    public function updateStock(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'action'   => 'required|string|in:reserve,release',
            'quantity' => 'required|integer|min:1',
        ]);

        $action   = $validated['action'];
        $quantity = (int) $validated['quantity'];

        $result = null;
        $error  = null;

        DB::transaction(function () use ($id, $action, $quantity, &$result, &$error) {
            /** @var Book|null $book */
            $book = Book::lockForUpdate()->find($id);

            if (!$book) {
                $error = ['status' => 404, 'message' => 'Book not found.'];
                return;
            }

            if ($action === 'reserve') {
                if ($book->stock_available < $quantity) {
                    $error = [
                        'status'  => 409,
                        'message' => "Insufficient stock. Available: {$book->stock_available}, requested: {$quantity}.",
                    ];
                    return;
                }
                $book->stock_available -= $quantity;

            } else { // release
                $newAvailable = $book->stock_available + $quantity;
                if ($newAvailable > $book->stock_total) {
                    $error = [
                        'status'  => 409,
                        'message' => "Release quantity exceeds stock total. Total: {$book->stock_total}, would become: {$newAvailable}.",
                    ];
                    return;
                }
                $book->stock_available = $newAvailable;
            }

            $book->save();
            $result = $book;
        });

        if ($error) {
            return $this->error($error['message'], $error['status']);
        }

        return $this->success("Stock {$action}d successfully.", [
            'id'              => $result->id,
            'title'           => $result->title,
            'stock_available' => $result->stock_available,
        ]);
    }
}
