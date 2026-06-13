<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Console;

use Illuminate\Console\Command;
use RuntimeException;
use VerityPOS\AwsKit\Contracts\Dispatcher as DispatcherContract;
use VerityPOS\AwsKit\Contracts\Envelope;
use VerityPOS\AwsKit\Contracts\EnvelopeParser;
use VerityPOS\AwsKit\Sqs\Consumer;
use VerityPOS\AwsKit\Sqs\ConsumerConfig;
use VerityPOS\AwsKit\Sqs\QueueBinding;
use VerityPOS\AwsKit\Sqs\QueueBindingRegistry;
use VerityPOS\AwsKit\Sqs\SqsEnvelopeParser;

/**
 * Long-poll SQS consumer worker.
 *
 * Two invocation modes:
 *
 *   1. Binding mode (recommended for new code)
 *      The service registers `QueueBinding`s via `QueueBindingRegistry`
 *      from a service provider. Supervisord invokes the command with
 *      `--binding=NAME` to pick which one to consume. Each binding
 *      carries its own queue URL, region, envelope parser, and
 *      dispatcher.
 *
 *      [program:aws-kit-sqs-consume-inter-service]
 *      command=php /var/www/artisan aws-kit:sqs-consume --binding=inter-service
 *
 *      [program:aws-kit-sqs-consume-device-events]
 *      command=php /var/www/artisan aws-kit:sqs-consume --binding=device-events
 *
 *      If --binding is omitted and exactly one binding is registered,
 *      that binding is auto-selected. If multiple are registered, the
 *      command errors and asks for an explicit --binding.
 *
 *   2. Legacy single-queue mode (backward compat with v0.4.x)
 *      If no bindings are registered, the command falls back to
 *      `config('aws-kit.sqs.consumer.queue_url')` + the kit's default
 *      `SqsEnvelopeParser` + the global `Contracts\Dispatcher`. This
 *      is the path platform-service uses today.
 *
 * The worker (per invocation):
 *   1. Resolves the binding (or the legacy config)
 *   2. Long-polls SQS for messages
 *   3. Parses each message with the binding's `EnvelopeParser`
 *   4. Dispatches via the binding's `Dispatcher` (or the global one)
 *   5. On success: acks the message (configurable per binding)
 *   6. On failure: leaves the message for redelivery
 *
 * SIGTERM/SIGINT stop the loop after the current message finishes,
 * so ECS can drain the task without losing in-flight work.
 */
final class SqsConsumeCommand extends Command
{
    /** @var string */
    protected $signature = 'aws-kit:sqs-consume
        {--binding= : The QueueBinding name to consume from (default: the only registered binding)}
        {--queue= : Legacy single-queue override (reads queue_url from config aws-kit.sqs.consumer.queue_url when no binding is registered)}
        {--max-messages= : Override max messages per poll (max 10)}
        {--wait-time= : Override long-poll wait time in seconds (max 20)}
        {--visibility-timeout= : Override message visibility timeout in seconds}
        {--region= : AWS region (default: aws-kit.aws.region or ap-southeast-1) — legacy mode only}
        {--endpoint= : Override endpoint (for LocalStack; default: aws-kit.aws.endpoint) — legacy mode only}
        {--once : Process one batch and exit (used for tests)}';

    /** @var string */
    protected $description = 'Long-poll SQS consumer worker (Fargate supervisord runtime)';

    public function __construct(
        private readonly Consumer $consumer,
        private readonly QueueBindingRegistry $registry,
        private readonly DispatcherContract $globalDispatcher,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $binding = $this->resolveBinding();
        $config = $this->resolveConfig($binding);
        $parser = $this->resolveParser($binding);
        $dispatcher = $this->resolveDispatcher($binding);

        $this->info($this->planDescription($binding, $config));
        $this->info("  max-messages={$config->maxMessages} wait={$config->waitTimeSeconds}s visibility={$config->visibilityTimeout}s");

        $consumer = $this->consumer->withConfig($config);
        $consumer->registerSignalHandlers();

        $handler = fn (Envelope $envelope) => $this->dispatch($dispatcher, $envelope);

        if ($this->option('once')) {
            $consumer->consumeOnce($handler, $parser);

            return self::SUCCESS;
        }

        $consumer->consume($handler, $parser);

        return self::SUCCESS;
    }

