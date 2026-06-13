<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Sqs;

/**
 * Read-only configuration for a named SQS publisher.
 *
 * Service providers register one binding per logical queue the
 * service publishes to (e.g. `sync-events`, `event-results`,
 * `domain-events`). The binding is the **declaration** of intent;
 * the actual `SqsPublisher` is built by `SqsPublisherFactory` and
 * cached on the registry.
 *
 * A service with multiple outbound queues (commerce publishes to
 * both `sync-events` and `event-results`) registers multiple
 * bindings. A service with one queue (e.g. the platform inter-service
 * consumer) registers one.
 *
 * The name is the lookup key for `SqsPublisherRegistry::get($name)`.
 * It also appears in logs and the `--binding=` flag of the
 * `aws-kit:sqs-consume` command, so make it human-readable.
 */
final class SqsPublisherBinding
{
    public function __construct(
        public readonly string $name,
        public readonly string $queueUrl,
        public readonly string $region = 'ap-southeast-1',
        public readonly ?string $endpoint = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? throw new \InvalidArgumentException('SqsPublisherBinding requires a name')),
            queueUrl: (string) ($data['queue_url'] ?? throw new \InvalidArgumentException('SqsPublisherBinding requires queue_url')),
            region: (string) ($data['region'] ?? 'ap-southeast-1'),
            endpoint: isset($data['endpoint']) ? (string) $data['endpoint'] : null,
        );
    }
}
