<?php

declare(strict_types=1);

use VerityPOS\AwsKit\Sqs\QueueBinding;
use VerityPOS\AwsKit\Sqs\QueueBindingRegistry;
use VerityPOS\AwsKit\Sqs\SqsClientFactory;
use VerityPOS\AwsKit\Sqs\SqsEnvelopeParser;
use VerityPOS\AwsKit\Sqs\SqsPublisher;
use VerityPOS\AwsKit\Sqs\SqsPublisherBinding;
use VerityPOS\AwsKit\Sqs\SqsPublisherFactory;
use VerityPOS\AwsKit\Sqs\SqsPublisherRegistry;

it('registers a binding by name', function (): void {
    $registry = new QueueBindingRegistry;
    $registry->register(new QueueBinding(
        name: 'device-events',
        queueUrl: 'https://sqs.example.com/device',
    ));

    expect($registry->has('device-events'))->toBeTrue()
        ->and($registry->names())->toBe(['device-events'])
        ->and($registry->all())->toHaveCount(1);
});

it('rejects duplicate binding names', function (): void {
    $registry = new QueueBindingRegistry;
    $registry->register(new QueueBinding(name: 'a', queueUrl: 'q1'));

    $registry->register(new QueueBinding(name: 'a', queueUrl: 'q2'));
})->throws(InvalidArgumentException::class, 'already registered');

it('throws when looking up an unknown binding', function (): void {
    $registry = new QueueBindingRegistry;

    $registry->get('does-not-exist');
})->throws(InvalidArgumentException::class, 'No QueueBinding registered for name does-not-exist');

it('returns all bindings in registration order', function (): void {
    $registry = new QueueBindingRegistry;
    $registry->register(new QueueBinding(name: 'a', queueUrl: 'q1'));
    $registry->register(new QueueBinding(name: 'b', queueUrl: 'q2'));
    $registry->register(new QueueBinding(name: 'c', queueUrl: 'q3'));

    expect($registry->names())->toBe(['a', 'b', 'c']);
});

it('rejects a binding whose envelopeParserClass is not a real EnvelopeParser', function (): void {
    new QueueBinding(
        name: 'bad',
        queueUrl: 'q1',
        envelopeParserClass: stdClass::class,
    );
})->throws(InvalidArgumentException::class, 'must implement');

it('accepts a binding whose dispatcherClass is null (use global)', function (): void {
    $binding = new QueueBinding(
        name: 'use-global',
        queueUrl: 'q1',
        dispatcherClass: null,
    );

    expect($binding->dispatcherClass)->toBeNull();
});

it('rejects a binding whose dispatcherClass is not a real Dispatcher', function (): void {
    new QueueBinding(
        name: 'bad-dispatcher',
        queueUrl: 'q1',
        dispatcherClass: stdClass::class,
    );
})->throws(InvalidArgumentException::class, 'must implement');

it('builds a QueueBinding from an array via fromArray()', function (): void {
    $binding = QueueBinding::fromArray([
        'name' => 'from-array',
        'queue_url' => 'https://sqs.example.com/from-array',
        'region' => 'us-east-1',
        'endpoint' => 'http://localstack:4566',
        'max_messages' => 5,
        'wait_time_seconds' => 10,
        'visibility_timeout' => 60,
        'envelope_parser_class' => SqsEnvelopeParser::class,
    ]);

    expect($binding->name)->toBe('from-array')
        ->and($binding->queueUrl)->toBe('https://sqs.example.com/from-array')
        ->and($binding->region)->toBe('us-east-1')
        ->and($binding->endpoint)->toBe('http://localstack:4566')
        ->and($binding->maxMessages)->toBe(5)
        ->and($binding->waitTimeSeconds)->toBe(10)
        ->and($binding->visibilityTimeout)->toBe(60);
});

it('rejects SqsPublisherBinding::fromArray() when required fields are missing', function (): void {
    SqsPublisherBinding::fromArray(['name' => 'oops']);
})->throws(InvalidArgumentException::class, 'requires queue_url');

it('registers an SqsPublisherBinding by name', function (): void {
    $registry = new SqsPublisherRegistry;
    $registry->register(new SqsPublisherBinding(
        name: 'sync-events',
        queueUrl: 'https://sqs.example.com/sync',
    ));

    expect($registry->has('sync-events'))->toBeTrue()
        ->and($registry->get('sync-events')->queueUrl)->toBe('https://sqs.example.com/sync');
});

it('rejects duplicate publisher binding names', function (): void {
    $registry = new SqsPublisherRegistry;
    $registry->register(new SqsPublisherBinding(name: 'a', queueUrl: 'q1'));

    $registry->register(new SqsPublisherBinding(name: 'a', queueUrl: 'q2'));
})->throws(InvalidArgumentException::class, 'already registered');

it('builds an SqsPublisher from a binding (factory)', function (): void {
    $factory = new SqsPublisherFactory(new SqsClientFactory);

    $binding = new SqsPublisherBinding(
        name: 'sync-events',
        queueUrl: 'https://sqs.example.com/sync',
        region: 'us-east-1',
    );

    $publisher = $factory->make($binding);

    expect($publisher)->toBeInstanceOf(SqsPublisher::class)
        ->and($publisher->isConfigured())->toBeTrue();
});

it('caches the SqsPublisher across calls for the same binding name', function (): void {
    $factory = new SqsPublisherFactory(new SqsClientFactory);
    $binding = new SqsPublisherBinding(name: 'a', queueUrl: 'q1');

    $first = $factory->make($binding);
    $second = $factory->make($binding);

    expect($second)->toBe($first);
});

it('drops the cached publisher on forget()', function (): void {
    $factory = new SqsPublisherFactory(new SqsClientFactory);
    $binding = new SqsPublisherBinding(name: 'a', queueUrl: 'q1');

    $first = $factory->make($binding);
    $factory->forget('a');
    $second = $factory->make($binding);

    expect($second)->not->toBe($first);
});

it('builds an SqsPublisherBinding from an array via fromArray()', function (): void {
    $binding = SqsPublisherBinding::fromArray([
        'name' => 'sync-events',
        'queue_url' => 'https://sqs.example.com/sync',
        'region' => 'us-east-1',
        'endpoint' => 'http://localstack:4566',
    ]);

    expect($binding->name)->toBe('sync-events')
        ->and($binding->queueUrl)->toBe('https://sqs.example.com/sync')
        ->and($binding->region)->toBe('us-east-1')
        ->and($binding->endpoint)->toBe('http://localstack:4566');
});

it('rejects fromArray() when required fields are missing', function (): void {
    SqsPublisherBinding::fromArray(['name' => 'oops']);
})->throws(InvalidArgumentException::class, 'requires queue_url');
