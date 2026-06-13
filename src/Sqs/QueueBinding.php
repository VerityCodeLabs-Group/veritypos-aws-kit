<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Sqs;

use VerityPOS\AwsKit\Contracts\Dispatcher as DispatcherContract;
use VerityPOS\AwsKit\Contracts\EnvelopeParser;

/**
 * Read-only configuration for a named SQS consumer binding.
 *
 * A service that consumes from N queues registers N bindings. Each
 * binding tells the kit's `SqsConsumeCommand` (or per-binding
 * subcommand) which queue to long-poll, which envelope parser to
 * use for messages from that queue, and which Dispatcher to route
 * parsed events into.
 *
 * Why per-binding parser + dispatcher?
 *
 *   The kit's default `SqsEnvelopeParser` assumes an EventBridge-fed
 *   queue (single event per message, `event_type` attribute or
 *   `detail-type` body field). Commerce's device bus carries
 *   `SyncMessageData` envelopes (one tenant + N events per message)
 *   — completely different shape. Each binding declares which
 *   parser to use, so the same kit can serve both topologies.
 *
 *   Similarly, the kit's default `Dispatcher` (bound as
 *   `Contracts\Dispatcher`) routes via prefix matching — perfect for
 *   inter-service events. The device bus wants a dispatcher that
 *   looks up the handler by full event-type string and dispatches
 *   each event in the batched envelope individually. Each binding
 *   declares which dispatcher to use.
 *
 * Example (commerce):
 *
 *   $registry->register(new QueueBinding(
 *       name: 'device-events',
 *       queueUrl: config('aws-kit.sqs.device_events.queue_url'),
 *       envelopeParserClass: SyncMessageDataEnvelopeParser::class,
 *       dispatcherClass:    DeviceEventDispatcher::class,
 *   ));
 */
final class QueueBinding
{
    /**
     * @param  class-string<EnvelopeParser>  $envelopeParserClass
     * @param  class-string<DispatcherContract>|null  $dispatcherClass
     */
    public function __construct(
        public readonly string $name,
        public readonly string $queueUrl,
        public readonly string $region = 'ap-southeast-1',
        public readonly ?string $endpoint = null,
        public readonly int $maxMessages = 10,
        public readonly int $waitTimeSeconds = 20,
        public readonly int $visibilityTimeout = 30,
        public readonly bool $acknowledgeOnSuccess = true,
        public readonly string $envelopeParserClass = SqsEnvelopeParser::class,
        public readonly ?string $dispatcherClass = null,
    ) {
        if (! is_subclass_of($envelopeParserClass, EnvelopeParser::class)) {
            throw new \InvalidArgumentException(sprintf(
                'QueueBinding %s: envelopeParserClass %s must implement %s',
                $name,
                $envelopeParserClass,
                EnvelopeParser::class,
            ));
        }

        if ($dispatcherClass !== null && ! is_subclass_of($dispatcherClass, DispatcherContract::class)) {
            throw new \InvalidArgumentException(sprintf(
                'QueueBinding %s: dispatcherClass %s must implement %s',
                $name,
                $dispatcherClass,
                DispatcherContract::class,
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $envelopeParserClass = (string) ($data['envelope_parser_class'] ?? SqsEnvelopeParser::class);
        if (! is_subclass_of($envelopeParserClass, EnvelopeParser::class)) {
            throw new \InvalidArgumentException(sprintf(
                'envelope_parser_class %s must implement %s',
                $envelopeParserClass,
                EnvelopeParser::class,
            ));
        }

        $dispatcherClass = isset($data['dispatcher_class']) ? (string) $data['dispatcher_class'] : null;
        if ($dispatcherClass !== null && ! is_subclass_of($dispatcherClass, DispatcherContract::class)) {
            throw new \InvalidArgumentException(sprintf(
                'dispatcher_class %s must implement %s',
                $dispatcherClass,
                DispatcherContract::class,
            ));
        }

        return new self(
            name: (string) ($data['name'] ?? throw new \InvalidArgumentException('QueueBinding requires a name')),
            queueUrl: (string) ($data['queue_url'] ?? throw new \InvalidArgumentException('QueueBinding requires queue_url')),
            region: (string) ($data['region'] ?? 'ap-southeast-1'),
            endpoint: isset($data['endpoint']) ? (string) $data['endpoint'] : null,
            maxMessages: (int) ($data['max_messages'] ?? 10),
            waitTimeSeconds: (int) ($data['wait_time_seconds'] ?? 20),
            visibilityTimeout: (int) ($data['visibility_timeout'] ?? 30),
            acknowledgeOnSuccess: (bool) ($data['acknowledge_on_success'] ?? true),
            envelopeParserClass: $envelopeParserClass,
            dispatcherClass: $dispatcherClass,
        );
    }
}
