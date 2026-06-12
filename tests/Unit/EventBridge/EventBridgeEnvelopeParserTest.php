<?php

declare(strict_types=1);

use VerityPOS\AwsKit\EventBridge\EventBridgeEnvelope;
use VerityPOS\AwsKit\EventBridge\EventBridgeEnvelopeParser;

it('parses a valid EventBridge envelope from JSON string', function (): void {
    $parser = new EventBridgeEnvelopeParser;
    $envelope = $parser->parse(json_encode([
        'version' => '0',
        'id' => 'evt-123',
        'source' => 'veritypos.auth',
        'detail-type' => 'user.created',
        'detail' => ['id' => 'usr-1', 'email' => 'a@b.com'],
    ]));

    expect($envelope)->toBeInstanceOf(EventBridgeEnvelope::class)
        ->and($envelope->source())->toBe('veritypos.auth')
        ->and($envelope->eventType())->toBe('user.created')
        ->and($envelope->payload())->toBe(['id' => 'usr-1', 'email' => 'a@b.com']);
});

it('parses a valid EventBridge envelope from an array', function (): void {
    $parser = new EventBridgeEnvelopeParser;
    $envelope = $parser->fromArray([
        'source' => 'veritypos.platform',
        'detail-type' => 'tenant.created',
        'detail' => ['id' => 't-1', 'name' => 'Acme'],
    ]);

    expect($envelope->eventType())->toBe('tenant.created')
        ->and($envelope->source())->toBe('veritypos.platform')
        ->and($envelope->payload())->toBe(['id' => 't-1', 'name' => 'Acme']);
});

it('rejects an envelope missing the source field', function (): void {
    $parser = new EventBridgeEnvelopeParser;
    $parser->fromArray(['detail-type' => 'user.created', 'detail' => []]);
})->throws(InvalidArgumentException::class, 'EventBridge envelope missing source or detail-type');

it('rejects an envelope missing the detail-type field', function (): void {
    $parser = new EventBridgeEnvelopeParser;
    $parser->fromArray(['source' => 'veritypos.auth', 'detail' => []]);
})->throws(InvalidArgumentException::class, 'EventBridge envelope missing source or detail-type');

it('rejects an envelope where detail is not an array', function (): void {
    $parser = new EventBridgeEnvelopeParser;
    $parser->fromArray([
        'source' => 'veritypos.auth',
        'detail-type' => 'user.created',
        'detail' => 'not-an-object',
    ]);
})->throws(InvalidArgumentException::class, 'EventBridge envelope `detail` must be an array');

it('rejects a non-array JSON payload', function (): void {
    $parser = new EventBridgeEnvelopeParser;
    $parser->parse('"just-a-string"');
})->throws(InvalidArgumentException::class);
