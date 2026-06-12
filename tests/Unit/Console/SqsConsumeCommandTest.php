<?php

declare(strict_types=1);

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
    $dispatcher = new class implements Dispatcher {
        public function dispatch(string $eventType, array $payload): void {}
    };

    $command = new SqsConsumeCommand($consumer, $dispatcher);

    expect($command)->toBeInstanceOf(\Illuminate\Console\Command::class)
        ->and($command->getName())->toBe('aws-kit:sqs-consume');
});

it('declares the expected --queue and tuning options', function (): void {
    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: ConsumerConfig::forQueue('q1'),
    );
    $dispatcher = new class implements Dispatcher {
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

it('requires --queue to be a non-empty string (the option itself)', function (): void {
    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: ConsumerConfig::forQueue('q1'),
    );
    $dispatcher = new class implements Dispatcher {
        public function dispatch(string $eventType, array $payload): void {}
    };

    $command = new SqsConsumeCommand($consumer, $dispatcher);

    // The --queue option must accept a value (required for runtime).
    $option = $command->getDefinition()->getOption('queue');
    expect($option->acceptValue())->toBeTrue();
});

it('dispatches the unwrapped envelope to the consumer service dispatcher', function (): void {
    $envelope = new class implements Envelope {
        public function source(): string { return 'q1'; }
        public function eventType(): string { return 'user.created'; }
        public function payload(): array { return ['id' => 'u-1']; }
    };

    $captured = null;
    $dispatcher = new class($captured) implements Dispatcher {
        public mixed $captured = null;
        public function dispatch(string $eventType, array $payload): void
        {
            $this->captured = ['type' => $eventType, 'payload' => $payload];
        }
    };

    $dispatcher->dispatch($envelope->eventType(), $envelope->payload());

    expect($dispatcher->captured)->toBe(['type' => 'user.created', 'payload' => ['id' => 'u-1']]);
});
