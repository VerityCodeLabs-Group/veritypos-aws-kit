<?php

declare(strict_types=1);

use VerityPOS\AwsKit\Contracts\Consumer;
use VerityPOS\AwsKit\Contracts\Dispatcher;
use VerityPOS\AwsKit\Contracts\Envelope;
use VerityPOS\AwsKit\Contracts\Handler;

it('defines the Envelope contract (source + eventType + payload)', function (): void {
    $envelope = new class implements Envelope
    {
        public function source(): string
        {
            return 'veritypos.auth';
        }

        public function eventType(): string
        {
            return 'user.created';
        }

        public function payload(): array
        {
            return ['id' => '1'];
        }
    };

    expect($envelope->source())->toBe('veritypos.auth')
        ->and($envelope->eventType())->toBe('user.created')
        ->and($envelope->payload())->toBe(['id' => '1']);
});

it('defines the Handler contract', function (): void {
    $handler = new class implements Handler
    {
        public function handle(string $eventType, array $payload): void {}
    };

    expect($handler)->toBeInstanceOf(Handler::class);
});

it('defines the Dispatcher contract', function (): void {
    $dispatcher = new class implements Dispatcher
    {
        public function dispatch(string $eventType, array $payload): void {}
    };

    expect($dispatcher)->toBeInstanceOf(Dispatcher::class);
});

it('defines the Consumer contract', function (): void {
    $consumer = new class implements Consumer
    {
        public function consume(callable $handler): void {}

        public function stop(): void {}
    };

    expect($consumer)->toBeInstanceOf(Consumer::class);
});
