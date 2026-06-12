<?php

declare(strict_types=1);

use VerityPOS\AwsKit\EventBridge\EventBridgePublisher;

/**
 * These tests verify the publisher's configuration handling and the
 * "not configured" path. The actual `putEvents` call is covered by
 * integration tests (which need LocalStack or AWS credentials).
 */
it('reports not configured when bus name is empty', function (): void {
    $publisher = new EventBridgePublisher(
        eventBusName: '',
        sourcePrefix: 'veritypos',
        region: 'ap-southeast-1',
        endpoint: null,
    );

    expect($publisher->isConfigured())->toBeFalse();
});

it('reports configured when bus name is set', function (): void {
    $publisher = new EventBridgePublisher(
        eventBusName: 'veritypos-production-domain-events',
        sourcePrefix: 'veritypos',
        region: 'ap-southeast-1',
        endpoint: null,
    );

    expect($publisher->isConfigured())->toBeTrue();
});

it('accepts a custom endpoint for LocalStack', function (): void {
    $publisher = new EventBridgePublisher(
        eventBusName: 'veritypos-domain-events',
        sourcePrefix: 'veritypos',
        region: 'ap-southeast-1',
        endpoint: 'http://localhost:4566',
    );

    expect($publisher->isConfigured())->toBeTrue();
});
