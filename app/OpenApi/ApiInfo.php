<?php

namespace App\OpenApi;

/**
 * @OA\Info(
 *     title="Book Service API",
 *     version="1.0.0",
 *     description="Microservice responsible for managing the book catalogue in the book rental SOA system. Owns the `books_db` MySQL database and runs on port 8002.\n\n## Authentication\n- **Public endpoints** — no auth required.\n- **Admin endpoints** — require a Bearer JWT (RS256) issued by the User Service. The token payload must include `role: admin`.\n- **Internal endpoints** — require `X-Service-Key` header matching the `SERVICE_KEY` env variable. Called by the Checkout Service.\n\n## Response Format\nAll responses follow the unified envelope:\n```json\n{\n  \"success\": true,\n  \"message\": \"...\",\n  \"data\": { ... },\n  \"errors\": null\n}\n```",
 *     @OA\Contact(name="Book Service", email="admin@example.com"),
 *     @OA\License(name="MIT", url="https://opensource.org/licenses/MIT")
 * )
 *
 * @OA\Server(url="http://localhost:8002", description="Local development server (php artisan serve --port=8002)")
 *
 * @OA\SecurityScheme(
 *     securityScheme="BearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="RS256 JWT issued by the User Service. Include as: `Authorization: Bearer <token>`. Token payload must contain `role: admin` for admin endpoints."
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="ServiceKey",
 *     type="apiKey",
 *     in="header",
 *     name="X-Service-Key",
 *     description="Shared secret for internal service-to-service calls. Must match the SERVICE_KEY environment variable."
 * )
 *
 * @OA\Tag(name="Books", description="Public book catalogue — no authentication required")
 * @OA\Tag(name="Books (Admin)", description="Admin book management — requires Bearer JWT with `role=admin`")
 * @OA\Tag(name="Internal Stock", description="Service-to-service stock management — requires `X-Service-Key` header")
 */

// ── Reusable Schemas ─────────────────────────────────────────────────────────

/**
 * Full book object (used in detail, create, update responses).
 *
 * @OA\Schema(
 *     schema="Book",
 *     title="Book",
 *     description="Full book resource",
 *     required={"id","title","author","price_per_day","stock_total","stock_available"},
 *     @OA\Property(property="id",              type="string", format="uuid",  example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="title",           type="string",                 example="To Kill a Mockingbird"),
 *     @OA\Property(property="author",          type="string",                 example="Harper Lee"),
 *     @OA\Property(property="isbn",            type="string", nullable=true,  example="978-0-06-112008-4"),
 *     @OA\Property(property="genre",           type="string", nullable=true,  example="fiction"),
 *     @OA\Property(property="description",     type="string", nullable=true,  example="A gripping tale of racial injustice..."),
 *     @OA\Property(property="cover_image_url", type="string", format="url",   nullable=true, example="https://covers.openlibrary.org/b/isbn/9780061120084-L.jpg"),
 *     @OA\Property(property="stock_total",     type="integer",                example=10),
 *     @OA\Property(property="stock_available", type="integer",                example=7),
 *     @OA\Property(property="price_per_day",   type="number", format="float", example=5000.00, description="Rental cost in IDR per day"),
 *     @OA\Property(property="published_year",  type="integer", nullable=true, example=1960),
 *     @OA\Property(property="is_available",    type="boolean",                example=true),
 *     @OA\Property(property="created_at",      type="string", format="date-time", example="2024-01-15T10:00:00.000000Z"),
 *     @OA\Property(property="updated_at",      type="string", format="date-time", example="2024-01-15T10:00:00.000000Z")
 * )
 */

/**
 * Abbreviated book item used in paginated listing.
 *
 * @OA\Schema(
 *     schema="BookListItem",
 *     title="BookListItem",
 *     description="Abbreviated book object returned in paginated lists",
 *     @OA\Property(property="id",              type="string", format="uuid",  example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="title",           type="string",                 example="1984"),
 *     @OA\Property(property="author",          type="string",                 example="George Orwell"),
 *     @OA\Property(property="genre",           type="string", nullable=true,  example="fiction"),
 *     @OA\Property(property="isbn",            type="string", nullable=true,  example="978-0-45-228285-3"),
 *     @OA\Property(property="price_per_day",   type="number", format="float", example=5500.00),
 *     @OA\Property(property="stock_available", type="integer",                example=8),
 *     @OA\Property(property="is_available",    type="boolean",                example=true),
 *     @OA\Property(property="cover_image_url", type="string", format="url",   nullable=true, example=null)
 * )
 */

