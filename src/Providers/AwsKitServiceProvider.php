<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Providers;

use Illuminate\Support\ServiceProvider;
use VerityPOS\AwsKit\Console\EventBridgeInvokeCommand;
use VerityPOS\AwsKit\Contracts\Dispatcher;
use VerityPOS\AwsKit\Dispatcher\NullDispatcher;
use VerityPOS\AwsKit\EventBridge\EventBridgePublisher;

/**
 * Registers the AWS Kit services into the Laravel container.
 *
 * Reads config/aws-kit.php for region, bus name, source prefix, etc.
 * The consumer services are expected to register their own Dispatcher
 * implementation that routes to their domain handlers (they know
 * which event types they care about).
 */
final class AwsKitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/aws-kit.php', 'aws-kit');

        $this->app->singleton(EventBridgePublisher::class, function () {
            $config = config('aws-kit.eventbridge');

            return new EventBridgePublisher(
                eventBusName: (string) $config['event_bus_name'],
                sourcePrefix: (string) $config['source_prefix'],
                region: (string) ($config['region'] ?? 'ap-southeast-1'),
                endpoint: $config['endpoint'] ?? null,
            );
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            EventBridgeInvokeCommand::class,
        ]);
    }

    /**
     * Publish the package config so the consumer service can override
     * (e.g. set a per-service event_bus_name in their own config).
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../Config/aws-kit.php' => config_path('aws-kit.php'),
        ], 'aws-kit-config');

        // If the consumer service has a Dispatcher bound, leave it alone.
        // Otherwise bind a no-op default so the package doesn't require
        // a consumer service to register one.
        if ($this->app->bound(Dispatcher::class)) {
            return;
        }

        $this->app->singleton(Dispatcher::class, NullDispatcher::class);
    }
}
