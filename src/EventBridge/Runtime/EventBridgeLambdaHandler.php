<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\EventBridge\Runtime;

use Bref\Context\Context;
use Bref\Event\EventBridge\EventBridgeEvent;
use Bref\Event\EventBridge\EventBridgeHandler;
use Illuminate\Support\Facades\Log;
use Throwable;
use VerityPOS\AwsKit\Contracts\Dispatcher;
use VerityPOS\AwsKit\EventBridge\EventBridgeEnvelopeParser;

/**
 * Lambda handler for EventBridge events.
 *
 * Wired into lambda/event-bridge.php in each service repo:
 *
 *   return $app->make(EventBridgeLambdaHandler::class);
 *
 * Bref's EventBridgeEvent automatically unwraps the envelope, giving us
 * the detail payload which contains the domain DTO. The handler:
 *   1. Parses the envelope (defense in depth — even if Bref mis-parses)
 *   2. Dispatches to the configured handler
 *   3. Logs success / failure for CloudWatch
 *
 * Throwing on failure triggers Lambda's retry policy; after retries are
 * exhausted, EventBridge routes to the configured DLQ.
 */
final class EventBridgeLambdaHandler extends EventBridgeHandler
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly EventBridgeEnvelopeParser $parser,
    ) {}

    public function handleEventBridge(EventBridgeEvent $event, Context $context): void
    {
        $detail = $event->getDetail();
        $detailType = $event->getDetailType();

        Log::info('[AwsKit\EventBridgeLambdaHandler] Received event', [
            'detail_type' => $detailType,
            'request_id' => $context->getAwsRequestId(),
        ]);

        try {
            // Defense in depth: re-parse the envelope even though Bref
            // already extracted the detail. Catches malformed envelopes
            // before they reach the dispatcher.
            $envelope = $this->parser->fromArray([
                'source' => 'veritypos.unknown',  // Bref doesn't surface the source; dispatcher will match by event_type only
                'detail-type' => $detailType,
                'detail' => $detail,
            ]);

            $this->dispatcher->dispatch($envelope->eventType(), $envelope->payload());

            Log::info('[AwsKit\EventBridgeLambdaHandler] Processed event', [
                'event_type' => $envelope->eventType(),
            ]);
        } catch (Throwable $e) {
            Log::error('[AwsKit\EventBridgeLambdaHandler] Failed to process event', [
                'detail_type' => $detailType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
