---
name: pest-testing
description: >-
  Guides testing with the Pest 3 PHP framework. Activates when writing tests, creating unit tests,
  adding assertions, debugging test failures, working with datasets or mocking; or when the user
  mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.
---

# Pest Testing

## When to Apply

- Writing new tests or updating existing tests
- Adding assertions or debugging test failures
- Working with mocking, faking, or datasets

## Test Runner

This is a library — tests run directly on the host:

```bash
composer test:unit              # Run all tests
vendor/bin/pest --filter=Name   # Run specific test
vendor/bin/pest --compact       # Compact output
```

## Test Structure

```
tests/
├── TestCase.php    # Orchestra Testbench base
├── Pest.php        # Applies TestCase to Unit/
└── Unit/
    ├── Auth/
    │   ├── AuthContextTest.php
    │   └── JwtRoundTripTest.php
    └── Middleware/
        ├── EnsurePermissionTest.php
        ├── EnsureRoleTest.php
        └── ResolveAuthContextTest.php
```

Tests mirror the `src/` directory structure under `tests/Unit/`.

## Test Conventions

### Use `it()` with closures

```php
it('creates from claims', function (): void {
    $context = AuthContext::fromClaims([
        'user_id' => 42,
        'tenant_id' => 1,
        'roles' => ['tenant_admin'],
        'permissions' => ['pos.access'],
    ]);

    expect($context->userId)->toBe(42)
        ->and($context->tenantId)->toBe(1);
});
```

### Always type the closure return

```php
it('does something', function (): void {
    // ...
});
```

### Use `expect()` chains — not PHPUnit assertions

```php
// Good
expect($result)->toBe(42)
    ->and($other)->toBeNull();

// Bad
$this->assertEquals(42, $result);
```

### Group related assertions with `->and()`

```php
expect($context->userId)->toBe(42)
    ->and($context->tenantId)->toBe(1)
    ->and($context->roles)->toBe(['tenant_admin'])
    ->and($context->portal)->toBe('client');
```

## HTTP Testing with Testbench

For middleware tests, use Orchestra Testbench's routing helpers:

```php
use Illuminate\Http\Request;
use Illuminate\Http\Response;

it('resolves auth context from valid JWT', function (): void {
    $encoder = new JwtEncoder($secret, 'HS256', 3600);
    $token = $encoder->encode($context);

    $request = Request::create('/test', 'GET');
    $request->headers->set('Authorization', "Bearer {$token}");

    $middleware = new ResolveAuthContext(new JwtDecoder($secret, 'HS256'));
    $response = $middleware->handle($request, fn ($req) => new Response('ok'));

    expect($response->getStatusCode())->toBe(200);
});
```

## Exception Testing

```php
it('throws on invalid token', function (): void {
    $decoder->decode('invalid.token');
})->throws(AuthenticationException::class);

it('throws with specific message', function (): void {
    $decoder->decode($expiredToken);
})->throws(AuthenticationException::class, 'expired');
```

## Key Rules

- Every change must have a corresponding test
- Tests live in `tests/Unit/` mirroring `src/` structure
- Use `expect()` with `->and()` chains
- No `$this->` PHPUnit assertions
- Type closure returns as `: void`
- Run `composer test:unit` before finalizing

## Common Pitfalls

- Using `test()` instead of `it()` — this SDK uses `it()`
- Using PHPUnit assertions instead of Pest `expect()`
- Putting tests in the wrong directory
- Not running tests after changes
- Forgetting to type the closure return
