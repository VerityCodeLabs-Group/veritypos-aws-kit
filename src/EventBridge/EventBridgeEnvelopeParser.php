<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\EventBridge;

use VerityPOS\AwsKit\Contracts\Envelope;

/**
 * Unwraps an EventBridge JSON payload into an Envelope.
 *
 * Used by:
 *   - Lambda handler (Bref delivers the event as JSON)
 *   - Local CLI simulator
 *   - Tests (construct from a known envelope)
 */
final class EventBridgeEnvelopeParser
{
    public function parse(string $json): Envelope
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new \InvalidArgumentException('EventBridge envelope must be a JSON object');
        }

        return $this->fromArray($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function fromArray(array $data): Envelope
    {
        $source = (string) ($data['source'] ?? '');
        $detailType = (string) ($data['detail-type'] ?? '');
        $detail = $data['detail'] ?? [];

        if (! is_array($detail)) {
            throw new \InvalidArgumentException('EventBridge envelope `detail` must be an array');
        }

        if ($source === '' || $detailType === '') {
            throw new \InvalidArgumentException('EventBridge envelope missing source or detail-type');
        }

        return new EventBridgeEnvelope($source, $detailType, $detail);
    }
}
