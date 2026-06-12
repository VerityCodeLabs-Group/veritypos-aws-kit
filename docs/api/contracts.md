# Contracts

The package's `src/Contracts/` directory defines 4 runtime-agnostic interfaces. Consumer services depend on these contracts — never on the concrete AWS-specific implementations — so the same handler code runs in every runtime.

## Envelope

```php
namespace VerityPOS\AwsKit\Contracts;

interface Envelope
{
    public function source(): string;
    public function eventType(): string;
    public function payload(): array;
}
```

Represents an AWS-delivered event after the protocol envelope has been unwrapped. Implementations:

- `EventBridge/EventBridgeEnvelope` — wraps the EventBridge `detail` field
- `Sqs/SqsEnvelope` — wraps the SQS `Body` field

`source()` is the protocol-specific identifier (e.g. `veritypos.auth` for EventBridge, the queue ARN for SQS). `eventType()` is the routing key. `payload()` is the unwrapped domain DTO.

## Handler

```php
namespace VerityPOS\AwsKit\Contracts;

interface Handler
{
    public function handle(string $eventType, array $payload): void;
}
```

The consumer service implements this contract for each event type it cares about. Throw to signal failure (triggers retry / DLQ).

```php
final class UserEventHandler implements Handler
{
    public function handle(string $eventType, array $payload): void
    {
        // dispatch by event type
    }
}
```

## Dispatcher

```php
namespace VerityPOS\AwsKit\Contracts;

interface Dispatcher
{
    public function dispatch(string $eventType, array $payload): void;
}
```

Routes an Envelope to a Handler. Two implementations ship with the package:

- `Dispatcher/PrefixDispatcher` — routes by event type prefix (production usage)
- `Dispatcher/NullDispatcher` — no-op default (used when the consumer service hasn't bound its own Dispatcher yet)

A consumer service binds its own `Dispatcher` in `AppServiceProvider` with the routing table.

## Consumer

```php
namespace VerityPOS\AwsKit\Contracts;

interface Consumer
{
    public function consume(callable $handler): void;
    public function stop(): void;
}
```

Long-poll worker that reads from a source and invokes the callable for each message. The runtime adapter implements this. SQS is the only current implementation; SNS / EventBridge direct consumer will land in v0.3.0+.

```php
$consumer->consume(function (Envelope $envelope): void {
    $dispatcher->dispatch($envelope->eventType(), $envelope->payload());
});
```

`stop()` signals the consumer to exit after the current message finishes — wired to SIGTERM / SIGINT in `Consumer::registerSignalHandlers()`.

## Why Runtime-Agnostic

The whole point of these contracts is that **the consumer service writes its handlers once, and the runtime is a deployment detail**. Switching from Fargate to Lambda, or from LocalStack CLI to production, is a config change, not a code change.

Concretely: if you bind `Dispatcher` in `AppServiceProvider` and your handlers implement `Handler`, the same code runs in:
- `aws-kit:sqs-consume` (Fargate supervisord)
- `EventBridgeLambdaHandler` (Bref Lambda)
- `aws-kit:event-bridge-invoke` (local CLI)

No service-level changes between environments.
