<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Contracts;

/**
 * Unwraps a protocol-specific raw message into a runtime-agnostic
 * `Envelope`.
 *
 * The kit ships a default `SqsEnvelopeParser` that handles the
 * EventBridge-fed SQS shape (single event per message, `event_type`
 * attribute or `detail-type` body field). Services with different
 * envelope shapes (e.g. commerce's `SyncMessageData` multi-tenant
 * batched envelope on the device bus) implement this contract and
 * declare their parser on the corresponding `QueueBinding`.
 *
 * Parsers are **stateful per-binding** (not singleton): each
 * `SqsConsumeCommand` invocation instantiates the parser the binding
 * declares. Parsers should be cheap to construct (no I/O in the
 * constructor).
 */
interface EnvelopeParser
{
    /**
     * @param  array<string, mixed>  $rawMessage  the full SQS message array
     *                                            (Body, MessageAttributes, MessageId, ReceiptHandle, ...)
     * @param  string  $source  queue ARN or URL (for routing into the Envelope)
     */
    public function parse(array $rawMessage, string $source): Envelope;
}
