<?php

declare(strict_types=1);

use VerityPOS\AwsKit\Aws\ClientFactory;

it('builds a client for an arbitrary AWS service', function (): void {
    $factory = new ClientFactory;
    $client = $factory->build('s3', 'us-west-2');

    expect($client->getRegion())->toBe('us-west-2')
        ->and($client->getApi()->getServiceFullName())->toBe('s3');
});

it('uses the default region when none is provided', function (): void {
    $factory = new ClientFactory;
    $client = $factory->build('sns');

    expect($client->getRegion())->toBe('ap-southeast-1');
});

it('applies a custom endpoint (LocalStack)', function (): void {
    $factory = new ClientFactory;
    $client = $factory->build('sqs', 'ap-southeast-1', 'http://localhost:4566');

    expect($client)->toBeInstanceOf(\Aws\AwsClient::class);
});

it('uses default credential chain when no creds and no endpoint', function (): void {
    $factory = new ClientFactory;
    $client = $factory->build('sqs', 'ap-southeast-1', null, null);

    // The SDK will fall back to the default credential provider chain
    // (env, role, etc.). We can only verify the client builds.
    expect($client)->toBeInstanceOf(\Aws\AwsClient::class);
});
