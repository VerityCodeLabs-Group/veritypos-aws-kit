<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Sqs;

use VerityPOS\AwsKit\Contracts\Envelope;

/**
 * The unwrapped SQS message, ready to dispatch.
 *
 * SQS delivers messages in this shape:
 *
 *   {
 *     "MessageId": "...",
 *     "ReceiptHandle": "...",
 *     "Body": "{ ... domain payload ... }",  // string (JSON)
 *     "MessageAttributes": {
 *       "event_type": { "DataType": "String", "StringValue": "user.created" }
 *     }
 *   }
 *
 * The SqsEnvelope unwraps `Body` (parsed as array) into `payload()`,
 * and exposes the message attributes (including the routing key) via
 * the `eventType()` method. The `source()` method returns the queue
 * ARN (or the queue URL in LocalStack) — useful for logging.
 */
final class SqsEnvelope implements Envelope
{
    /**
     * @param  array<string, mixed>                              $payload
     * @param  array<string, array{DataType: string, StringValue: string}>  $attributes
     * @param  string|null                                        $messageId
     * @param  string|null                                        $receiptHandle
     */
    public function __construct(
        private readonly string $source,
        private readonly string $eventType,
        private readonly array $payload,
        private readonly array $attributes = [],
        private readonly ?string $messageId = null,
        private readonly ?string $receiptHandle = null,
    ) {}

    public function source(): string
    {
        return $this->source;
    }

    public function eventType(): string
    {
        return $this->eventType;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * @return array<string, array{DataType: string, StringValue: string}>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function messageId(): ?string
    {
        return $this->messageId;
    }

    public function receiptHandle(): ?string
    {
        return $this->receiptHandle;
    }
}
