<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Dispatcher;

use VerityPOS\AwsKit\Contracts\Dispatcher as DispatcherContract;

/**
 * A no-op dispatcher. Used as the default when a consumer service
 * hasn't registered its own Dispatcher binding — so the package
 * can be installed in a service that doesn't consume any events
 * without forcing the service to bind a Dispatcher just to satisfy
 * the contract.
 *
 * Events routed to this dispatcher are silently dropped. The consumer
 * service's first action after installing the package should be to
 * bind its own Dispatcher (typically a PrefixDispatcher).
 */
final class NullDispatcher implements DispatcherContract
{
    public function dispatch(string $eventType, array $payload): void
    {
        // Intentional no-op. See class docblock.
    }
}
