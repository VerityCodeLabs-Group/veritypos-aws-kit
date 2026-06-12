<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Dispatcher;

use Illuminate\Support\Facades\Log;
use VerityPOS\AwsKit\Contracts\Dispatcher as DispatcherContract;
use VerityPOS\AwsKit\Contracts\Handler;

/**
 * Prefix-based dispatcher.
 *
 * Routes an event to a handler by matching the event type against a
 * configured list of prefix patterns. The first match wins.
 *
 * Example configuration in a service's AppServiceProvider:
 *
 *   $this->app->singleton(Dispatcher::class, function () {
 *       return new PrefixDispatcher([
 *           'user.'        => app(UserEventHandler::class),
 *           'tenant.'      => app(TenantEventHandler::class),
 *           'device.'      => app(DeviceEventHandler::class),
 *           'seeding.'     => app(ProvisioningEventHandler::class),
 *       ]);
 *   });
 */
final class PrefixDispatcher implements DispatcherContract
{
    /**
     * @param  array<string, Handler>  $routes  event type prefix => handler
     */
    public function __construct(
        private readonly array $routes,
    ) {}

    public function dispatch(string $eventType, array $payload): void
    {
        foreach ($this->routes as $prefix => $handler) {
            if (str_starts_with($eventType, $prefix)) {
                $handler->handle($eventType, $payload);

                return;
            }
        }

        // No route matched — log and throw so Lambda retries / SQS
        // dead-letters. We never want to silently drop an event.
        Log::warning('[AwsKit\PrefixDispatcher] No handler matched event type', [
            'event_type' => $eventType,
        ]);

        throw new \RuntimeException("No handler registered for event type: {$eventType}");
    }
}
