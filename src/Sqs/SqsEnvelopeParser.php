<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Sqs;

use VerityPOS\AwsKit\Contracts\Envelope;

/**
 * Unwraps a raw SQS message array into an Envelope.
 *
 * The SQS body is a JSON string that we parse into an array. The
 * `event_type` is sourced from the message attributes (SQS convention
 * — see the `veritypos-aws-kit` config docs) with a fallback to a
 * `detail-type` field in the body for parity with the EventBridge
 * envelope shape.
 */
final class SqsEnvelopeParser
{
    /**
     * @param  array<string, mixed>  $rawMessage  the full SQS message array
     * @param  string  $source  queue ARN or URL (for routing)
     */
    public function parse(array $rawMessage, string $source): Envelope
    {
        $body = $rawMessage['Body'] ?? '';
        $attributes = $rawMessage['MessageAttributes'] ?? [];

        if (! is_string($body)) {
            throw new \InvalidArgumentException('SQS message Body must be a string');
        }

        $payload = $body === '' ? [] : $this->decodeBody($body);

        $eventType = $this->extractEventType($payload, $attributes);

        return new SqsEnvelope(
            source: $source,
            eventType: $eventType,
            payload: $payload,
            attributes: $attributes,
            messageId: isset($rawMessage['MessageId']) ? (string) $rawMessage['MessageId'] : null,
            receiptHandle: isset($rawMessage['ReceiptHandle']) ? (string) $rawMessage['ReceiptHandle'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(string $body): array
    {
        $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('SQS message Body must be a JSON object');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, array{DataType: string, StringValue: string}>  $attributes
     */
    private function extractEventType(array $payload, array $attributes): string
    {
        // Preferred: message attribute (cheap to access, doesn't require
        // parsing the body)
        $attr = $attributes['event_type'] ?? null;
        if (is_array($attr)) {
            return $attr['StringValue'];
        }

        // Fallback: detail-type field in the body (parity with EventBridge)
        if (isset($payload['detail-type']) && is_string($payload['detail-type'])) {
            return $payload['detail-type'];
        }

        throw new \InvalidArgumentException(
            'SQS message missing event_type attribute and body has no detail-type field'
        );
    }
}
