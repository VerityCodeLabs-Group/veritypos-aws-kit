# Consuming Events

The package supports 3 runtimes. Pick one per service. Most services run the Fargate supervisord worker; the Lambda adapter is kept for cases where the consumer logic needs to scale independently of the web tier.

The kit's SQS consumer is **binding-driven**: a service registers one `QueueBinding` per inbound queue (typically from a service provider's `register()`), and supervisord invokes `aws-kit:sqs-consume --binding=NAME` to pick which one to consume. A single-binding service can omit `--binding=NAME` and the command auto-selects the one binding.

## Step 1: Register your QueueBindings

In your service's `AppServiceProvider` (or a dedicated `AwsKitBindingsServiceProvider`), register one `QueueBinding` per inbound queue:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use VerityPOS\AwsKit\Sqs\QueueBinding;
use VerityPOS\AwsKit\Sqs\QueueBindingRegistry;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Inter-service EventBridge-fed SQS (kit's default parser,
        // prefix-based dispatcher — typical "user.*" / "tenant.*" use)
        $this->app->make(QueueBindingRegistry::class)->register(
            new QueueBinding(
                name: 'inter-service',
                queueUrl: env('SQS_INTER_SERVICE_QUEUE_URL'),
                region: 'ap-southeast-1',
            ),
        );

        // Device bus SQS (different envelope shape, custom parser
        // and dispatcher — typical sync-engine-fed use)
        $this->app->make(QueueBindingRegistry::class)->register(
            new QueueBinding(
                name: 'device-events',
                queueUrl: env('SQS_DEVICE_EVENTS_QUEUE_URL'),
                envelopeParserClass: SyncMessageDataEnvelopeParser::class,
                dispatcherClass: DeviceEventDispatcher::class,
            ),
        );
    }
}
```

Each binding has 4 fields that vary per queue:

| Field | Purpose |
|---|---|
| `name` | Unique identifier. Used by `--binding=NAME` and in logs. |
| `queueUrl` | SQS queue URL. |
| `envelopeParserClass` | Class implementing `VerityPOS\AwsKit\Contracts\EnvelopeParser` to unwrap the message into a `Contracts\Envelope`. Defaults to `SqsEnvelopeParser` (EventBridge-fed single-event shape). |
| `dispatcherClass` | Class implementing `VerityPOS\AwsKit\Contracts\Dispatcher` to route the unwrapped event. If null, the global `Contracts\Dispatcher` binding is used. |

The global `Contracts\Dispatcher` is still bound (typically as `PrefixDispatcher` for inter-service prefix-routing) — bindings without their own `dispatcherClass` fall back to it.

## Step 2: Implement your Dispatchers + Handlers

A binding with a custom `dispatcherClass` lets you route messages by **full event-type** instead of prefix (useful for non-hierarchical event names) or dispatch **multiple events from a single message** (useful for batched envelopes like `SyncMessageData`).

```php
namespace App\Messaging;

use VerityPOS\AwsKit\Contracts\Dispatcher;
use VerityPOS\AwsKit\Contracts\Envelope;

final class DeviceEventDispatcher implements Dispatcher
{
    public function dispatch(string $eventType, array $payload): void
    {
        $handler = match ($eventType) {
            'order.created'   => app(OrderCreatedHandler::class),
            'order.completed' => app(OrderCompletedHandler::class),
            'product_price.*' => app(ProductPriceHandler::class),
            default => throw new \RuntimeException("Unhandled event type: {$eventType}"),
        };

        $handler->handle($eventType, $payload);
    }
}
```

Handlers implement whichever contract the dispatcher is built for. Most kit-native handlers use the runtime-agnostic `Handler` contract:

```php
namespace App\Messaging\Handlers;

use VerityPOS\AwsKit\Contracts\Handler;

final class OrderCreatedHandler implements Handler
{
    public function handle(string $eventType, array $payload): void
    {
        // idempotency check, dispatch to a use case, etc.
    }
}
```

For typed payloads, use `veritypos/domain-events`'s `DomainEventHandler` contract (which the kit's `PrefixDispatcher` bridges to). See the platform-service `MessagingServiceProvider` for a worked example.

## Step 3a: Fargate Supervisord Worker (recommended)

Add one program per binding to your `docker/prod/supervisord.conf`:

```ini
[program:aws-kit-sqs-consume-inter-service]
command=php /var/www/artisan aws-kit:sqs-consume --binding=inter-service
user=www-data
autostart=true
autorestart=true
startsecs=5
stopwaitsecs=30
stopsignal=TERM
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
priority=15

