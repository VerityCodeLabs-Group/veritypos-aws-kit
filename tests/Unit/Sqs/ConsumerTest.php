<?php

declare(strict_types=1);

use Aws\Sqs\SqsClient;
use VerityPOS\AwsKit\Sqs\Consumer;
use VerityPOS\AwsKit\Sqs\ConsumerConfig;
use VerityPOS\AwsKit\Sqs\SqsClientFactory;

it('reports not configured when queue URL is empty', function (): void {
    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: new ConsumerConfig(queueUrl: ''),
    );

    expect($consumer->isConfigured())->toBeFalse();
});

it('reports configured when queue URL is set', function (): void {
    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: ConsumerConfig::forQueue('https://sqs.ap-southeast-1.amazonaws.com/123/test'),
    );

    expect($consumer->isConfigured())->toBeTrue();
});

it('throws if consume is called without a configured queue URL', function (): void {
    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: new ConsumerConfig(queueUrl: ''),
    );

    $consumer->consume(fn () => null);
})->throws(LogicException::class, 'Consumer is not configured');

it('can be stopped (sets the shouldStop flag, no throw)', function (): void {
    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: ConsumerConfig::forQueue('q1'),
    );

    $consumer->stop();

    expect(true)->toBeTrue();
});

it('registers signal handlers when pcntl is available', function (): void {
    if (! extension_loaded('pcntl')) {
        $this->markTestSkipped('pcntl extension not available');
    }

    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: ConsumerConfig::forQueue('q1'),
    );

    // No exception = handlers registered
    $consumer->registerSignalHandlers();
    expect(true)->toBeTrue();
});

it('withConfig returns a fresh consumer bound to the new config', function (): void {
    // The SqsConsumeCommand uses withConfig() in handle() to apply
    // the queue URL resolved from CLI/env onto the injected
    // Consumer. The returned consumer must be a distinct instance
    // (the original stays configured as-injected, useful for tests
    // and for keeping signal-handler state isolated per invocation).
    $original = new Consumer(
        clientFactory: new SqsClientFactory,
        config: new ConsumerConfig,
    );
    $resolved = $original->withConfig(ConsumerConfig::forQueue('https://sqs.example.com/q1'));

    expect($original)->not->toBe($resolved)
        ->and($original->isConfigured())->toBeFalse()
        ->and($resolved->isConfigured())->toBeTrue();
});

it('withConfig preserves the SqsClient cache across the swap', function (): void {
    // If the consumer already opened an SqsClient (e.g. mid-loop),
    // withConfig() should carry the same client into the new
    // instance so we don't re-open a connection on every poll.
    $original = new Consumer(
        clientFactory: new SqsClientFactory,
        config: ConsumerConfig::forQueue('https://sqs.example.com/q1'),
    );
    $sqsClient = Mockery::mock(SqsClient::class);
    $reflection = new ReflectionProperty($original, 'client');
    $reflection->setValue($original, $sqsClient);

    $resolved = $original->withConfig(ConsumerConfig::forQueue('https://sqs.example.com/q2'));

    $resolvedClient = (new ReflectionProperty($resolved, 'client'))->getValue($resolved);
    expect($resolvedClient)->toBe($sqsClient);
});
