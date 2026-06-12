<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Sqs;

use Aws\Sqs\SqsClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Publishes messages to an SQS queue.
 *
 * Supports both single-message (sendMessage) and batch (sendMessageBatch)
 * publishing. Batch is up to 10x more efficient and the preferred
 * path for high-throughput publishers (e.g. the sync engine flushing
 * 100s of device events). Callers are responsible for chunking to
 * ≤10 entries per batch — SQS hard-rejects larger batches.
 */
final class SqsPublisher
{
    private ?SqsClient $client = null;

    public function __construct(
        private readonly SqsClientFactory $clientFactory,
        private readonly string $queueUrl,
        private readonly string $region = 'ap-southeast-1',
        private readonly ?string $endpoint = null,
    ) {}

    public function isConfigured(): bool
    {
        return $this->queueUrl !== '';
    }

    /**
     * Publish a single message.
     *
     * @param  array<string, array{DataType: string, StringValue: string}>  $attributes
     *
     * @throws Throwable
     */
    public function publish(string $messageBody, array $attributes = []): void
    {
        $params = [
            'QueueUrl' => $this->queueUrl,
            'MessageBody' => $messageBody,
        ];

        if ($attributes !== []) {
            $params['MessageAttributes'] = $attributes;
        }

        try {
            $this->getClient()->sendMessage($params);

            Log::debug('[AwsKit\SqsPublisher] Message published', [
                'queue_url' => $this->queueUrl,
            ]);
        } catch (Throwable $e) {
            Log::error('[AwsKit\SqsPublisher] Failed to publish message', [
                'queue_url' => $this->queueUrl,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Publish a batch of messages (max 10 per call — SQS hard limit).
     *
     * @param  list<array{messageBody: string, attributes?: array<string, array{DataType: string, StringValue: string}>}>  $entries
     *
     * @throws Throwable
     */
    public function publishBatch(array $entries): void
    {
        if ($entries === []) {
            return;
        }

        if (count($entries) > 10) {
            throw new \InvalidArgumentException('SQS sendMessageBatch accepts at most 10 entries per call');
        }

        $batchEntries = [];
        foreach ($entries as $index => $entry) {
            $batchEntry = [
                'Id' => (string) $index,
                'MessageBody' => $entry['messageBody'],
            ];

            if (! empty($entry['attributes'])) {
                $batchEntry['MessageAttributes'] = $entry['attributes'];
            }

            $batchEntries[] = $batchEntry;
        }

        $params = [
            'QueueUrl' => $this->queueUrl,
            'Entries' => $batchEntries,
        ];

        try {
            $result = $this->getClient()->sendMessageBatch($params);

            $failed = $result->get('Failed') ?? [];
            $successful = $result->get('Successful') ?? [];

            if ($failed !== []) {
                Log::warning('[AwsKit\SqsPublisher] Batch had failed entries', [
                    'queue_url' => $this->queueUrl,
                    'failed_count' => count($failed),
                    'failed' => $failed,
                ]);
            }

            Log::debug('[AwsKit\SqsPublisher] Batch published', [
                'queue_url' => $this->queueUrl,
                'entry_count' => count($entries),
                'success_count' => count($successful),
                'failed_count' => count($failed),
            ]);
        } catch (Throwable $e) {
            Log::error('[AwsKit\SqsPublisher] Failed to publish batch', [
                'queue_url' => $this->queueUrl,
                'entry_count' => count($entries),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function getClient(): SqsClient
    {
        return $this->client ??= $this->clientFactory->create(
            queueUrl: $this->queueUrl,
            region: $this->region,
            endpoint: $this->endpoint,
        );
    }
}
