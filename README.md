# VerityPOS AWS Kit for PHP

Reusable AWS integration patterns for VerityPOS microservices: EventBridge publishers/consumers, SQS workers, Lambda handlers, SQS/SNS envelopes, and runtime adapters for Fargate, ECS, Lambda, and LocalStack.

**Why this package exists:** before `veritypos/aws-kit`, each VerityPOS service had its own ad-hoc AWS SDK calls, its own envelope-parsing code, and its own way of handling Lambda cold-starts. This package centralizes the common patterns so each service only writes the parts that are unique to it (which events it consumes, which domain DTOs it emits).

## What's in v0.1.0

| Concern | Where | Status |
|---|---|---|
| Generic AWS SDK client factory | `src/Aws/ClientFactory.php` | ✅ |
| Runtime-agnostic contracts | `src/Contracts/{Envelope,Handler,Dispatcher,Consumer}.php` | ✅ |
| Prefix-based event router | `src/Dispatcher/PrefixDispatcher.php` | ✅ |
| EventBridge publisher | `src/EventBridge/EventBridgePublisher.php` | ✅ |
| EventBridge envelope parser | `src/EventBridge/EventBridgeEnvelopeParser.php` | ✅ |
| Lambda handler adapter (Bref) | `src/EventBridge/Runtime/EventBridgeLambdaHandler.php` | ✅ |
| CLI simulator adapter | `src/Console/EventBridgeInvokeCommand.php` | ✅ |
| SQS consumer (Fargate supervisord) | `src/Console/SqsConsumeCommand.php` | 🚧 (next release) |
| SQS publisher | `src/Sqs/SqsPublisher.php` | 🚧 |
| SNS pub/sub | `src/Sns/` | 🚧 |
| CloudWatch metrics/alarms | `src/CloudWatch/` | 🚧 |
| EventBridge Scheduler | `src/Scheduler/` | 🚧 |

## Installation

```bash
composer require veritypos/aws-kit:^0.1
```

(Configure the GitHub VCS repo in your `composer.json` first, like the other `veritypos/*-sdk-php` packages.)

## Quick start

### Publishing an event

```php
use VerityPOS\AwsKit\EventBridge\EventBridgePublisher;

app(EventBridgePublisher::class)->publish(
    source: 'auth',
    eventType: 'user.created',
    detail: ['id' => $user->id, 'email' => $user->email],
);
```

### Consuming events (in your service's `AppServiceProvider`)

```php
use VerityPOS\AwsKit\Dispatcher\PrefixDispatcher;
use VerityPOS\AwsKit\Contracts\Dispatcher;

$this->app->singleton(Dispatcher::class, function () {
    return new PrefixDispatcher([
        'user.'        => app(UserEventHandler::class),
        'tenant.'      => app(TenantEventHandler::class),
        'device.'      => app(DeviceEventHandler::class),
    ]);
});
```

### Wiring a Lambda handler

```php
// lambda/event-bridge.php
return $app->make(\VerityPOS\AwsKit\EventBridge\Runtime\EventBridgeLambdaHandler::class);
```

### Local testing without LocalStack

```bash
php artisan aws-kit:event-bridge-invoke --event-type=user.created --payload='{"id":"1"}'
php artisan aws-kit:event-bridge-invoke --event-file=event.json
```

## Architecture

The package is organized around **3 patterns** that recur in every AWS integration:

1. **Client Factory** — uniform AWS SDK client construction (region, optional endpoint override for LocalStack, credentials)
2. **Envelope Parser** — unwrap protocol-specific JSON (EventBridge detail, SQS body, SNS message) into a uniform `Envelope` contract
3. **Runtime Adapter** — different "where does this code run" entry points (Lambda, Fargate worker, CLI) all calling the same `Dispatcher::dispatch()`

This separation means the consumer service writes the handler code once, and it runs identically in production (Lambda), local dev (CLI), and tests (in-process).

See [docs/architecture.md](docs/architecture.md) for the full design.

## Environment variables

| Var | Default | Purpose |
|---|---|---|
| `AWS_REGION` | `ap-southeast-1` | AWS region |
| `AWS_ENDPOINT` | _(null)_ | Override for LocalStack (`http://veritypos-localstack:4566`) |
| `AWS_ACCESS_KEY_ID` | _(null)_ | Explicit credentials (prod uses task role) |
| `AWS_SECRET_ACCESS_KEY` | _(null)_ | Explicit credentials (prod uses task role) |
| `EVENTBRIDGE_BUS_NAME` | `veritypos-domain-events` | EventBridge bus to publish to |
| `EVENTBRIDGE_SOURCE_PREFIX` | `veritypos` | Prepended to source (e.g. `veritypos.auth`) |
| `EVENTBRIDGE_REGION` | `AWS_REGION` | EventBridge region (usually same as AWS_REGION) |
| `EVENTBRIDGE_ENDPOINT` | _(null)_ | Override for LocalStack |

## Running tests

```bash
composer test           # lint + stan + unit
composer test:lint      # pint --test
composer test:unit      # pest
```

## License

MIT
