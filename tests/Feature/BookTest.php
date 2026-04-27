<?php

use App\Models\Book;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ══════════════════════════════════════════════════════════════════════════════
// Hardcoded Test RSA Key Pair (2048-bit, generated once — not used in production)
// Using static keys avoids openssl_pkey_new() failures on Windows/Laragon where
// OPENSSL_CONF is not set in the PHP environment.
// ══════════════════════════════════════════════════════════════════════════════

const TEST_RSA_PRIVATE_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCtMgpLym5qtrk5
KvkuKfFiTujS1dis6Ujievf3us+KdvbK69dvfX/8I1z3I1VArutfkHU3uf73AcvD
bm3fUf9yxDIbSgjVQH1qDC9eYHXJMp+9xnkdFB1oDRH+pqMrTHhXQJOpzlPDPi3Q
pv1pGMWL/T8qnHX4RrlpF9SqoTnNQRa2CdaFZl/cqz7A27OevV4tiwojBLMTVRgS
1H4pzmWytsvOGqDo0frf/rPY16uGmFu7FLFYmhPzon2aHhywttkTIbCO11O/8Y/S
aucG54rx878/0170yFoWoyO7j8NQ4ZJZIAB4Zx0aEIBicv1gWwsuNDcAK1Lahyu6
jXSJV33NAgMBAAECggEADfXLG6nQ9ZwN2PxMQnrSAJUQsjHXebSCEfNMenTd1rDZ
Gqkg39UzVDz7eNYpwIF46maH9DpzQTCtXm7Pv2DqILOMwGNay+mbUklyJf7Lb7C7
bH4LdBsTdbgb+7Ut0Mckqg/D9ztwx/uCkN9s7KQ5BBwhE8uxvJXiywRHOYuuT4tu
D0M1E+9Qb/04xtwUsKZ/olKSlfdTx0+MgJHtz8MbgW1Isk+r2OuQ37iqTN/diKqX
DH4PHgc4Ch1Jupd8Fu1a6lR98L0Qj+SvnyNA8auRu24kFdFn/RJtwXRvuNFbAJ/e
LNmWqQ5K1Bq88XtucSZX1C8b9LsKJdSW8zKKZrbdgQKBgQDdKssB/drJD5+E75JJ
DMue9YSS4S0EMv/YGkHCNfnOs1IRHkKfeSNOle8Sqtp655cNWrXR/ETsNONqV+x6
R1ZACKOUhCMc9Z2eUNqYj+dabyo23gcrwIz5hFLC7fBDKcrZUNzm00UUX+zwqMBT
DHHqskWxQ/oExEOn5EXrdSZtQQKBgQDIeRVg9xXn0IcAArmOAj7ib2IJ/3poBLAT
mS3kBW0P7sHLWmlundJ/D3f9w6GgJ5bGxqsfOq8MfkzoFbhAt3nzdkCMwMZrz0iB
ck3isHOhZyVX0z9UpNAde087EDS5N7F4qhBJkR7XWXRFvNRsOEw7hkgGVigBmJ1f
vkRbPuwRjQKBgQCktWTgg9R3Hkp3bw9rlbrjFAd6d3XWBcEhiFRmtVnoBQXeN8H4
D/gqY2DbbyAsneKRkHeN/ai6nJysqvQzEIN8RrLEPTAFNin/KEnTioAKinVOzUVb
4RdcD56vCxJ+glZOR3lr8fUlOlcz1wj8EG2aEs/yNySwfhXAqDEmLGXEwQKBgQCk
Is8oNuVOiWMe1RxLcvc9uehRO0VjSQNI6I+0M+UZuGgfMQVFth4UPfwGX1hDomZG
lX6h8RBFcFtTYgUbp51HgrhTBbrvpiU9JvMx+TqTGbpvb9xYVyC2IrG6MAia5Uh+
/O6c7R6NPwZ92p8pg+aWjdkGpx/WPrgLHeMluhzaOQKBgACvhK/bfH7sIaPbVRZZ
por7v3JCt03/OPeHsCtH8FZy+3o1F+0ewe2RBL4XLOY/ppjOq2J5HP125dZodv+7
3AZlBAgZgfyKALB8178wrb7MQIB1ZrlxbiF5HFlUIs3AzgIs5vkTsSh9xBZiPv0H
YBatuB6qIblY/jtj89QDTvmE
-----END PRIVATE KEY-----
PEM;

