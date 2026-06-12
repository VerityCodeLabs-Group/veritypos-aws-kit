# Testing

The package's contracts make testing straightforward: you bind test doubles for `Dispatcher` / `Handler` / `Consumer`, and exercise the runtime-agnostic code without touching AWS.

## Unit Tests (the package's own tests)

The package itself ships with unit tests for every component. They use Orchestra Testbench + Pest 3:

```bash
composer test           # lint + stan + unit
composer test:unit      # pest only
composer test:lint:fix  # pint
```

PHPStan runs at level 8. Pint enforces `declare_strict_types` + `final_class`.

## Testing Your Consumer Code (downstream)

Your handler tests should mock the `Dispatcher` and verify the handler is called with the right arguments:

```php
use App\Messaging\Handlers\UserEventHandler;
use VerityPOS\AwsKit\Contracts\Dispatcher;

it('creates a user and a tenant_user pivot on user.created', function (): void {
    $dispatcher = Mockery::mock(Dispatcher::class);
    $dispatcher->shouldReceive('dispatch')
        ->once()
        ->with('user.created', ['id' => 'u-1', 'email' => 'a@b.com', 'tenant_id' => 't-1']);

    $handler = new UserEventHandler(/* ... */);
    $handler->handle('user.created', [
        'id' => 'u-1',
        'email' => 'a@b.com',
        'tenant_id' => 't-1',
    ]);
});
```

## Testing Your Producer Code (downstream)

The `EventBridgePublisher` makes AWS SDK calls. For unit tests, mock it at the container level:

```php
use VerityPOS\AwsKit\EventBridge\EventBridgePublisher;

it('publishes a user.created event with the right source prefix', function (): void {
    $publisher = Mockery::mock(EventBridgePublisher::class);
    $publisher->shouldReceive('publish')
        ->once()
        ->with('auth', 'user.created', Mockery::on(function ($detail) {
            return $detail['id'] === 'u-1' && $detail['email'] === 'a@b.com';
        }));

    app()->instance(EventBridgePublisher::class, $publisher);

    // Trigger your code that publishes the event
    $this->postJson('/users', [...]);

    // Verify the event was published with the right args
});
```

For integration tests that actually exercise the AWS SDK (LocalStack or live AWS), use the `--once` flag on the CLI simulator to assert the dispatcher fires:

```bash
php artisan aws-kit:sqs-consume --queue=test-queue --once
```

## End-to-End with LocalStack

LocalStack (the `veritypos-localstack` container in `local/`) provides real SQS + EventBridge at `http://veritypos-localstack:4566`. Use the LocalStack AWS env vars from [configuration.md](configuration.md) to wire the package against it:

```dotenv
AWS_ENDPOINT=http://veritypos-localstack:4566
AWS_ACCESS_KEY_ID=test
AWS_SECRET_ACCESS_KEY=test
EVENTBRIDGE_BUS_NAME=veritypos-domain-events
EVENTBRIDGE_ENDPOINT=http://veritypos-localstack:4566
```

Then test the full flow: `php artisan aws-kit:event-bridge-invoke` against a LocalStack-bound `EventBridgePublisher` + a `PrefixDispatcher` registered with a test handler.
