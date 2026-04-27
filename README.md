# Book Service

The **Book Service** is a standalone microservice within the book rental SOA (Service-Oriented Architecture) system. It is the single source of truth for book catalogue data, owning the `books_db` PostgreSQL database. It runs on **port 8002** and exposes both public-facing REST endpoints (for browsing and searching books) and internal service-to-service endpoints (used by the Checkout Service to manage stock during the rental lifecycle).

---

## Tech Stack

| Layer              | Technology                          |
|--------------------|-------------------------------------|
| Framework          | Laravel 13                          |
| Language           | PHP 8.3                             |
| Database           | MySQL (`books_db`)                  |
| JWT Verification   | `firebase/php-jwt` ^6.0 (RS256)     |
| Query Filtering    | `spatie/laravel-query-builder` ^6.0 |
| Testing            | Pest ^4.6 + PestPlugin-Laravel      |

---

## Environment Variables

Copy `.env.example` to `.env` and fill in the values below.

| Variable                  | Description                                                                                   | Example                          |
|---------------------------|-----------------------------------------------------------------------------------------------|----------------------------------|
| `APP_PORT`                | Port the service listens on                                                                   | `8002`                           |
| `DB_CONNECTION`           | Database driver                                                                               | `mysql`                          |
| `DB_DATABASE`             | MySQL database name                                                                           | `books_db`                       |
| `DB_HOST`                 | MySQL host                                                                                    | `127.0.0.1`                      |
| `DB_PORT`                 | MySQL port                                                                                    | `3306`                           |
| `DB_USERNAME`             | MySQL username                                                                                | `root`                           |
| `DB_PASSWORD`             | MySQL password                                                                                | `secret`                         |
| `USER_SERVICE_PUBLIC_KEY` | RSA public key PEM exported from the User Service; used to verify RS256 JWTs issued by it    | `-----BEGIN PUBLIC KEY-----...`  |
| `SERVICE_KEY`             | Shared secret for internal service-to-service calls (matched against `X-Service-Key` header) | `super-secret-service-key`       |

> **Security note:** Never commit `.env` to version control. Keep `USER_SERVICE_PUBLIC_KEY` and `SERVICE_KEY` in a secrets manager (e.g. Vault, AWS Secrets Manager) in production.

---

## Installation

```bash
cd book-service
composer install
cp .env.example .env
php artisan key:generate
# Edit .env — set DB_DATABASE, DB_USERNAME, DB_PASSWORD, USER_SERVICE_PUBLIC_KEY, SERVICE_KEY
php artisan migrate
php artisan db:seed
php artisan serve --port=8002
```

### Quick Setup (all-in-one)

```bash
composer run setup
```

The `setup` script in `composer.json` will install dependencies, copy `.env`, generate the app key, and run migrations automatically.

---

## Database Seeding

Running `php artisan db:seed` populates the `books` table with **10+ sample books** across multiple genres (fiction, science, history, biography, etc.), giving you realistic data to work with immediately.

To wipe the MySQL database and re-seed from scratch:

```bash
php artisan migrate:fresh --seed
```

---

## API Endpoints

All responses follow the unified envelope format:

```json
{
  "success": true,
  "message": "Human-readable message",
  "data": { ... },
  "errors": null
}
```

| Method  | Endpoint                           | Auth            | Description                                              |
|---------|------------------------------------|-----------------|----------------------------------------------------------|
| GET     | `/api/books`                       | Public          | List all books (paginated, filterable, sortable)         |
| GET     | `/api/books/{id}`                  | Public          | Retrieve a single book by UUID                           |
| POST    | `/api/books`                       | Admin JWT       | Create a new book                                        |
| PUT     | `/api/books/{id}`                  | Admin JWT       | Update an existing book                                  |
| DELETE  | `/api/books/{id}`                  | Admin JWT       | Soft-delete a book (only if no copies are rented out)    |
| GET     | `/api/internal/books/{id}/stock`   | Service Key     | Get current stock info (used by Checkout Service)        |
| PATCH   | `/api/internal/books/{id}/stock`   | Service Key     | Reserve or release stock (used by Checkout Service)      |

### Query Parameters — GET `/api/books`

