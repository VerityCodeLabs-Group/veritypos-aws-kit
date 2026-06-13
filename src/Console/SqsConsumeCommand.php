<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Console;

use Illuminate\Console\Command;
use RuntimeException;
use VerityPOS\AwsKit\Contracts\Dispatcher;
use VerityPOS\AwsKit\Contracts\Envelope;
use VerityPOS\AwsKit\Sqs\Consumer;
use VerityPOS\AwsKit\Sqs\ConsumerConfig;

/**
 * Long-poll SQS consumer worker.
 *
 * This is the runtime adapter for Fargate supervisord. Each service
 * runs one of these per consumed queue:
 *
 *   [program:aws-kit-sqs-consume]
 *   command=php /var/www/artisan aws-kit:sqs-consume --max-messages=10
 *
 * The queue URL is read from `config('aws-kit.sqs.consumer.queue_url')`
 * by default, with `--queue=` as a per-invocation override. This
 * makes the command service-agnostic — no per-service wrapper needed.
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
        {--queue= : The SQS queue URL (default: config aws-kit.sqs.consumer.queue_url)}
        {--max-messages=10 : Max messages to receive per poll (max 10)}
        {--wait-time=20 : Long-poll wait time in seconds (max 20)}
        {--visibility-timeout=30 : Message visibility timeout in seconds}
        {--region= : AWS region (default: aws-kit.aws.region or ap-southeast-1)}
        {--endpoint= : Override endpoint (for LocalStack; default: aws-kit.aws.endpoint)}
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
        $queueOption = $this->option('queue');
        $queueUrl = is_string($queueOption) && $queueOption !== ''
            ? $queueOption
            : (string) config('aws-kit.sqs.consumer.queue_url', '');

        if ($queueUrl === '') {
            throw new RuntimeException(
                'SQS queue URL is not configured. Set config(\'aws-kit.sqs.consumer.queue_url\') '
                .'or pass --queue=<url> on the command line.'
            );
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

        // Apply the resolved config to the consumer. The Consumer
        // instance was injected by Laravel with a default empty
        // ConsumerConfig (queueUrl=''), so we need to swap in the
        // real one. withConfig() returns a fresh consumer bound
        // to the new config, preserving the SqsClient cache if any.
        $consumer = $this->consumer->withConfig($config);

        $consumer->registerSignalHandlers();

        // --once runs a single poll, then exits. Used by tests and
        // for one-shot debugging sessions. The full supervisord path
        // runs without --once (long-lived).
        if ($this->option('once')) {
            $consumer->consumeOnce(fn (Envelope $envelope) => $this->dispatch($envelope));

            return self::SUCCESS;
        }

        $consumer->consume(fn (Envelope $envelope) => $this->dispatch($envelope));

        return self::SUCCESS;
    }

    private function dispatch(Envelope $envelope): void
    {
        $this->dispatcher->dispatch($envelope->eventType(), $envelope->payload());
    }
}
