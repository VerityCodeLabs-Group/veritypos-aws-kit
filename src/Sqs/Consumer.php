<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Sqs;

use Aws\Sqs\SqsClient;
use Illuminate\Support\Facades\Log;
use Throwable;
use VerityPOS\AwsKit\Contracts\Consumer as ConsumerContract;
use VerityPOS\AwsKit\Contracts\Envelope;

/**
 * Long-poll SQS consumer.
 *
 * Loops over ReceiveMessage (with WaitTimeSeconds for cost-efficient
 * long-polling), invokes the configured handler for each message, and:
 *
 *   - On success → calls deleteMessage to acknowledge (the message is
 *     removed from the queue, even if it's later redelivered it's gone)
 *   - On exception → leaves the message alone. The visibility timeout
 *     will eventually expire and SQS will redeliver. After N retries
 *     (set on the queue), the message goes to the DLQ.
 *
 * The consumer is the runtime adapter for the Fargate supervisord
 * process (no Lambda, no cold start). One `aws-kit:sqs-consume`
 * process per Fargate task per consumed queue.
 */
final class Consumer implements ConsumerContract
{
    private ?SqsClient $client = null;

    private bool $shouldStop = false;

    public function __construct(
        private readonly SqsClientFactory $clientFactory,
        private readonly ConsumerConfig $config,
    ) {}

    public function isConfigured(): bool
    {
        return $this->config->queueUrl !== '';
    }

    /**
     * Block and consume messages.
     *
     * @param  callable(Envelope): void  $handler
     */
    public function consume(callable $handler): void
    {
        if (! $this->isConfigured()) {
            throw new \LogicException('Consumer is not configured (queueUrl is empty)');
        }

        $this->shouldStop = false;

        do {
            $this->pollOnce($handler);
        } while (! $this->shouldStop);
    }

    /**
     * Run exactly one poll and return. Used by the --once flag on
     * the SqsConsumeCommand for tests + one-shot debugging.
     *
     * @param  callable(Envelope): void  $handler
     */
    public function consumeOnce(callable $handler): void
    {
        if (! $this->isConfigured()) {
            throw new \LogicException('Consumer is not configured (queueUrl is empty)');
        }

        $this->pollOnce($handler);
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }

    /**
     * Register SIGTERM + SIGINT handlers that call stop() after the
     * current message finishes. The supervisord container sends
     * SIGTERM during a graceful drain (ECS deploy, kubernetes pod
     * shutdown, etc.) — we want to finish the in-flight message
     * before exiting.
     */
    public function registerSignalHandlers(): void
    {
        if (! extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn () => $this->stop());
        pcntl_signal(SIGINT, fn () => $this->stop());
    }

    /**
     * @param  callable(Envelope): void  $handler
     */
    private function pollOnce(callable $handler): void
    {
        $result = $this->getClient()->receiveMessage([
            'QueueUrl' => $this->config->queueUrl,
            'MaxNumberOfMessages' => min($this->config->maxMessages, 10),
            'WaitTimeSeconds' => $this->config->waitTimeSeconds,
            'VisibilityTimeout' => $this->config->visibilityTimeout,
            'MessageAttributeNames' => ['All'],
        ]);

        /** @var array<int, array<string, mixed>> $messages */
        $messages = $result->get('Messages') ?? [];

        foreach ($messages as $message) {
            $this->processMessage($message, $handler);
        }

        if ($messages === []) {
            // Throttle empty polls to avoid hammering SQS during idle periods.
            // The WaitTimeSeconds on ReceiveMessage already provides backpressure
            // server-side, so 100ms is a sanity floor.
            usleep(100_000);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  callable(Envelope): void  $handler
     */
    private function processMessage(array $message, callable $handler): void
    {
        $parser = new SqsEnvelopeParser;
        $messageId = (string) ($message['MessageId'] ?? 'unknown');

        try {
            $envelope = $parser->parse($message, $this->config->queueUrl);
        } catch (Throwable $e) {
            // The message body is malformed (not JSON, missing event_type,
            // etc.). We can't dispatch it. Log and acknowledge so it doesn't
            // loop forever. A proper fix would be a "poison message" DLQ.
            Log::error('[AwsKit\Sqs\Consumer] Failed to parse message; acknowledging to drop', [
                'queue_url' => $this->config->queueUrl,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            $this->delete($message);

            return;
        }

        try {
            $handler($envelope);

            if ($this->config->acknowledgeOnSuccess) {
                $this->delete($message);
            }
        } catch (Throwable $e) {
            // Leave the message in the queue (visibility timeout will
            // expire and SQS will redeliver). After N retries, it goes
            // to the DLQ. We do NOT delete on failure.
            Log::error('[AwsKit\Sqs\Consumer] Handler failed; leaving message for redelivery', [
                'queue_url' => $this->config->queueUrl,
                'message_id' => $messageId,
                'event_type' => $envelope->eventType(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function delete(array $message): void
    {
        $receiptHandle = $message['ReceiptHandle'] ?? null;
        if (! is_string($receiptHandle) || $receiptHandle === '') {
            return;
        }

        try {
            $this->getClient()->deleteMessage([
                'QueueUrl' => $this->config->queueUrl,
                'ReceiptHandle' => $receiptHandle,
            ]);
        } catch (Throwable $e) {
            Log::warning('[AwsKit\Sqs\Consumer] Failed to delete message', [
                'queue_url' => $this->config->queueUrl,
                'message_id' => $message['MessageId'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getClient(): SqsClient
    {
        return $this->client ??= $this->clientFactory->create(
            queueUrl: $this->config->queueUrl,
            region: $this->config->region,
            endpoint: $this->config->endpoint,
        );
    }
}
