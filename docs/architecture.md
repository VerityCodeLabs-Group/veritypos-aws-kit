# Architecture

## Overview

`veritypos/aws-kit` is a shared PHP Composer package for AWS integration patterns. It centralizes the common EventBridge + SQS + Lambda + config glue that was previously duplicated across `veritypos/domain-events`, `veritypos/sync-sdk-php`, and the individual service repos.

> **Current scope (v0.2.0):** EventBridge publish, SQS publish + long-poll consume, EventBridge Lambda handler, CLI simulator, runtime-agnostic Dispatcher / Handler / Envelope / Consumer contracts. Future releases will add SNS pub/sub, CloudWatch metrics/alarms, EventBridge Scheduler, and X-Ray helpers.

## The 3 Patterns

Every AWS integration in this package follows one of these patterns. Recognizing the pattern tells you which class to look at.

### 1. Client Factory

`Aws/ClientFactory::build($service, $region, $endpoint, $credentials)` returns a configured AWS SDK client. Used by every concrete service (EventBridge, SQS, SNS, CloudWatch, ...).

**Why:** AWS SDK construction has the same shape for every service — region, optional endpoint override (LocalStack), credentials. One factory, one place to fix bugs / add config.

**Sister classes** (one per service):
- `Sqs/SqsClientFactory` — adds LocalStack URL detection
- (Future) `Sns/SnsClientFactory`, `CloudWatch/CloudWatchClientFactory`, ...

### 2. Envelope Parser

`Contracts/Envelope` is the runtime-agnostic shape of an AWS-delivered event. Each AWS protocol has a parser that unwraps the protocol-specific JSON into a uniform `Envelope`:

| AWS Protocol | Parser | Unwraps |
|---|---|---|
| EventBridge | `EventBridge/EventBridgeEnvelopeParser` | `detail` field |
| SQS | `Sqs/SqsEnvelopeParser` | `Body` field + `MessageAttributes.event_type` |
| (Future) SNS | `Sns/SnsEnvelopeParser` | `Message` field |

**Why:** consumer code shouldn't care if the event came from EventBridge, SQS, or SNS. After parsing, every envelope has the same `source() / eventType() / payload()` shape, and the same Dispatcher routes it to the right handler.

### 3. Runtime Adapter

Each "where does the code run" target gets its own adapter. All adapters call the same `Contracts/Dispatcher::dispatch()` so the consumer's handler code runs identically in every runtime:

| Runtime | Adapter | Where It Runs |
|---|---|---|
| Fargate supervisord worker | `Sqs/Consumer` + `Console/SqsConsumeCommand` | ECS Fargate container (Fargate task) |
| Lambda (Bref) | `EventBridge/Runtime/EventBridgeLambdaHandler` | AWS Lambda (Bref v3 layer) |
| CLI (local dev / tests) | `Console/EventBridgeInvokeCommand` | `php artisan` in the Fargate image |

**Why:** the same handler code runs in 3 different runtimes without modification. The runtime is a deployment detail, not a code detail.

## Runtime Architecture (Fargate)

This is the architecture the package enables in v0.2.0. All three runtimes are wired through the same Dispatcher:

```
  ┌─────────────── Fargate task: auth ───────────────┐
  │  supervisord:                                   │
  │    - nginx + php-fpm (HTTP)                     │
  │    - queue:work redis                           │
  │    - aws-kit:sqs-consume --queue=auth-events    │ ◄── consumes other services' events
  │                                                 │
  │  publishes:                                     │
  │    - EventBridgePublisher::publish()            │ ──publishes──┐
  └─────────────────────────────────────────────────┘                │
                                                                    ▼
                                              ┌─── EventBridge Bus ───┐
                                              │  rule: user.* → SQS    │──┐
                                              │  rule: tenant.* → SQS  │  │
                                              │  rule: device.* → SQS  │  │
                                              └───────────────────────┘  │
                                                                              │
  ┌─────────────── Fargate task: auth ───────────────┐                  │
  │  aws-kit:sqs-consume (Fargate supervisord)     │ ◄────────────────┘
  │       ↓                                          │
  │  SqsEnvelopeParser::parse()                      │
  │       ↓                                          │
  │  Dispatcher::dispatch('user.created')           │
  │       ↓                                          │
  │  UserEventHandler::handle()                      │
  │       ↓                                          │
  │  DeleteMessage (ack)                             │
  └─────────────────────────────────────────────────┘
```

