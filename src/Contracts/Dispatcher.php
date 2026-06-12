<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Contracts;

/**
 * Routes an Envelope to a Handler.
 *
 * Each runtime adapter (Lambda, SQS consumer, CLI) calls this to
 * dispatch the unwrapped payload to the right handler. Implementations
 * may use a prefix match, an exact match, or a class map.
 */
interface Dispatcher
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws \Throwable if no handler matches
     */
    public function dispatch(string $eventType, array $payload): void;
}
