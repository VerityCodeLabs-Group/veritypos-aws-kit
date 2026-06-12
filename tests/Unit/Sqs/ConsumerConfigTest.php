<?php

declare(strict_types=1);

use VerityPOS\AwsKit\Sqs\ConsumerConfig;

it('builds a config from a queue URL with defaults', function (): void {
    $config = ConsumerConfig::forQueue('https://sqs.ap-southeast-1.amazonaws.com/123/test');

    expect($config->queueUrl)->toBe('https://sqs.ap-southeast-1.amazonaws.com/123/test')
        ->and($config->maxMessages)->toBe(10)
        ->and($config->waitTimeSeconds)->toBe(20)
        ->and($config->visibilityTimeout)->toBe(30)
        ->and($config->region)->toBe('ap-southeast-1')
        ->and($config->endpoint)->toBeNull()
        ->and($config->acknowledgeOnSuccess)->toBeTrue();
});

it('withMaxMessages returns a new config with the updated value', function (): void {
    $config = ConsumerConfig::forQueue('q1');
    $newConfig = $config->withMaxMessages(5);

    expect($newConfig->maxMessages)->toBe(5)
        ->and($newConfig->queueUrl)->toBe('q1')
        // original is unchanged (immutability)
        ->and($config->maxMessages)->toBe(10);
});

it('withWaitTimeSeconds returns a new config with the updated value', function (): void {
    $config = ConsumerConfig::forQueue('q1');
    $newConfig = $config->withWaitTimeSeconds(5);

    expect($newConfig->waitTimeSeconds)->toBe(5)
        ->and($config->waitTimeSeconds)->toBe(20);
});
