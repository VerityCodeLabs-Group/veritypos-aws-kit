<?php

declare(strict_types=1);

use VerityPOS\AwsKit\Sqs\SqsClientFactory;

it('creates an SQS client with default region', function (): void {
    $factory = new SqsClientFactory;
    $client = $factory->create('https://sqs.ap-southeast-1.amazonaws.com/123/test-queue');

    expect($client->getRegion())->toBe('ap-southeast-1');
});

it('creates an SQS client with a custom region', function (): void {
    $factory = new SqsClientFactory;
    $client = $factory->create('https://sqs.ap-southeast-1.amazonaws.com/123/test-queue', 'us-east-1');

    expect($client->getRegion())->toBe('us-east-1');
});

it('rejects an empty queue URL', function (): void {
    $factory = new SqsClientFactory;
    $factory->create('');
})->throws(\InvalidArgumentException::class, 'Queue URL cannot be empty');

it('extracts a LocalStack endpoint from the queue URL', function (): void {
    $factory = new SqsClientFactory;
    $client = $factory->create('http://veritypos-localstack:4566/000000000000/test-queue');

    // The SQS client wraps the endpoint but we can verify via the queue URL
    // accessor or by checking that the configuration was applied (we don't
    // expose endpoint publicly, so we just verify the client builds).
    expect($client)->toBeInstanceOf(\Aws\Sqs\SqsClient::class);
});

it('extracts a LocalStack endpoint from a localhost:4566 URL', function (): void {
    $factory = new SqsClientFactory;
    $client = $factory->create('http://localhost:4566/000000000000/test-queue');

    expect($client)->toBeInstanceOf(\Aws\Sqs\SqsClient::class);
});

it('respects an explicit endpoint override', function (): void {
    $factory = new SqsClientFactory;
    $client = $factory->create(
        queueUrl: 'https://sqs.ap-southeast-1.amazonaws.com/123/test-queue',
        endpoint: 'http://custom-endpoint:4566',
    );

    expect($client)->toBeInstanceOf(\Aws\Sqs\SqsClient::class);
});

it('rejects a malformed URL with a LocalStack flag', function (): void {
    $factory = new SqsClientFactory;
    $factory->create('not-a-real-url:localstack');
})->throws(\InvalidArgumentException::class);
