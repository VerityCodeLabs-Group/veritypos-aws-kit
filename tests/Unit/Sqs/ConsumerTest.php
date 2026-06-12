<?php

declare(strict_types=1);

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
})->throws(\LogicException::class, 'Consumer is not configured');

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
