<?php

declare(strict_types=1);

use VerityPOS\AwsKit\Sqs\SqsEnvelope;
use VerityPOS\AwsKit\Sqs\SqsEnvelopeParser;

it('parses a message with event_type in message attributes', function (): void {
    $parser = new SqsEnvelopeParser;
    $envelope = $parser->parse([
        'MessageId' => 'msg-1',
        'ReceiptHandle' => 'rh-1',
        'Body' => json_encode(['id' => 'u-1', 'email' => 'a@b.com']),
        'MessageAttributes' => [
            'event_type' => ['DataType' => 'String', 'StringValue' => 'user.created'],
        ],
    ], 'https://sqs.ap-southeast-1.amazonaws.com/123/test');

    expect($envelope)->toBeInstanceOf(SqsEnvelope::class)
        ->and($envelope->source())->toBe('https://sqs.ap-southeast-1.amazonaws.com/123/test')
        ->and($envelope->eventType())->toBe('user.created')
        ->and($envelope->payload())->toBe(['id' => 'u-1', 'email' => 'a@b.com'])
        ->and($envelope->messageId())->toBe('msg-1')
        ->and($envelope->receiptHandle())->toBe('rh-1');
});

it('parses a message with event_type in body (detail-type fallback)', function (): void {
    $parser = new SqsEnvelopeParser;
    $envelope = $parser->parse([
        'MessageId' => 'msg-2',
        'Body' => json_encode([
            'id' => 't-1',
            'detail-type' => 'tenant.created',
        ]),
        'MessageAttributes' => [],
    ], 'queue-url');

    expect($envelope->eventType())->toBe('tenant.created')
        ->and($envelope->payload())->toBe(['id' => 't-1', 'detail-type' => 'tenant.created']);
});

it('prefers the message attribute over the body detail-type', function (): void {
    $parser = new SqsEnvelopeParser;
    $envelope = $parser->parse([
        'MessageId' => 'msg-3',
        'Body' => json_encode(['detail-type' => 'from-body']),
        'MessageAttributes' => [
            'event_type' => ['DataType' => 'String', 'StringValue' => 'from-attribute'],
        ],
    ], 'queue-url');

    expect($envelope->eventType())->toBe('from-attribute');
});

it('handles an empty body as an empty payload', function (): void {
    $parser = new SqsEnvelopeParser;
    $envelope = $parser->parse([
        'MessageId' => 'msg-4',
        'Body' => '',
        'MessageAttributes' => [
            'event_type' => ['DataType' => 'String', 'StringValue' => 'heartbeat'],
        ],
    ], 'queue-url');

    expect($envelope->payload())->toBe([])
        ->and($envelope->eventType())->toBe('heartbeat');
});

it('rejects a body that is not a JSON object', function (): void {
    $parser = new SqsEnvelopeParser;
    $parser->parse([
        'Body' => '"just-a-string"',
        'MessageAttributes' => [],
    ], 'queue-url');
})->throws(\InvalidArgumentException::class);

it('rejects a message without event_type anywhere', function (): void {
    $parser = new SqsEnvelopeParser;
    $parser->parse([
        'Body' => json_encode(['id' => '1']),
        'MessageAttributes' => [],
    ], 'queue-url');
})->throws(\InvalidArgumentException::class, 'SQS message missing event_type attribute');