/**
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     title="PaginationMeta",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="per_page",     type="integer", example=15),
 *     @OA\Property(property="total",        type="integer", example=42),
 *     @OA\Property(property="last_page",    type="integer", example=3),
 *     @OA\Property(property="from",         type="integer", nullable=true, example=1),
 *     @OA\Property(property="to",           type="integer", nullable=true, example=15)
 * )
 */

/**
 * @OA\Schema(
 *     schema="PaginationLinks",
 *     title="PaginationLinks",
 *     @OA\Property(property="first", type="string", format="url", example="http://localhost:8002/api/books?page=1"),
 *     @OA\Property(property="last",  type="string", format="url", example="http://localhost:8002/api/books?page=3"),
 *     @OA\Property(property="prev",  type="string", format="url", nullable=true, example=null),
 *     @OA\Property(property="next",  type="string", format="url", nullable=true, example="http://localhost:8002/api/books?page=2")
 * )
 */

/**
 * @OA\Schema(
 *     schema="PaginatedBooks",
 *     title="PaginatedBooks",
 *     description="Paginated list of books",
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/BookListItem")
 *     ),
 *     @OA\Property(property="meta",  ref="#/components/schemas/PaginationMeta"),
 *     @OA\Property(property="links", ref="#/components/schemas/PaginationLinks")
 * )
 */

/**
 * Wraps a successful single-item response.
 *
 * @OA\Schema(
 *     schema="BookResponse",
 *     title="BookResponse",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string",  example="Book retrieved successfully."),
 *     @OA\Property(property="data",    ref="#/components/schemas/Book"),
 *     @OA\Property(property="errors",  type="object",  nullable=true, example=null)
 * )
 */

/**
 * Wraps a successful paginated list response.
 *
 * @OA\Schema(
 *     schema="BookListResponse",
 *     title="BookListResponse",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string",  example="Books retrieved successfully."),
 *     @OA\Property(property="data",    ref="#/components/schemas/PaginatedBooks"),
 *     @OA\Property(property="errors",  type="object",  nullable=true, example=null)
 * )
 */

/**
 * Stock info returned by the internal endpoint.
 *
 * @OA\Schema(
 *     schema="StockInfo",
 *     title="StockInfo",
 *     @OA\Property(property="id",              type="string", format="uuid",  example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="title",           type="string",                 example="1984"),
 *     @OA\Property(property="stock_available", type="integer",                example=5),
 *     @OA\Property(property="price_per_day",   type="number", format="float", example=5500.00)
 * )
 */

/**
 * Request body for PATCH /api/internal/books/{id}/stock.
 *
 * @OA\Schema(
 *     schema="StockUpdateRequest",
 *     title="StockUpdateRequest",
 *     required={"action","quantity"},
 *     @OA\Property(property="action",   type="string",  enum={"reserve","release"}, example="reserve",
 *                  description="`reserve` decrements stock_available; `release` increments it"),
 *     @OA\Property(property="quantity", type="integer", minimum=1, example=1)
 * )
 */

/**
 * Result after a stock reserve/release.
 *
 * @OA\Schema(
 *     schema="StockUpdateResult",
 *     title="StockUpdateResult",
 *     @OA\Property(property="id",              type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="title",           type="string",                example="1984"),
 *     @OA\Property(property="stock_available", type="integer",               example=4)
 * )
 */

/**
 * Generic error envelope.
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     title="ErrorResponse",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string",  example="Unauthorized"),
 *     @OA\Property(property="data",    type="object",  nullable=true, example=null),
 *     @OA\Property(property="errors",  type="object",  nullable=true, example=null)
 * )
 */

/**
 * 422 Validation error envelope.
 *
 * @OA\Schema(
 *     schema="ValidationErrorResponse",
 *     title="ValidationErrorResponse",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string",  example="Validation failed."),
 *     @OA\Property(property="data",    type="object",  nullable=true, example=null),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         example={"title":{"The title field is required."},"price_per_day":{"The price per day field is required."}}
 *     )
 * )
 */

class ApiInfo {}
