<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Contracts;

/**
 * Handles an unwrapped domain event.
 *
 * The consumer service implements this contract for each event type
 * it cares about, and the runtime adapter (Lambda / SQS worker / CLI)
 * dispatches to the right handler.
 */
interface Handler
{
    /**
     * Handle the event. Throw to signal failure (triggers retry / DLQ).
     *
     * @param  array<string, mixed>  $payload  the unwrapped domain payload
     */
    public function handle(string $eventType, array $payload): void;
}