const TEST_RSA_PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArTIKS8puara5OSr5Linx
Yk7o0tXYrOlI4nr397rPinb2yuvXb31//CNc9yNVQK7rX5B1N7n+9wHLw25t31H/
csQyG0oI1UB9agwvXmB1yTKfvcZ5HRQdaA0R/qajK0x4V0CTqc5Twz4t0Kb9aRjF
i/0/Kpx1+Ea5aRfUqqE5zUEWtgnWhWZf3Ks+wNuznr1eLYsKIwSzE1UYEtR+Kc5l
srbLzhqg6NH63/6z2NerhphbuxSxWJoT86J9mh4csLbZEyGwjtdTv/GP0mrnBueK
8fO/P9Ne9MhaFqMju4/DUOGSWSAAeGcdGhCAYnL9YFsLLjQ3ACtS2ocruo10iVd9
zQIDAQAB
-----END PUBLIC KEY-----
PEM;

// ══════════════════════════════════════════════════════════════════════════════
// Helper Functions
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Create and persist a Book model with sensible defaults.
 * Any key in $overrides will replace the corresponding default.
 */
function makeBook(array $overrides = []): Book
{
    return Book::create(array_merge([
        'title'           => 'Test Book',
        'author'          => 'Test Author',
        'isbn'            => (string) Str::uuid(),   // unique per call
        'genre'           => 'fiction',
        'description'     => 'A test book description.',
        'cover_image_url' => null,
        'stock_total'     => 5,
        'stock_available' => 5,                      // equals stock_total by default
        'price_per_day'   => 10.00,
        'published_year'  => 2023,
    ], $overrides));
}

/**
 * Issue an RS256 JWT with role=admin using the hardcoded test private key.
 * Also sets config('jwt.public_key') so JwtVerifyMiddleware can verify it.
 */
function makeAdminToken(): string
{
    config(['jwt.public_key' => TEST_RSA_PUBLIC_KEY]);

    return JWT::encode([
        'sub'   => '1',
        'email' => 'admin@test.com',
        'role'  => 'admin',
        'exp'   => time() + 3600,
    ], TEST_RSA_PRIVATE_KEY, 'RS256');
}

/**
 * Issue an RS256 JWT with role=user using the hardcoded test private key.
 * Also sets config('jwt.public_key') so JwtVerifyMiddleware can verify it.
 */
function makeUserToken(): string
{
    config(['jwt.public_key' => TEST_RSA_PUBLIC_KEY]);

    return JWT::encode([
        'sub'   => '2',
        'email' => 'user@test.com',
        'role'  => 'user',
        'exp'   => time() + 3600,
    ], TEST_RSA_PRIVATE_KEY, 'RS256');
}

// ══════════════════════════════════════════════════════════════════════════════
// Group: Book Listing (public)
// ══════════════════════════════════════════════════════════════════════════════

describe('Book Listing (public)', function () {

    it('lists books and returns paginated response', function () {
        makeBook(['title' => 'Laravel Deep Dive']);
        makeBook(['title' => 'PHP Internals']);

        $response = $this->getJson('/api/books');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => ['items'],
                 ]);

        expect($response->json('data.items'))->not->toBeEmpty();
    });

    it('filters books by genre', function () {
        makeBook(['title' => 'Fiction Story',   'genre' => 'fiction']);
        makeBook(['title' => 'Science Journal', 'genre' => 'science']);

        $response = $this->getJson('/api/books?filter[genre]=fiction');

        $response->assertStatus(200);

        $items = $response->json('data.items');
        expect($items)->not->toBeEmpty();

        foreach ($items as $item) {
            expect($item['genre'])->toBe('fiction');
        }
    });

    it('filters books by search query', function () {
        makeBook(['title' => 'UniqueXYZ123Title']);
        makeBook(['title' => 'Another Ordinary Book']);

        $response = $this->getJson('/api/books?filter[search]=UniqueXYZ123Title');

        $response->assertStatus(200);

        $items = $response->json('data.items');
        expect($items)->not->toBeEmpty();
        expect($items[0]['title'])->toBe('UniqueXYZ123Title');
    });

    it('filters available books', function () {
        makeBook(['title' => 'Available Book',   'stock_total' => 5, 'stock_available' => 5]);
        makeBook(['title' => 'Unavailable Book', 'stock_total' => 5, 'stock_available' => 0]);

        $response = $this->getJson('/api/books?filter[available]=true');

        $response->assertStatus(200);

        $items = $response->json('data.items');
        expect($items)->not->toBeEmpty();

        foreach ($items as $item) {
            expect($item['stock_available'])->toBeGreaterThan(0);
        }
    });

    it('sorts books by price_per_day', function () {
        makeBook(['title' => 'Expensive Book', 'price_per_day' => 50.00]);
        makeBook(['title' => 'Cheap Book',     'price_per_day' =>  5.00]);
        makeBook(['title' => 'Mid-range Book', 'price_per_day' => 25.00]);

        // Spatie sort format: ?sort=price_per_day  (ascending)
        $response = $this->getJson('/api/books?sort=price_per_day');

        $response->assertStatus(200);

        $items = $response->json('data.items');
        expect($items)->not->toBeEmpty();

        // Cast to float because price_per_day arrives as a decimal string
        $prices = array_map(fn ($item) => (float) $item['price_per_day'], $items);

        for ($i = 0; $i < count($prices) - 1; $i++) {
            expect($prices[$i])->toBeLessThanOrEqual($prices[$i + 1]);
        }
    });

});

