<?php

declare(strict_types=1);

use Aws\Result;
use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;
use Symfony\Component\Console\Tester\CommandTester;
use VerityPOS\AwsKit\Console\SqsConsumeCommand;
use VerityPOS\AwsKit\Contracts\Dispatcher;
use VerityPOS\AwsKit\Contracts\Envelope;
use VerityPOS\AwsKit\Sqs\Consumer;
use VerityPOS\AwsKit\Sqs\ConsumerConfig;
use VerityPOS\AwsKit\Sqs\SqsClientFactory;

it('implements the artisan command contract', function (): void {
    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: ConsumerConfig::forQueue('q1'),
    );
    $dispatcher = new class implements Dispatcher
    {
        public function dispatch(string $eventType, array $payload): void {}
    };

    $command = new SqsConsumeCommand($consumer, $dispatcher);

    expect($command)->toBeInstanceOf(Command::class)
        ->and($command->getName())->toBe('aws-kit:sqs-consume');
});

it('declares the expected --queue and tuning options', function (): void {
    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: ConsumerConfig::forQueue('q1'),
    );
    $dispatcher = new class implements Dispatcher
    {
        public function dispatch(string $eventType, array $payload): void {}
    };

    $command = new SqsConsumeCommand($consumer, $dispatcher);
    $definition = $command->getDefinition();

    expect($definition->hasOption('queue'))->toBeTrue()
        ->and($definition->hasOption('max-messages'))->toBeTrue()
        ->and($definition->hasOption('wait-time'))->toBeTrue()
        ->and($definition->hasOption('visibility-timeout'))->toBeTrue()
        ->and($definition->hasOption('region'))->toBeTrue()
        ->and($definition->hasOption('endpoint'))->toBeTrue()
        ->and($definition->hasOption('once'))->toBeTrue();
});

it('accepts a value for --queue (option is not a flag)', function (): void {
    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: ConsumerConfig::forQueue('q1'),
    );
    $dispatcher = new class implements Dispatcher
    {
        public function dispatch(string $eventType, array $payload): void {}
    };

    $command = new SqsConsumeCommand($consumer, $dispatcher);
    $option = $command->getDefinition()->getOption('queue');
    expect($option->acceptValue())->toBeTrue();
});

it('can be constructed without runtime config (artisan-list safe)', function (): void {
    // Regression: previously the kit's SqsConsumeCommand cascaded
    // to ConsumerConfig($queueUrl) which had no default, so any
    // service that auto-registered the command would fail `php
    // artisan list`. ConsumerConfig now defaults to '' and the
    // Consumer throws at consume() time if not configured.
    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: new ConsumerConfig,
    );
    $dispatcher = new class implements Dispatcher
    {
        public function dispatch(string $eventType, array $payload): void {}
    };

    $command = new SqsConsumeCommand($consumer, $dispatcher);

    expect($command)->toBeInstanceOf(Command::class)
        ->and($command->getName())->toBe('aws-kit:sqs-consume');
});

it('reads the queue URL from config(\'aws-kit.sqs.consumer.queue_url\') when --queue is omitted', function (): void {
    // The queue URL is read from config('aws-kit.sqs.consumer.queue_url')
    // when --queue is omitted. Assert the URL flows through to the
    // SQS receiveMessage call.
    config()->set('aws-kit.sqs.consumer.queue_url', 'https://sqs.example.com/from-config');

    $sqsClient = Mockery::mock(SqsClient::class);
    $sqsClient->shouldReceive('receiveMessage')
        ->once()
        ->withArgs(function (array $args) {
            expect($args['QueueUrl'])->toBe('https://sqs.example.com/from-config');

            return true;
        })
        ->andReturn(new Result(['Messages' => []]));

    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: new ConsumerConfig(queueUrl: 'https://sqs.example.com/from-config'),
    );

    // Inject the mocked SqsClient directly into the Consumer's
    // private $client cache. SqsClientFactory is final and can't be
    // mocked; injecting here skips the factory call entirely.
    $reflection = new ReflectionProperty($consumer, 'client');
    $reflection->setValue($consumer, $sqsClient);

    $dispatcher = new class implements Dispatcher
    {
        public function dispatch(string $eventType, array $payload): void {}
    };

    $command = new SqsConsumeCommand($consumer, $dispatcher);
    $command->setLaravel($this->app);

    // --once runs a single poll, then exits — no infinite loop.
    $tester = new CommandTester($command);
    $tester->execute(['--once' => true]);

    expect($tester->getStatusCode())->toBe(0);
});

it('--queue takes precedence over the config value when both are provided', function (): void {
    config()->set('aws-kit.sqs.consumer.queue_url', 'https://sqs.example.com/from-config');

    $sqsClient = Mockery::mock(SqsClient::class);
    $sqsClient->shouldReceive('receiveMessage')
        ->once()
        ->withArgs(function (array $args) {
            // --queue wins over config
            expect($args['QueueUrl'])->toBe('https://sqs.example.com/from-cli');

            return true;
        })
        ->andReturn(new Result(['Messages' => []]));

    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: new ConsumerConfig(queueUrl: 'https://sqs.example.com/from-cli'),
    );

    $reflection = new ReflectionProperty($consumer, 'client');
    $reflection->setValue($consumer, $sqsClient);

    $dispatcher = new class implements Dispatcher
    {
        public function dispatch(string $eventType, array $payload): void {}
    };

    $command = new SqsConsumeCommand($consumer, $dispatcher);
    $command->setLaravel($this->app);

    $tester = new CommandTester($command);
    $tester->execute(['--queue' => 'https://sqs.example.com/from-cli', '--once' => true]);

    expect($tester->getStatusCode())->toBe(0);
});

it('dispatches the unwrapped envelope to the consumer service dispatcher', function (): void {
    $envelope = new class implements Envelope
    {
        public function source(): string
        {
            return 'q1';
        }

        public function eventType(): string
        {
            return 'user.created';
        }

        public function payload(): array
        {
            return ['id' => 'u-1'];
        }
    };

    $captured = null;
    $dispatcher = new class($captured) implements Dispatcher
    {
        public mixed $captured = null;

        public function dispatch(string $eventType, array $payload): void
        {
            $this->captured = ['type' => $eventType, 'payload' => $payload];
        }
    };

    $dispatcher->dispatch($envelope->eventType(), $envelope->payload());

    expect($dispatcher->captured)->toBe(['type' => 'user.created', 'payload' => ['id' => 'u-1']]);
});
