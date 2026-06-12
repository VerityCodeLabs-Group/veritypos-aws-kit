<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\EventBridge;

use Aws\EventBridge\EventBridgeClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Publishes domain events to an EventBridge bus.
 *
 * Usage from a publishing service:
 *
 *   app(Dispatcher::class)->dispatch(
 *       new TenantCreated($tenant),
 *       fn (string $type, array $payload) => $publisher->publish(
 *           source: ServiceSource::PLATFORM,
 *           eventType: $type,
 *           detail: $payload,
 *       ),
 *   );
 */
final class EventBridgePublisher
{
    private ?EventBridgeClient $client = null;

    public function __construct(
        private readonly string $eventBusName,
        private readonly string $sourcePrefix,
        private readonly string $region = 'ap-southeast-1',
        private readonly ?string $endpoint = null,
    ) {}

    public function isConfigured(): bool
    {
        return $this->eventBusName !== '';
    }

    /**
     * Publish a domain event.
     *
     * @param  string  $source  the service source (e.g. "auth", "platform")
     * @param  string  $eventType  the event type (e.g. "user.created")
     * @param  array<string, mixed>  $detail
     *
     * @throws Throwable on AWS SDK error
     */
    public function publish(string $source, string $eventType, array $detail): void
    {
        $fullSource = "{$this->sourcePrefix}.{$source}";

        try {
            $this->getClient()->putEvents([
                'Entries' => [
                    [
                        'EventBusName' => $this->eventBusName,
                        'Source' => $fullSource,
                        'DetailType' => $eventType,
                        'Detail' => json_encode($detail, JSON_THROW_ON_ERROR),
                    ],
                ],
            ]);

            Log::debug('[AwsKit\EventBridgePublisher] Event published', [
                'event_bus' => $this->eventBusName,
                'source' => $fullSource,
                'detail_type' => $eventType,
            ]);
        } catch (Throwable $e) {
            Log::error('[AwsKit\EventBridgePublisher] Failed to publish event', [
                'event_bus' => $this->eventBusName,
                'source' => $fullSource,
                'detail_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function getClient(): EventBridgeClient
    {
        return $this->client ??= new EventBridgeClient([
            'region' => $this->region,
            'version' => 'latest',
            ...($this->endpoint !== null ? [
                'endpoint' => $this->endpoint,
                'credentials' => ['key' => 'test', 'secret' => 'test'],
            ] : []),
        ]);
    }
}
