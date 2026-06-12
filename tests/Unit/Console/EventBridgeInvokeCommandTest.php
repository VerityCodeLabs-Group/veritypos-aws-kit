<?php

declare(strict_types=1);

use VerityPOS\AwsKit\Console\EventBridgeInvokeCommand;
use VerityPOS\AwsKit\Contracts\Dispatcher;
use VerityPOS\AwsKit\EventBridge\EventBridgeEnvelopeParser;

it('implements the artisan command contract', function (): void {
    $dispatcher = new class implements Dispatcher {
        public function dispatch(string $eventType, array $payload): void {}
    };
    $parser = new EventBridgeEnvelopeParser;

    $command = new EventBridgeInvokeCommand($dispatcher, $parser);

    expect($command)->toBeInstanceOf(\Illuminate\Console\Command::class)
        ->and($command->getName())->toBe('aws-kit:event-bridge-invoke');
});

it('declares the expected --event-type and --event-file options', function (): void {
    $dispatcher = new class implements Dispatcher {
        public function dispatch(string $eventType, array $payload): void {}
    };
    $parser = new EventBridgeEnvelopeParser;

    $command = new EventBridgeInvokeCommand($dispatcher, $parser);
    $definition = $command->getDefinition();

    expect($definition->hasOption('event-type'))->toBeTrue()
        ->and($definition->hasOption('event-file'))->toBeTrue()
        ->and($definition->hasOption('source'))->toBeTrue()
        ->and($definition->hasOption('payload'))->toBeTrue();
});

it('the --event-type and --event-file options must accept a value', function (): void {
    $dispatcher = new class implements Dispatcher {
        public function dispatch(string $eventType, array $payload): void {}
    };
    $parser = new EventBridgeEnvelopeParser;

    $command = new EventBridgeInvokeCommand($dispatcher, $parser);
    $definition = $command->getDefinition();

    expect($definition->getOption('event-type')->acceptValue())->toBeTrue()
        ->and($definition->getOption('event-file')->acceptValue())->toBeTrue()
        ->and($definition->getOption('source')->acceptValue())->toBeTrue()
        ->and($definition->getOption('payload')->acceptValue())->toBeTrue();
});