Uses [Spatie Laravel Query Builder](https://spatie.be/docs/laravel-query-builder) conventions.

| Parameter             | Type    | Description                                              | Example                        |
|-----------------------|---------|----------------------------------------------------------|--------------------------------|
| `filter[search]`      | string  | Full-text search across `title` and `author`             | `?filter[search]=tolkien`      |
| `filter[genre]`       | string  | Exact genre match                                        | `?filter[genre]=fiction`       |
| `filter[available]`   | boolean | When `true`, only books with `stock_available > 0`       | `?filter[available]=true`      |
| `sort`                | string  | Field to sort by (prefix `-` for descending)             | `?sort=price_per_day`          |
| `per_page`            | integer | Items per page (default: 15)                             | `?per_page=20`                 |

---

## Authentication

The Book Service **does not issue tokens**. It only **verifies** JWTs that were issued by the **User Service**.

- **Algorithm:** RS256
- **Verification key:** `USER_SERVICE_PUBLIC_KEY` from `.env` (loaded via `config('jwt.public_key')`)
- **Middleware:** `jwt.verify` decodes and validates the token; `admin` checks that `role === 'admin'`

> **Note:** The current User Service uses Laravel Sanctum (opaque tokens). To use RS256 JWT verification end-to-end, the User Service must be updated to issue RS256 JWTs. Alternatively, you can adapt `JwtVerifyMiddleware` to validate Sanctum tokens by calling the User Service's token-verify endpoint.

### Token payload expected by admin middleware

```json
{
  "sub": "<user-uuid>",
  "email": "user@example.com",
  "role": "admin",
  "exp": 1700000000
}
```

If the token is missing, expired, or has an invalid signature, the service returns **401 Unauthorized**. If the token is valid but the role is not `admin`, the service returns **403 Forbidden**.

---

## Internal Service Communication

The internal endpoints (`/api/internal/...`) are **not meant to be called by end-users**. They are reserved for service-to-service communication, specifically the **Checkout Service**.

### Authentication

Every request to an internal endpoint must include the `X-Service-Key` header with a value matching `SERVICE_KEY` in `.env`.

```
X-Service-Key: super-secret-service-key
```

Requests without this header, or with an incorrect value, receive **403 Forbidden**.

### PATCH `/api/internal/books/{id}/stock` — request body

```json
{
  "action": "reserve",
  "quantity": 1
}
```

| Field      | Values              | Description                                    |
|------------|---------------------|------------------------------------------------|
| `action`   | `reserve`, `release`| `reserve` decrements available stock; `release` increments it |
| `quantity` | positive integer    | Number of copies to reserve or release         |

---

## Stock Management

Each book has two stock fields:

| Field             | Meaning                                                   |
|-------------------|-----------------------------------------------------------|
| `stock_total`     | Total number of physical copies owned                     |
| `stock_available` | Copies currently available for rental                     |

The difference (`stock_total - stock_available`) represents the number of copies that are currently rented out.

### Reserve / Release flow

1. **Reserve** — called by Checkout Service when a rental is confirmed. Decrements `stock_available` by `quantity`. Returns **409 Conflict** if `stock_available < quantity`.
2. **Release** — called by Checkout Service when a rental ends (return or cancellation). Increments `stock_available` by `quantity`. Returns **409 Conflict** if the result would exceed `stock_total`.

### Race condition prevention

Both operations are wrapped in a **database transaction** with **pessimistic locking** (`SELECT ... FOR UPDATE`) to prevent two concurrent requests from over-committing the same stock.

### Delete guard

`DELETE /api/books/{id}` checks whether any copies are currently rented out (`stock_available < stock_total`). If so, it returns **409 Conflict** and refuses the deletion to maintain data integrity with active rentals.

---

## Running Tests

The test suite uses **Pest** and runs against a dedicated **MySQL test database** (`books_db_test`). Make sure MySQL is running and the database exists before running tests:

```sql
CREATE DATABASE books_db_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

The default test credentials in `phpunit.xml` are `root` with an empty password (standard Laragon defaults). Adjust `DB_USERNAME` / `DB_PASSWORD` in `phpunit.xml` if your setup differs.

```bash
# Via Artisan
php artisan test

# Via Pest binary directly
./vendor/bin/pest

# With coverage report (requires Xdebug or pcov)
./vendor/bin/pest --coverage
```

Test groups:
- **Book Listing (public)** — pagination, filtering by genre/search/availability, sorting
- **Book Detail (public)** — single book retrieval, 404 handling
- **Create Book (admin)** — JWT auth, role enforcement, validation
- **Update Book (admin)** — partial update, stock recalculation
- **Delete Book (admin)** — soft delete, rental guard (409)
- **Internal Stock (service.key)** — stock read, reserve, release, conflict handling

> `RefreshDatabase` rolls back all migrations after each test, so the `books_db_test` database is left clean between runs.

---

## Docker (Optional)

A `Dockerfile` is included at the root of this service for containerised deployments. It uses `php:8.3-fpm` with the `pdo_mysql` extension and is intended to run behind an Nginx reverse proxy.

```bash
# Build the image
docker build -t book-service .

# Run the container (adjust env vars as needed)
docker run -d \
  -p 9000:9000 \
  --env-file .env \
  --name book-service \
  book-service
```

For a full multi-service setup (PostgreSQL + Nginx + all microservices), refer to the `docker-compose.yml` at the repository root.