    /**
     * Resolve the binding for this invocation.
     *
     * Returns a `QueueBinding` for binding mode, or `null` for legacy
     * single-queue mode (resolved from config).
     */
    private function resolveBinding(): ?QueueBinding
    {
        $nameOption = $this->option('binding');
        $name = is_string($nameOption) && $nameOption !== '' ? $nameOption : null;

        if ($name !== null) {
            return $this->registry->get($name);
        }

        $names = $this->registry->names();
        if ($names === []) {
            $this->warn('No QueueBindings registered. Falling back to config(aws-kit.sqs.consumer.queue_url). '
                .'New code should register a binding via QueueBindingRegistry.');

            return null;
        }

        if (count($names) > 1) {
            throw new RuntimeException(sprintf(
                'Multiple QueueBindings are registered [%s]. Pass --binding=<name> to pick one.',
                implode(', ', $names),
            ));
        }

        return $this->registry->get($names[0]);
    }

    private function resolveConfig(?QueueBinding $binding): ConsumerConfig
    {
        if ($binding instanceof QueueBinding) {
            return new ConsumerConfig(
                queueUrl: $binding->queueUrl,
                maxMessages: (int) ($this->option('max-messages') ?: $binding->maxMessages),
                waitTimeSeconds: (int) ($this->option('wait-time') ?: $binding->waitTimeSeconds),
                visibilityTimeout: (int) ($this->option('visibility-timeout') ?: $binding->visibilityTimeout),
                region: $binding->region,
                endpoint: $binding->endpoint,
                acknowledgeOnSuccess: $binding->acknowledgeOnSuccess,
            );
        }

        $queueOption = $this->option('queue');
        $queueUrl = is_string($queueOption) && $queueOption !== ''
            ? $queueOption
            : (string) config('aws-kit.sqs.consumer.queue_url', '');

        if ($queueUrl === '') {
            throw new RuntimeException(
                'No QueueBindings are registered AND config(aws-kit.sqs.consumer.queue_url) is empty. '
                .'Either register a QueueBinding or set the legacy config key.'
            );
        }

        return new ConsumerConfig(
            queueUrl: $queueUrl,
            maxMessages: (int) ($this->option('max-messages') ?: 10),
            waitTimeSeconds: (int) ($this->option('wait-time') ?: 20),
            visibilityTimeout: (int) ($this->option('visibility-timeout') ?: 30),
            region: (string) ($this->option('region') ?: config('aws-kit.aws.region', 'ap-southeast-1')),
            endpoint: $this->option('endpoint') ?: config('aws-kit.aws.endpoint'),
        );
    }

    private function resolveParser(?QueueBinding $binding): EnvelopeParser
    {
        if ($binding instanceof QueueBinding) {
            $class = $binding->envelopeParserClass;

            return new $class;
        }

        return new SqsEnvelopeParser;
    }

    private function resolveDispatcher(?QueueBinding $binding): DispatcherContract
    {
        if ($binding instanceof QueueBinding && $binding->dispatcherClass !== null) {
            return app($binding->dispatcherClass);
        }

        return $this->globalDispatcher;
    }

    private function dispatch(DispatcherContract $dispatcher, Envelope $envelope): void
    {
        $dispatcher->dispatch($envelope->eventType(), $envelope->payload());
    }

    private function planDescription(?QueueBinding $binding, ConsumerConfig $config): string
    {
        if ($binding instanceof QueueBinding) {
            return "Consuming binding '{$binding->name}' from {$config->queueUrl}";
        }

        return "Consuming (legacy mode) from {$config->queueUrl}";
    }
}