// ══════════════════════════════════════════════════════════════════════════════
// Group: Book Detail (public)
// ══════════════════════════════════════════════════════════════════════════════

describe('Book Detail (public)', function () {

    it('returns a single book', function () {
        $book = makeBook(['title' => 'Detail Test Book']);

        $response = $this->getJson("/api/books/{$book->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.id', $book->id);
    });

    it('returns 404 for nonexistent book', function () {
        $fakeId = (string) Str::uuid();

        $response = $this->getJson("/api/books/{$fakeId}");

        $response->assertStatus(404)
                 ->assertJsonPath('success', false);
    });

});

// ══════════════════════════════════════════════════════════════════════════════
// Group: Create Book (admin)
// ══════════════════════════════════════════════════════════════════════════════

describe('Create Book (admin)', function () {

    it('creates a book as admin', function () {
        $token = makeAdminToken();

        $payload = [
            'title'          => 'New Admin Book',
            'author'         => 'Admin Author',
            'isbn'           => '978-0-000000-00-1',
            'genre'          => 'non-fiction',
            'description'    => 'Created by an admin user.',
            'stock_total'    => 10,
            'price_per_day'  => 7.50,
            'published_year' => 2024,
        ];

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                         ->postJson('/api/books', $payload);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.title', 'New Admin Book');

        // stock_available must equal stock_total when a book is first created
        expect($response->json('data.stock_available'))
            ->toBe($response->json('data.stock_total'));
    });

    it('rejects create without auth', function () {
        $response = $this->postJson('/api/books', [
            'title'         => 'No Auth Book',
            'author'        => 'Ghost',
            'price_per_day' => 5.00,
            'stock_total'   => 1,
        ]);

        $response->assertStatus(401)
                 ->assertJsonPath('success', false);
    });

    it('rejects create with non-admin token', function () {
        $token = makeUserToken();

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                         ->postJson('/api/books', [
                             'title'         => 'User Attempt Book',
                             'author'        => 'Regular User',
                             'price_per_day' => 5.00,
                             'stock_total'   => 3,
                         ]);

        $response->assertStatus(403)
                 ->assertJsonPath('success', false);
    });

    it('validates required fields on create', function () {
        $token = makeAdminToken();

        // Submit an entirely empty body — all required fields missing
        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                         ->postJson('/api/books', []);

        $response->assertStatus(422)
                 ->assertJsonPath('success', false)
                 ->assertJsonStructure(['errors']);

        expect($response->json('errors'))->not->toBeNull();
    });

});

// ══════════════════════════════════════════════════════════════════════════════
// Group: Update Book (admin)
// ══════════════════════════════════════════════════════════════════════════════

describe('Update Book (admin)', function () {

    it('updates a book as admin', function () {
        $book  = makeBook(['title' => 'Old Title']);
        $token = makeAdminToken();

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                         ->putJson("/api/books/{$book->id}", [
                             'title' => 'New Updated Title',
                         ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.title', 'New Updated Title');
    });

    it('recalculates stock_available when stock_total increases', function () {
        // Start: stock_total=5, stock_available=5 (nothing rented)
        $book  = makeBook(['stock_total' => 5, 'stock_available' => 5]);
        $token = makeAdminToken();

        // Increase stock_total by 5 → stock_available should also increase by 5 → 10
        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                         ->putJson("/api/books/{$book->id}", [
                             'stock_total' => 10,
                         ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);

        expect($response->json('data.stock_available'))->toBe(10);
    });

});

// ══════════════════════════════════════════════════════════════════════════════
// Group: Delete Book (admin)
// ══════════════════════════════════════════════════════════════════════════════

describe('Delete Book (admin)', function () {

    it('soft deletes a book as admin', function () {
        $book  = makeBook(['title' => 'Book To Delete']);
        $token = makeAdminToken();

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
             ->deleteJson("/api/books/{$book->id}")
             ->assertStatus(200)
             ->assertJsonPath('success', true);

        // The book must no longer appear in the public listing
        $listResponse = $this->getJson('/api/books');
        $ids = array_column($listResponse->json('data.items') ?? [], 'id');
        expect($ids)->not->toContain($book->id);
    });

    it('returns 409 when book has copies rented', function () {
        // stock_available < stock_total  →  some copies are currently rented out
        $book  = makeBook(['stock_total' => 5, 'stock_available' => 3]);
        $token = makeAdminToken();

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                         ->deleteJson("/api/books/{$book->id}");

        $response->assertStatus(409)
                 ->assertJsonPath('success', false);
    });

});

// ══════════════════════════════════════════════════════════════════════════════
// Group: Internal Stock (service.key)
// ══════════════════════════════════════════════════════════════════════════════

describe('Internal Stock (service.key)', function () {

    // Configure the shared service key before each test in this group
    beforeEach(function () {
        config(['services.service_key' => 'test-service-key']);
    });

    it('returns stock for a book via internal endpoint', function () {
        $book = makeBook(['stock_total' => 8, 'stock_available' => 6]);

        $response = $this->withHeaders(['X-Service-Key' => 'test-service-key'])
                         ->getJson("/api/internal/books/{$book->id}/stock");

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure([
                     'data' => ['id', 'stock_available', 'price_per_day'],
                 ]);

        expect($response->json('data.id'))->toBe($book->id);
    });

    it('returns 403 without service key', function () {
        $book = makeBook();

        // No X-Service-Key header → should be forbidden
        $response = $this->getJson("/api/internal/books/{$book->id}/stock");

        $response->assertStatus(403)
                 ->assertJsonPath('success', false);
    });

    it('reserves stock successfully', function () {
        $book = makeBook(['stock_total' => 5, 'stock_available' => 5]);

        $response = $this->withHeaders(['X-Service-Key' => 'test-service-key'])
                         ->patchJson("/api/internal/books/{$book->id}/stock", [
                             'action'   => 'reserve',
                             'quantity' => 1,
                         ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);

        // stock_available must decrease by the reserved quantity
        expect($response->json('data.stock_available'))->toBe(4);
    });

    it('releases stock successfully', function () {
        // 2 copies are currently rented out (5 total, 3 available)
        $book = makeBook(['stock_total' => 5, 'stock_available' => 3]);

        $response = $this->withHeaders(['X-Service-Key' => 'test-service-key'])
                         ->patchJson("/api/internal/books/{$book->id}/stock", [
                             'action'   => 'release',
                             'quantity' => 1,
                         ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true);

        // stock_available must increase by the released quantity
        expect($response->json('data.stock_available'))->toBe(4);
    });

    it('returns 409 when reserving more than available', function () {
        $book = makeBook(['stock_total' => 5, 'stock_available' => 0]);

        $response = $this->withHeaders(['X-Service-Key' => 'test-service-key'])
                         ->patchJson("/api/internal/books/{$book->id}/stock", [
                             'action'   => 'reserve',
                             'quantity' => 1,
                         ]);

        $response->assertStatus(409)
                 ->assertJsonPath('success', false);
    });

    it('returns 409 when releasing more than stock_total', function () {
        // stock_available is already at its maximum (== stock_total)
        // releasing any more would push it beyond stock_total
        $book = makeBook(['stock_total' => 5, 'stock_available' => 5]);

        $response = $this->withHeaders(['X-Service-Key' => 'test-service-key'])
                         ->patchJson("/api/internal/books/{$book->id}/stock", [
                             'action'   => 'release',
                             'quantity' => 1,
                         ]);

        $response->assertStatus(409)
                 ->assertJsonPath('success', false);
    });

});
