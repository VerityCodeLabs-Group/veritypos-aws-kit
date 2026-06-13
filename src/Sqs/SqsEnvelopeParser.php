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

        $rawPayload = $body === '' ? [] : $this->decodeBody($body);

        // Extract the event type from the raw body first (EventBridge
        // puts `detail-type` at the envelope's top level, not under
        // `detail`), then unwrap the EventBridge envelope so the
        // dispatched payload is the domain payload, not the AWS
        // wrapper.
        $eventType = $this->extractEventType($rawPayload, $attributes);

        $payload = $this->unwrapEventBridgeEnvelope($rawPayload);

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
     * @param  array<string, mixed>  $rawPayload
     * @return array<string, mixed>
     */
    private function unwrapEventBridgeEnvelope(array $rawPayload): array
    {
        if (! $this->looksLikeEventBridgeEnvelope($rawPayload)) {
            return $rawPayload;
        }

        // When the SQS queue is fed by EventBridge (the standard
        // VerityPOS pattern: EventBridge rule → SQS target), the body
        // is the full EventBridge envelope. The actual domain payload
        // lives under `detail`. Without unwrapping, downstream
        // consumers see the EventBridge wrapper as their payload and
        // can't find domain fields like `aggregate_id` at the top
        // level. The kit's runtime-agnostic contract is that the
        // dispatched payload is the *domain* payload, so we unwrap
        // here.
        return is_array($rawPayload['detail'] ?? null) ? $rawPayload['detail'] : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function looksLikeEventBridgeEnvelope(array $payload): bool
    {
        // EventBridge envelopes always have version="0", detail-type,
        // and a detail object. Match on the three required fields to
        // avoid misidentifying a domain payload that happens to have
        // a "detail" key.
        return ($payload['version'] ?? null) === '0'
            && is_string($payload['detail-type'] ?? null)
            && array_key_exists('detail', $payload);
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
