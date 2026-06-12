<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Console;

use Illuminate\Console\Command;
use Throwable;
use VerityPOS\AwsKit\Contracts\Dispatcher;
use VerityPOS\AwsKit\Contracts\Envelope;
use VerityPOS\AwsKit\Sqs\Consumer;
use VerityPOS\AwsKit\Sqs\ConsumerConfig;
use VerityPOS\AwsKit\Sqs\SqsEnvelopeParser;

/**
 * Long-poll SQS consumer worker.
 *
 * This is the runtime adapter for Fargate supervisord. Each service
 * runs one of these per consumed queue:
 *
 *   [program:aws-kit-sqs-consume]
 *   command=php /var/www/artisan aws-kit:sqs-consume --queue=%(ENV_SQS_QUEUE_URL)s
 *
 * The worker:
 *   1. Long-polls SQS for messages
 *   2. Parses each message into an Envelope
 *   3. Calls the configured Dispatcher (which routes to the service's
 *      own event handlers)
 *   4. On success: acks the message
 *   5. On failure: leaves the message for redelivery
 *
 * SIGTERM/SIGINT stop the loop after the current message finishes,
 * so ECS can drain the task without losing in-flight work.
 */
final class SqsConsumeCommand extends Command
{
    /** @var string */
    protected $signature = 'aws-kit:sqs-consume
        {--queue= : The SQS queue URL to consume from (required)}
        {--max-messages=10 : Max messages to receive per poll (max 10)}
        {--wait-time=20 : Long-poll wait time in seconds (max 20)}
        {--visibility-timeout=30 : Visibility timeout in seconds}
        {--region= : AWS region (default: AWS_REGION env or ap-southeast-1)}
        {--endpoint= : Override endpoint (for LocalStack)}
        {--once : Process one batch and exit (used for tests)}';

    /** @var string */
    protected $description = 'Long-poll SQS consumer worker (Fargate supervisord runtime)';

    public function __construct(
        private readonly Consumer $consumer,
        private readonly Dispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $queueUrl = (string) $this->option('queue');
        if ($queueUrl === '') {
            $this->error('--queue is required.');

            return self::FAILURE;
        }

        $config = new ConsumerConfig(
            queueUrl: $queueUrl,
            maxMessages: (int) $this->option('max-messages'),
            waitTimeSeconds: (int) $this->option('wait-time'),
            visibilityTimeout: (int) $this->option('visibility-timeout'),
            region: (string) ($this->option('region') ?: config('aws-kit.aws.region', 'ap-southeast-1')),
            endpoint: $this->option('endpoint') ?: config('aws-kit.aws.endpoint'),
        );

        $this->info("Consuming from {$config->queueUrl}");
        $this->info("  max-messages={$config->maxMessages} wait={$config->waitTimeSeconds}s visibility={$config->visibilityTimeout}s");

        $this->consumer->registerSignalHandlers();

        // One batch mode is for tests / debugging
        if ($this->option('once')) {
            $this->consumer->consume(fn ($envelope) => $this->dispatch($envelope));

            return self::SUCCESS;
        }

        $this->consumer->consume(function ($envelope): void {
            $this->dispatch($envelope);
        });

        return self::SUCCESS;
    }

    private function dispatch(Envelope $envelope): void
    {
        $this->dispatcher->dispatch($envelope->eventType(), $envelope->payload());
    }
}
