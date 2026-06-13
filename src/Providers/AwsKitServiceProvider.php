<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Providers;

use Illuminate\Support\ServiceProvider;
use VerityPOS\AwsKit\Console\EventBridgeInvokeCommand;
use VerityPOS\AwsKit\Console\SqsConsumeCommand;
use VerityPOS\AwsKit\Contracts\Dispatcher;
use VerityPOS\AwsKit\Dispatcher\NullDispatcher;
use VerityPOS\AwsKit\EventBridge\EventBridgePublisher;
use VerityPOS\AwsKit\Sqs\QueueBindingRegistry;
use VerityPOS\AwsKit\Sqs\SqsClientFactory;
use VerityPOS\AwsKit\Sqs\SqsPublisherFactory;
use VerityPOS\AwsKit\Sqs\SqsPublisherRegistry;

/**
 * Registers the AWS Kit services into the Laravel container.
 *
 * Reads config/aws-kit.php for region, bus name, source prefix, etc.
 *
 * Container bindings registered in `register()`:
 *   - EventBridgePublisher (singleton)
 *   - SqsClientFactory (singleton)
 *   - QueueBindingRegistry (singleton)
 *   - SqsPublisherRegistry (singleton)
 *   - SqsPublisherFactory (singleton)
 *   - Dispatcher (singleton, NullDispatcher default; consumer services rebind)
 *
 * Console commands registered in `register()` (only when running
 * in the console):
 *   - EventBridgeInvokeCommand
 *   - SqsConsumeCommand (`aws-kit:sqs-consume --binding=NAME`)
 *
 * Service providers in consumer services register their queue
 * bindings + publisher bindings + dispatcher at their own boot time
 * (after the kit's SP has bound the registries as singletons).
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

        $this->app->singleton(SqsClientFactory::class);

        $this->app->singleton(QueueBindingRegistry::class);

        $this->app->singleton(SqsPublisherRegistry::class);

        $this->app->singleton(SqsPublisherFactory::class, function ($app) {
            return new SqsPublisherFactory(
                clientFactory: $app->make(SqsClientFactory::class),
            );
        });

        // If the consumer service has a Dispatcher bound, leave it alone.
        // Otherwise bind a no-op default so the package doesn't require
        // a consumer service to register one.
        if (! $this->app->bound(Dispatcher::class)) {
            $this->app->singleton(Dispatcher::class, NullDispatcher::class);
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            EventBridgeInvokeCommand::class,
            SqsConsumeCommand::class,
        ]);
    }

    /**
     * Publish the package config so the consumer service can override
     * (e.g. set a per-service event_bus_name in their own config).
     *
     * No per-binding subcommands are registered here. Supervisord uses
     * the canonical `aws-kit:sqs-consume --binding=NAME` form:
     *
     *   command=php /var/www/artisan aws-kit:sqs-consume --binding=inter-service
     *   command=php /var/www/artisan aws-kit:sqs-consume --binding=device-events
     *
     * The `SqsConsumeCommand` reads the binding from the
     * `QueueBindingRegistry` (already populated by the consumer
     * service's own service providers during their `register()`).
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../Config/aws-kit.php' => config_path('aws-kit.php'),
        ], 'aws-kit-config');
    }
}
