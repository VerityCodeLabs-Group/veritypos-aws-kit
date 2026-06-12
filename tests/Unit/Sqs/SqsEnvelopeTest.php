<?php

declare(strict_types=1);

use VerityPOS\AwsKit\Sqs\SqsEnvelope;

it('exposes all fields set in the constructor', function (): void {
    $envelope = new SqsEnvelope(
        source: 'https://sqs.ap-southeast-1.amazonaws.com/123/test',
        eventType: 'user.created',
        payload: ['id' => 'u-1'],
        attributes: ['event_type' => ['DataType' => 'String', 'StringValue' => 'user.created']],
        messageId: 'msg-1',
        receiptHandle: 'rh-1',
    );

    expect($envelope->source())->toBe('https://sqs.ap-southeast-1.amazonaws.com/123/test')
        ->and($envelope->eventType())->toBe('user.created')
        ->and($envelope->payload())->toBe(['id' => 'u-1'])
        ->and($envelope->attributes())->toBe(['event_type' => ['DataType' => 'String', 'StringValue' => 'user.created']])
        ->and($envelope->messageId())->toBe('msg-1')
        ->and($envelope->receiptHandle())->toBe('rh-1');
});

it('defaults attributes, messageId, and receiptHandle to empty / null', function (): void {
    $envelope = new SqsEnvelope(
        source: 'queue',
        eventType: 'user.created',
        payload: ['id' => '1'],
    );

    expect($envelope->attributes())->toBe([])
        ->and($envelope->messageId())->toBeNull()
        ->and($envelope->receiptHandle())->toBeNull();
});
