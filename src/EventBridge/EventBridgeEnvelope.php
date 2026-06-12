<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\EventBridge;

use VerityPOS\AwsKit\Contracts\Envelope;

/**
 * The unwrapped EventBridge payload, ready to dispatch.
 *
 * EventBridge delivers events in this shape:
 *
 *   {
 *     "version": "0",
 *     "id": "...",
 *     "source": "veritypos.auth",
 *     "detail-type": "user.created",
 *     "detail": { ... domain payload ... }
 *   }
 *
 * We extract `source` and `detail-type` for routing, and unwrap `detail`
 * to get the domain payload.
 */
final class EventBridgeEnvelope implements Envelope
{
    /**
     * @param  array<string, mixed>  $detail
     */
    public function __construct(
        private readonly string $source,
        private readonly string $detailType,
        private readonly array $detail,
    ) {}

    public function source(): string
    {
        return $this->source;
    }

    public function eventType(): string
    {
        return $this->detailType;
    }

    public function payload(): array
    {
        return $this->detail;
    }
}
