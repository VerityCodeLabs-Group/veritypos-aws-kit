<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Contracts;

/**
 * A generic envelope wrapping an AWS-delivered event.
 *
 * AWS delivers events in protocol-specific shapes (EventBridge detail,
 * SQS body, SNS message, Lambda payload). After unwrapping the protocol
 * envelope, every shape reduces to a single domain-level DTO.
 *
 * Implementations:
 *   - EventBridgeEnvelope: wraps the `detail` field
 *   - SqsEnvelope:        unwraps the `Body` field
 *   - SnsEnvelope:        unwraps the `Message` field
 */
interface Envelope
{
    /**
     * The event source identifier (e.g. "veritypos.auth" for EventBridge,
     * the queue ARN for SQS, the topic ARN for SNS).
     */
    public function source(): string;

    /**
     * The event type / detail-type / routing key.
     */
    public function eventType(): string;

    /**
     * The unwrapped domain payload, ready to dispatch to a handler.
     *
     * @return array<string, mixed>
     */
    public function payload(): array;
}
