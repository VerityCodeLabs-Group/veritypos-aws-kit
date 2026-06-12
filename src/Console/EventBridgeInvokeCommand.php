<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;
use VerityPOS\AwsKit\Contracts\Dispatcher;
use VerityPOS\AwsKit\EventBridge\EventBridgeEnvelopeParser;

/**
 * Simulates an EventBridge event invocation against the local dispatcher.
 *
 * Constructs an EventBridge envelope from CLI args, then runs the
 * dispatcher (same code path the Lambda would). Useful for testing
 * the handler logic without standing up LocalStack.
 *
 * Usage:
 *   php artisan aws-kit:event-bridge-invoke --event-type=user.created --payload='{"id":"..."}'
 *   php artisan aws-kit:event-bridge-invoke --event-file=event.json
 */
final class EventBridgeInvokeCommand extends Command
{
    /** @var string */
    protected $signature = 'aws-kit:event-bridge-invoke
        {--event-type= : The domain event type (e.g. user.created)}
        {--source=auth : The service source (e.g. auth, platform)}
        {--payload= : JSON string of the event payload}
        {--event-file= : Path to a JSON file containing the full EventBridge envelope}';

    /** @var string */
    protected $description = 'Simulate an EventBridge event invocation against the local dispatcher';

    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly EventBridgeEnvelopeParser $parser,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $envelope = $this->resolveEnvelope();

        if ($envelope === null) {
            return self::FAILURE;
        }

        $this->info('Invoking dispatcher with event:');
        $this->line((string) json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        try {
            $parsed = $this->parser->fromArray($envelope);
            $this->dispatcher->dispatch($parsed->eventType(), $parsed->payload());

            $this->info('Dispatcher completed successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Dispatcher failed: {$e->getMessage()}");
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveEnvelope(): ?array
    {
        $eventFile = $this->option('event-file');

        if ($eventFile !== null) {
            if (! file_exists($eventFile)) {
                $this->error("Event file not found: {$eventFile}");

                return null;
            }

            $contents = file_get_contents($eventFile);
            if ($contents === false) {
                $this->error("Failed to read event file: {$eventFile}");

                return null;
            }

            $data = json_decode($contents, true);

            return is_array($data) ? $data : null;
        }

        $eventType = $this->option('event-type');
        if ($eventType === null) {
            $this->error('Provide --event-type or --event-file.');

            return null;
        }

        $source = (string) $this->option('source');
        $payload = [];

        $payloadOption = $this->option('payload');
        if ($payloadOption !== null) {
            $decoded = json_decode($payloadOption, true);

            if (is_array($decoded)) {
                $payload = $decoded;
            } else {
                $this->error('--payload must be valid JSON.');

                return null;
            }
        }

        return [
            'version' => '0',
            'id' => (string) Str::uuid(),
            'source' => "veritypos.{$source}",
            'detail-type' => $eventType,
            'account' => '000000000000',
            'time' => now()->toISOString(),
            'region' => 'ap-southeast-1',
            'resources' => [],
            'detail' => $payload,
        ];
    }
}