[program:aws-kit-sqs-consume-device-events]
command=php /var/www/artisan aws-kit:sqs-consume --binding=device-events
user=www-data
autostart=true
autorestart=true
startsecs=5
stopwaitsecs=30
stopsignal=TERM
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
priority=15
```

The worker per binding:
- Long-polls SQS (WaitTimeSeconds=20, configurable per binding)
- Parses each message with the binding's `EnvelopeParser`
- Calls the binding's `Dispatcher::dispatch()` (or the global one)
- On success: deletes the message (ack, configurable per binding)
- On failure: leaves it for redelivery (visibility timeout expires)
- Handles SIGTERM/SIGINT for graceful drain

A service with one binding can omit `--binding=NAME` and the command auto-selects the one binding.

## Step 3b: Lambda (alternative)

```php
// lambda/event-bridge.php
return $app->make(\VerityPOS\AwsKit\EventBridge\Runtime\EventBridgeLambdaHandler::class);
```

This handler is auto-wired with the `Dispatcher` and the `EventBridgeEnvelopeParser` from the container. The handler:
- Receives the EventBridge event via Bref
- Re-parses the envelope (defense in depth)
- Calls `Dispatcher::dispatch()`
- Throws on failure (triggers Lambda's retry policy → DLQ after retries)

**When to use Lambda over Fargate:**
- The consumer logic scales independently of the web tier (e.g. analytics ingests 100x more events than the web receives requests)
- You need burst capacity beyond the Fargate task count
- You're not on Fargate at all (e.g. on EC2 + supervisord that can't add tasks)

**When to use Fargate over Lambda:**
- The default — and the goal. One runtime, one image, one deploy.

## Step 3c: CLI Simulator (local dev / debugging)

Run a single event through the dispatcher without standing up LocalStack or a queue:

```bash
# Inline
php artisan aws-kit:event-bridge-invoke \
    --event-type=user.created \
    --source=auth \
    --payload='{"id":"u-1","email":"a@b.com","tenant_id":"t-1"}'

# From a JSON file
php artisan aws-kit:event-bridge-invoke --event-file=event.json
```

`event.json` is a full EventBridge envelope:

```json
{
    "version": "0",
    "id": "...",
    "source": "veritypos.auth",
    "detail-type": "user.created",
    "account": "000000000000",
    "time": "2026-06-12T00:00:00Z",
    "region": "ap-southeast-1",
    "resources": [],
    "detail": {
        "id": "u-1",
        "email": "a@b.com",
        "tenant_id": "t-1"
    }
}
```

The CLI exits 0 on success, 1 on handler failure — same as the Lambda handler.

## Step 4: Run the Worker

Fargate:

```bash
# The supervisord program starts it automatically. Manual:
php artisan aws-kit:sqs-consume --binding=inter-service
php artisan aws-kit:sqs-consume --binding=device-events
```

Lambda:

```bash
# The CI workflow deploys the ZIP. Manual:
make staging-lambda-deploy SVC=auth
```

CLI:

```bash
php artisan aws-kit:event-bridge-invoke --event-type=user.created --payload='{"id":"1"}'
```

## Backward compatibility (v0.4.x → v0.5.0)

The v0.4 single-queue invocation pattern still works without any code change:

```bash
# Old style (v0.4.x, still supported in v0.5.0 as a fallback):
php artisan aws-kit:sqs-consume --queue=https://sqs.ap-southeast-1.amazonaws.com/123/auth-events
```

If no `QueueBinding` is registered in the registry, the command falls back to `config('aws-kit.sqs.consumer.queue_url')` + the kit's default `SqsEnvelopeParser` + the global `Dispatcher`, and logs a one-time warning. Migrate by registering a `QueueBinding` (or several) in a service provider — supervisord then switches to the explicit `--binding=NAME` form.
