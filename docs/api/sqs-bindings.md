# SQS Queue Bindings

The kit's SQS consumer is **binding-driven**: each service registers one or more `QueueBinding` configs, and supervisord invokes `aws-kit:sqs-consume --binding=NAME` per binding. The same kit can serve multiple SQS topologies simultaneously — EventBridge-fed inter-service SQS, sync-engine-fed device-bus SQS, future event-source-fed SQS, etc.

## Why bindings?

Pre-0.5 the kit had one `SqsConsumeCommand` and one `aws-kit.sqs.consumer.queue_url` config key. A service with N inbound queues had to either:
- Run N copies of the same `SqsConsumeCommand` with different `--queue=` args (boilerplate, no way to express per-queue parser/dispatcher differences)
- Or extend `SqsConsumeCommand` in a service-side subclass per queue (boilerplate, kit bypass)

The binding pattern is the same fix Laravel uses for config providers: declare your queues as data, let the kit's runtime walk the registry.

## What is a binding?

A `VerityPOS\AwsKit\Sqs\QueueBinding` is a read-only DTO:

```php
final class QueueBinding
{
    public function __construct(
        public readonly string $name,                  // unique identifier
        public readonly string $queueUrl,
        public readonly string $region = 'ap-southeast-1',
        public readonly ?string $endpoint = null,
        public readonly int $maxMessages = 10,
        public readonly int $waitTimeSeconds = 20,
        public readonly int $visibilityTimeout = 30,
        public readonly bool $acknowledgeOnSuccess = true,
        public readonly string $envelopeParserClass = SqsEnvelopeParser::class,
        public readonly ?string $dispatcherClass = null,
    ) {}
}
```

`name` is the only field the consumer worker reads at runtime. The other fields flow into the binding's `ConsumerConfig` and the command's dispatch path.

## Registering bindings

In a service provider's `register()`:

```php
use VerityPOS\AwsKit\Sqs\QueueBinding;
use VerityPOS\AwsKit\Sqs\QueueBindingRegistry;

$this->app->make(QueueBindingRegistry::class)->register(
    new QueueBinding(
        name: 'inter-service',
        queueUrl: env('SQS_INTER_SERVICE_QUEUE_URL'),
    ),
);
```

The `QueueBindingRegistry` is bound as a singleton by the kit's `AwsKitServiceProvider`. Bindings persist for the lifetime of the Laravel application process (one process per Fargate task).

## How the command resolves a binding

`aws-kit:sqs-consume` (binding-driven) resolves the binding as follows:

1. `--binding=NAME` is passed → look up by name
2. `--binding` is omitted AND exactly one binding is registered → auto-pick
3. `--binding` is omitted AND multiple bindings are registered → error, ask for explicit `--binding`
4. Zero bindings AND `config('aws-kit.sqs.consumer.queue_url')` is set → legacy fallback (kit's default parser + global dispatcher)
5. Zero bindings AND no legacy config → error

## Envelope parsers (per binding)

The kit ships a default `SqsEnvelopeParser` (the `EnvelopeParser` contract implementation) that handles the EventBridge-fed SQS shape: single event per message, `event_type` attribute or `detail-type` body field. Services with different envelope shapes (e.g. commerce's `SyncMessageData` multi-tenant batched envelope) implement `VerityPOS\AwsKit\Contracts\EnvelopeParser` and declare their class on the `QueueBinding`:

```php
use App\Messaging\SyncMessageDataEnvelopeParser;

$this->app->make(QueueBindingRegistry::class)->register(
    new QueueBinding(
        name: 'device-events',
        queueUrl: env('SQS_DEVICE_EVENTS_QUEUE_URL'),
        envelopeParserClass: SyncMessageDataEnvelopeParser::class,
    ),
);
```

Each command invocation instantiates a fresh parser (parsers are stateless, see `Contracts\EnvelopeParser`).

## Dispatchers (per binding)

A binding can declare its own `Dispatcher` class to receive parsed events. The class must implement `VerityPOS\AwsKit\Contracts\Dispatcher`. If `dispatcherClass` is null, the global `Contracts\Dispatcher` binding is used (typically `PrefixDispatcher` for inter-service prefix-routing).

```php
use App\Messaging\DeviceEventDispatcher;

$this->app->make(QueueBindingRegistry::class)->register(
    new QueueBinding(
        name: 'device-events',
        queueUrl: env('SQS_DEVICE_EVENTS_QUEUE_URL'),
        envelopeParserClass: SyncMessageDataEnvelopeParser::class,
        dispatcherClass: DeviceEventDispatcher::class,
    ),
);
```

The dispatcher receives the unwrapped `eventType` + `payload` pair from the binding's parser. If your dispatcher needs the original SQS message (e.g. for the `ReceiptHandle` to ack individually), see `VerityPOS\AwsKit\Contracts\Envelope` — extend the envelope contract to carry it.

## Supervisord pattern

One program per binding. Names follow `aws-kit-sqs-consume-<binding-name>` for grep-ability in CloudWatch logs:

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

## Publishers (symmetric)

For outbound queues, use the symmetric `SqsPublisherBinding` + `SqsPublisherFactory` + `SqsPublisherRegistry`. A service with M outbound queues (e.g. commerce publishes to both `sync-events` and `event-results`) registers M publisher bindings and resolves them by name at the call site:

```php
use VerityPOS\AwsKit\Sqs\SqsPublisherFactory;
use VerityPOS\AwsKit\Sqs\SqsPublisherRegistry;
use VerityPOS\AwsKit\Sqs\SqsPublisherBinding;

// At register() time:
$this->app->make(SqsPublisherRegistry::class)->register(
    new SqsPublisherBinding(
        name: 'sync-events',
        queueUrl: env('SQS_SYNC_QUEUE_URL'),
    ),
);

// At the call site (e.g. a SyncEvent::publish() override):
$publisher = $this->app->make(SqsPublisherFactory::class)
    ->make($this->app->make(SqsPublisherRegistry::class)->get('sync-events'));

$publisher->publish($body, $attrs);
```

`SqsPublisherFactory` caches the `SqsPublisher` instance per binding name, so subsequent calls reuse the same client (one TCP pool per Fargate task).

## Worked example: commerce-service

The `veritypos-commerce-service` is the canonical multi-binding consumer. Its `SyncServiceProvider` registers:

- **`device-events` binding** — sync-engine-fed, multi-tenant batched envelope, custom `SyncMessageDataEnvelopeParser` + custom `DeviceEventDispatcher` (resolves the batched envelope to per-event dispatch).
- **`inter-service` binding** — EventBridge-fed, kit's default `SqsEnvelopeParser` + the global `PrefixDispatcher` (which routes `tenant.*` to commerce's `DomainEventHandler` classes via the existing `MessagingServiceProvider`).
- **`sync-events` publisher** — outbound to sync-engine (replaces sync-sdk's `SyncEvent::publish()` SQS call).
- **`event-results` publisher** — outbound sync-engine acceptance/rejection results (replaces sync-sdk's `EventResultPublisher`).

Supervisord runs 2 consumer processes (one per binding) + 1 nginx + 1 php-fpm + 2 queue workers = 5 long-running processes in the Fargate task.
