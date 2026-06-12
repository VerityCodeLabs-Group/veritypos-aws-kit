# Consuming Events

The package supports 3 runtimes. Pick one per service. Most services run the Fargate supervisord worker; the Lambda adapter is kept for cases where the consumer logic needs to scale independently of the web tier.

## Step 1: Bind a Dispatcher in AppServiceProvider

The `Dispatcher` is runtime-agnostic — you bind it once, and all 3 runtimes call it. Use the `PrefixDispatcher` to route by event type prefix:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use VerityPOS\Aws-kit\Dispatcher\PrefixDispatcher;
use VerityPOS\aws-kit\Contracts\Dispatcher;
use App\Messaging\Handlers\UserEventHandler;
use App\Messaging\Handlers\TenantEventHandler;
use App\Messaging\Handlers\DeviceEventHandler;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Dispatcher::class, function () {
            return new PrefixDispatcher([
                'user.'   => app(UserEventHandler::class),
                'tenant.' => app(TenantEventHandler::class),
                'device.' => app(DeviceEventHandler::class),
            ]);
        });
    }
}
```

First match wins. If no prefix matches, `PrefixDispatcher` throws a `RuntimeException` (which the runtime adapter catches and routes to the DLQ).

## Step 2: Implement your Handlers

Handlers implement the `VerityPOS\AwsKit\Contracts\Handler` contract:

```php
namespace App\Messaging\Handlers;

use VerityPOS\AwsKit\Contracts\Handler;
use App\Models\User;
use App\Models\TenantUser;

final class UserEventHandler implements Handler
{
    public function handle(string $eventType, array $payload): void
    {
        match ($eventType) {
            'user.created' => $this->onUserCreated($payload),
            'user.updated' => $this->onUserUpdated($payload),
            'user.deleted' => $this->onUserDeleted($payload),
            default => throw new \RuntimeException("Unhandled event type: {$eventType}"),
        };
    }

    private function onUserCreated(array $payload): void
    {
        $user = User::create([...]);
        TenantUser::create([
            'user_id' => $user->id,
            'tenant_id' => $payload['tenant_id'],
        ]);
    }

    // ...
}
```

Handlers can throw — the runtime adapter will leave the message in the queue for redelivery (SQS) or retry per the Lambda retry policy.

## Step 3a: Fargate Supervisord Worker (recommended)

Add a program to your `docker/prod/supervisord.conf`:

```ini
[program:aws-kit-sqs-consume-auth-events]
command=php /var/www/artisan aws-kit:sqs-consume --queue=%(ENV_SQS_QUEUE_URL)s
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

The worker:
- Long-polls SQS (WaitTimeSeconds=20)
- Parses each message into an Envelope
- Calls `Dispatcher::dispatch()` (your routes from step 1)
- On success: deletes the message (ack)
- On failure: leaves it for redelivery (visibility timeout expires)
- Handles SIGTERM/SIGINT for graceful drain

To consume multiple queues per service, add one program per queue (with different `--queue=` args).

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
php artisan aws-kit:sqs-consume --queue=https://sqs.ap-southeast-1.amazonaws.com/123/auth-events
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
