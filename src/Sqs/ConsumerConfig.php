<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Sqs;

/**
 * Immutable configuration for a long-poll SQS consumer.
 *
 * Wrapping the consumer params in a DTO avoids primitive obsession
 * in the supervisord worker + makes the contract unit-testable
 * without instantiating a SqsClient.
 */
final class ConsumerConfig
{
    public function __construct(
        public readonly string $queueUrl = '',
        public readonly int $maxMessages = 10,
        public readonly int $waitTimeSeconds = 20,
        public readonly int $visibilityTimeout = 30,
        public readonly string $region = 'ap-southeast-1',
        public readonly ?string $endpoint = null,
        public readonly bool $acknowledgeOnSuccess = true,
    ) {}

    public static function forQueue(string $queueUrl): self
    {
        return new self(queueUrl: $queueUrl);
    }

    public function withMaxMessages(int $maxMessages): self
    {
        return new self(
            queueUrl: $this->queueUrl,
            maxMessages: $maxMessages,
            waitTimeSeconds: $this->waitTimeSeconds,
            visibilityTimeout: $this->visibilityTimeout,
            region: $this->region,
            endpoint: $this->endpoint,
            acknowledgeOnSuccess: $this->acknowledgeOnSuccess,
        );
    }

    public function withWaitTimeSeconds(int $waitTimeSeconds): self
    {
        return new self(
            queueUrl: $this->queueUrl,
            maxMessages: $this->maxMessages,
            waitTimeSeconds: $waitTimeSeconds,
            visibilityTimeout: $this->visibilityTimeout,
            region: $this->region,
            endpoint: $this->endpoint,
            acknowledgeOnSuccess: $this->acknowledgeOnSuccess,
        );
    }
}