All in **one image, one supervisord, one CI deploy per service**. No Lambda. No Bref. No cold starts.

## Component Map

```
src/
├── Aws/
│   └── ClientFactory.php                # Generic AWS SDK client builder
│
├── Config/
│   └── aws-kit.php                      # Publishable config
│
├── Console/
│   ├── EventBridgeInvokeCommand.php    # CLI simulator (local dev / tests)
│   └── SqsConsumeCommand.php            # Fargate supervisord worker
│
├── Contracts/                           # Runtime-agnostic interfaces
│   ├── Envelope.php                     # source / eventType / payload
│   ├── EnvelopeParser.php               # raw SQS message → Envelope (v0.5+)
│   ├── Handler.php                      # handle(string $type, array $payload)
│   ├── Dispatcher.php                  # dispatch(string $type, array $payload)
│   └── Consumer.php                     # consume(callable $handler)
│
├── Dispatcher/
│   ├── NullDispatcher.php               # No-op default (when no routes registered)
│   └── PrefixDispatcher.php            # Prefix-based event router
│
├── EventBridge/
│   ├── EventBridgeEnvelope.php          # Unwrapped EventBridge detail
│   ├── EventBridgeEnvelopeParser.php   # JSON → Envelope
│   ├── EventBridgePublisher.php         # putEvents wrapper
│   └── Runtime/
│       └── EventBridgeLambdaHandler.php # Bref Lambda adapter
│
├── Sqs/                                 # SQS transport + binding-driven consumer
│   ├── SqsClientFactory.php             # SQS-specific client builder
│   ├── SqsPublisher.php                 # sendMessage + sendMessageBatch
│   ├── SqsPublisherBinding.php          # Named publisher config (v0.5+)
│   ├── SqsPublisherRegistry.php         # name => SqsPublisherBinding (v0.5+)
│   ├── SqsPublisherFactory.php          # builds SqsPublisher from binding, cached (v0.5+)
│   ├── SqsEnvelope.php                 # Unwrapped SQS body + attributes
│   ├── SqsEnvelopeParser.php            # SQS message → Envelope (default impl)
│   ├── QueueBinding.php                 # Named consumer config (v0.5+)
│   ├── QueueBindingRegistry.php         # name => QueueBinding (v0.5+)
│   ├── ConsumerConfig.php               # Immutable DTO
│   └── Consumer.php                     # Long-poll SQS worker (Fargate runtime)
│
└── Providers/
    └── AwsKitServiceProvider.php        # Laravel service provider
```

## Package Dependencies

| Package | Purpose |
|---|---|
| `aws/aws-sdk-php ^3.0` | AWS SDK |
| `bref/bref ^3.0` | Lambda handler (optional — only if you use the Lambda runtime) |
| `illuminate/{console, contracts, http, support} ^11\|^12\|^13` | Laravel primitives |
| `veritypos/contracts ^6.9` | Shared DTOs (`DomainEventData`, `EventType`, etc.) |

## Consumer Integration

Consumer services depend on `veritypos/aws-kit` as a Composer VCS dependency and bind their own `Dispatcher` in `AppServiceProvider`:

```php
$this->app->singleton(Dispatcher::class, function () {
    return new PrefixDispatcher([
        'user.'   => app(UserEventHandler::class),
        'tenant.' => app(TenantEventHandler::class),
        'device.' => app(DeviceEventHandler::class),
    ]);
});
```

The runtime adapter (Lambda or SQS worker) calls `Dispatcher::dispatch()` and the right handler fires. The service doesn't know — and doesn't need to know — which runtime triggered the call.

### Multiple SQS topologies per service (v0.5+)

A single service can consume from N queues with different envelope shapes and different routing strategies. Each queue is declared as a `QueueBinding` in a service provider, and supervisord runs one `aws-kit:sqs-consume --binding=NAME` process per binding. See [docs/api/sqs-bindings.md](api/sqs-bindings.md) for the full pattern, including how commerce-service runs two consumers (one for inter-service EventBridge-fed SQS, one for sync-engine-fed device-bus SQS) in the same Fargate task.

See [docs/api/installation.md](api/installation.md) for the full setup.
