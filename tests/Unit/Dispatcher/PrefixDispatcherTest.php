<?php

declare(strict_types=1);

use VerityPOS\AwsKit\Contracts\Handler;
use VerityPOS\AwsKit\Dispatcher\PrefixDispatcher;

it('routes an event to the first matching prefix handler', function (): void {
    $userHandler = new class implements Handler
    {
        public array $handled = [];

        public function handle(string $eventType, array $payload): void
        {
            $this->handled[] = ['type' => $eventType, 'payload' => $payload];
        }
    };

    $dispatcher = new PrefixDispatcher([
        'user.' => $userHandler,
        'tenant.' => new class implements Handler
        {
            public function handle(string $eventType, array $payload): void
            {
                throw new RuntimeException('should not be called');
            }
        },
    ]);

    $dispatcher->dispatch('user.created', ['id' => 'abc-123']);

    expect($userHandler->handled)->toHaveCount(1)
        ->and($userHandler->handled[0]['type'])->toBe('user.created')
        ->and($userHandler->handled[0]['payload'])->toBe(['id' => 'abc-123']);
});

it('throws when no handler matches the event type', function (): void {
    $dispatcher = new PrefixDispatcher([]);

    $dispatcher->dispatch('user.created', []);
})->throws(RuntimeException::class, 'No handler registered for event type: user.created');

it('uses the first registered prefix when multiple could match', function (): void {
    $specific = new class implements Handler
    {
        public function handle(string $eventType, array $payload): void {}
    };
    $generic = new class implements Handler
    {
        public function handle(string $eventType, array $payload): void
        {
            throw new RuntimeException('should not be called');
        }
    };

    // If a consumer registers `user.deleted` first, then `user.`, the
    // specific one wins.
    $dispatcher = new PrefixDispatcher([
        'user.deleted' => $specific,
        'user.' => $generic,
    ]);

    $dispatcher->dispatch('user.deleted', []);
    expect(true)->toBeTrue();
});
