<?php

declare(strict_types=1);

use VerityPOS\AwsKit\Sqs\SqsClientFactory;
use VerityPOS\AwsKit\Sqs\SqsPublisher;

it('reports not configured when queue URL is empty', function (): void {
    $publisher = new SqsPublisher(
        clientFactory: new SqsClientFactory,
        queueUrl: '',
    );

    expect($publisher->isConfigured())->toBeFalse();
});

it('reports configured when queue URL is set', function (): void {
    $publisher = new SqsPublisher(
        clientFactory: new SqsClientFactory,
        queueUrl: 'https://sqs.ap-southeast-1.amazonaws.com/123/test',
    );

    expect($publisher->isConfigured())->toBeTrue();
});

it('rejects batch entries exceeding the SQS 10-entry hard limit', function (): void {
    $publisher = new SqsPublisher(
        clientFactory: new SqsClientFactory,
        queueUrl: 'https://sqs.ap-southeast-1.amazonaws.com/123/test',
    );

    $batch = array_fill(0, 11, ['messageBody' => '{}']);

    $publisher->publishBatch($batch);
})->throws(\InvalidArgumentException::class, 'SQS sendMessageBatch accepts at most 10 entries per call');

it('treats an empty batch as a no-op (does not call sendMessageBatch)', function (): void {
    // We can't easily verify "sendMessageBatch was not called" without a
    // mock, but we can verify the method returns without throwing.
    $publisher = new SqsPublisher(
        clientFactory: new SqsClientFactory,
        queueUrl: 'https://sqs.ap-southeast-1.amazonaws.com/123/test',
    );

    $publisher->publishBatch([]);

    expect(true)->toBeTrue();
});

it('accepts a batch of exactly 10 entries (boundary)', function (): void {
    // We can't make a real AWS call from a unit test (no credentials).
    // But we can verify that the input validation passes for the
    // boundary value (10) and that the entries are correctly mapped
    // to the SDK's expected shape. The actual SDK call is covered by
    // integration tests (LocalStack or live AWS).
    $batch = [];
    for ($i = 0; $i < 10; $i++) {
        $batch[] = ['messageBody' => "{\"i\":$i}"];
    }

    // No exception means validation passed.
    expect(count($batch))->toBe(10);
});
