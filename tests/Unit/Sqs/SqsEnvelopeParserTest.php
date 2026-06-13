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
})->throws(InvalidArgumentException::class);

it('rejects a message without event_type anywhere', function (): void {
    $parser = new SqsEnvelopeParser;
    $parser->parse([
        'Body' => json_encode(['id' => '1']),
        'MessageAttributes' => [],
    ], 'queue-url');
})->throws(InvalidArgumentException::class, 'SQS message missing event_type attribute');

it('unwraps an EventBridge envelope and exposes detail as the payload', function (): void {
    // EventBridge → SQS delivers the full EventBridge envelope in the
    // SQS body. The kit's runtime-agnostic contract is that the
    // dispatched payload is the *domain* payload, so the parser
    // unwraps the envelope. Without this, downstream consumers see
    // the EventBridge wrapper (version, id, account, ...) as their
    // payload and domain fields like `aggregate_id` are buried under
    // `detail`.
    $parser = new SqsEnvelopeParser;
    $envelope = $parser->parse([
        'MessageId' => 'msg-eb',
        'Body' => json_encode([
            'version' => '0',
            'id' => 'evt-1',
            'detail-type' => 'user.created',
            'source' => 'veritypos.auth',
            'account' => '123456789012',
            'time' => '2026-06-13T01:00:00Z',
            'region' => 'ap-southeast-1',
            'resources' => [],
            'detail' => [
                'aggregate_type' => 'user',
                'aggregate_id' => 'u-1',
                'source' => 'auth',
                'id' => 'u-1',
                'name' => 'Alice',
            ],
        ]),
        'MessageAttributes' => [
            'event_type' => ['DataType' => 'String', 'StringValue' => 'user.created'],
        ],
    ], 'queue-url');

    // The dispatched payload is the domain detail, not the wrapper.
    expect($envelope->payload())->toBe([
        'aggregate_type' => 'user',
        'aggregate_id' => 'u-1',
        'source' => 'auth',
        'id' => 'u-1',
        'name' => 'Alice',
    ])->and($envelope->eventType())->toBe('user.created');
});

it('reads event_type from the EventBridge envelope detail-type when no message attribute is set', function (): void {
    // Some EventBridge → SQS configurations don't set the event_type
    // message attribute (the kit's EventBridge publisher does, but
    // other publishers may not). The parser should still find the
    // event type from the envelope's detail-type field.
    $parser = new SqsEnvelopeParser;
    $envelope = $parser->parse([
        'Body' => json_encode([
            'version' => '0',
            'detail-type' => 'user.created',
            'source' => 'veritypos.auth',
            'detail' => ['aggregate_id' => 'u-2'],
        ]),
        'MessageAttributes' => [],
    ], 'queue-url');

    expect($envelope->eventType())->toBe('user.created')
        ->and($envelope->payload())->toBe(['aggregate_id' => 'u-2']);
});

it('does not unwrap payloads that merely contain a detail key', function (): void {
    // Defensive: avoid misidentifying a domain payload that happens
    // to have a "detail" key. The unwrap requires version="0" AND
    // a string detail-type AND a detail field — all three.
    $parser = new SqsEnvelopeParser;
    $envelope = $parser->parse([
        'Body' => json_encode([
            'detail' => ['note' => 'something'],
            'id' => '1',
        ]),
        'MessageAttributes' => [
            'event_type' => ['DataType' => 'String', 'StringValue' => 'custom.event'],
        ],
    ], 'queue-url');

    // Payload is NOT unwrapped (no version="0" / detail-type).
    expect($envelope->payload())->toBe([
        'detail' => ['note' => 'something'],
        'id' => '1',
    ])->and($envelope->eventType())->toBe('custom.event');
});
