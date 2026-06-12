<?php

declare(strict_types=1);

use VerityPOS\AwsKit\Dispatcher\NullDispatcher;

it('silently drops events routed to it', function (): void {
    $dispatcher = new NullDispatcher;

    // No exception, no return value — just absorbs the call.
    $dispatcher->dispatch('user.created', ['id' => '1']);
    $dispatcher->dispatch('tenant.created', ['id' => '2']);

    expect(true)->toBeTrue();
});
