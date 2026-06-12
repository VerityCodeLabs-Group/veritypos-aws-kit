# Publishing Events

Use `EventBridgePublisher` to emit domain events. The publisher is auto-bound as a singleton by the service provider — fetch it from the container.

## Basic Usage

```php
use VerityPOS\AwsKit\EventBridge\EventBridgePublisher;

app(EventBridgePublisher::class)->publish(
    source: 'auth',           // becomes "veritypos.auth" with the prefix
    eventType: 'user.created',
    detail: [
        'id' => $user->id,
        'email' => $user->email,
        'tenant_id' => $user->tenant_id,
    ],
);
```

The resulting event on the bus:

```json
{
    "version": "0",
    "id": "...",
    "source": "veritypos.auth",
    "detail-type": "user.created",
    "detail": {
        "id": "u-1",
        "email": "a@b.com",
        "tenant_id": "t-1"
    }
}
```

## From a Laravel Observer

The recommended pattern — fire the event from a model observer, and the job is dispatched onto the queue so the HTTP request doesn't block on the AWS call:

```php
namespace Infrastructure\Messaging\Events\Listeners;

use Domain\Identity\Events\UserCreatedEvent;
use Infrastructure\Messaging\Events\Outbound\UserCreated;
use Infrastructure\Messaging\Jobs\PublishDomainEventJob;
use VerityPOS\AwsKit\EventBridge\EventBridgePublisher;

final class PublishUserEventListener
{
    public function handleUserCreated(UserCreatedEvent $event): void
    {
        PublishDomainEventJob::dispatch(
            new UserCreated($event->user, $event->tenantId),
        );
    }
}
```

The `PublishDomainEventJob` is service-specific (it knows your `DomainEvent` base class). It calls `$event->publish()` which ends up here:

```php
// Inside your DomainEvent::publish() implementation
public function publish(): void
{
    app(EventBridgePublisher::class)->publish(
        source: $this->source()->value,        // e.g. "auth"
        eventType: $this->eventType()->value,   // e.g. "user.created"
        detail: $this->payload()->toArray(),
    );
}
```

## Error Handling

`publish()` throws on AWS SDK errors. The exception is logged via `Log::error` before being re-thrown. Common failures:

- **`Aws\Exception\CredentialsException`** — task role not attached, or AWS creds missing. The service's `veritypos-production-ecs-task-role` should grant `events:PutEvents`.
- **`Aws\Exception\EventBridgeException`** — bus doesn't exist, or the IAM role doesn't have `events:PutEvents` on this bus.
- **`Aws\Exception\NetworkException`** — transient network blip. Laravel's queue:work retries automatically; the `PublishDomainEventJob` should be `ShouldQueue` so failures go through the standard retry path.

## Local Testing Without AWS

```bash
# In LocalStack-backed dev environment
EVENTBRIDGE_ENDPOINT=http://veritypos-localstack:4566 \
php artisan tinker
> app(VerityPOS\Aws-kit\EventBridge\EventBridgePublisher::class)->publish('auth', 'user.created', ['id' => '1']);
```

The publisher auto-detects the LocalStack endpoint and the SQS client is built with dummy `test`/`test` credentials.
