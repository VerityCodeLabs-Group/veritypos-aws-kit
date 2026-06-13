<?php

declare(strict_types=1);

use Aws\Result;
use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;
use Symfony\Component\Console\Tester\CommandTester;
use VerityPOS\AwsKit\Console\SqsConsumeCommand;
use VerityPOS\AwsKit\Contracts\Dispatcher;
use VerityPOS\AwsKit\Contracts\Envelope;
use VerityPOS\AwsKit\Contracts\EnvelopeParser;
use VerityPOS\AwsKit\Sqs\Consumer;
use VerityPOS\AwsKit\Sqs\ConsumerConfig;
use VerityPOS\AwsKit\Sqs\QueueBinding;
use VerityPOS\AwsKit\Sqs\QueueBindingRegistry;
use VerityPOS\AwsKit\Sqs\SqsClientFactory;

/**
 * Anonymous Dispatcher for tests.
 */
function kitTestDispatcher(?array &$captured = null): Dispatcher
{
    return new class($captured) implements Dispatcher
    {
        public function __construct(public ?array &$captured = null) {}

        public function dispatch(string $eventType, array $payload): void
        {
            $this->captured = ['type' => $eventType, 'payload' => $payload];
        }
    };
}

it('implements the artisan command contract', function (): void {
    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: ConsumerConfig::forQueue('q1'),
    );
    $registry = new QueueBindingRegistry;
    $dispatcher = kitTestDispatcher();

    $command = new SqsConsumeCommand($consumer, $registry, $dispatcher);

    expect($command)->toBeInstanceOf(Command::class)
        ->and($command->getName())->toBe('aws-kit:sqs-consume');
});

it('declares the expected --binding and tuning options', function (): void {
    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: ConsumerConfig::forQueue('q1'),
    );
    $registry = new QueueBindingRegistry;
    $dispatcher = kitTestDispatcher();

    $command = new SqsConsumeCommand($consumer, $registry, $dispatcher);
    $definition = $command->getDefinition();

    expect($definition->hasOption('binding'))->toBeTrue()
        ->and($definition->hasOption('max-messages'))->toBeTrue()
        ->and($definition->hasOption('wait-time'))->toBeTrue()
        ->and($definition->hasOption('visibility-timeout'))->toBeTrue()
        ->and($definition->hasOption('once'))->toBeTrue();
});

it('accepts a value for --binding (option is not a flag)', function (): void {
    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: ConsumerConfig::forQueue('q1'),
    );
    $registry = new QueueBindingRegistry;
    $dispatcher = kitTestDispatcher();

    $command = new SqsConsumeCommand($consumer, $registry, $dispatcher);
    $option = $command->getDefinition()->getOption('binding');
    expect($option->acceptValue())->toBeTrue();
});

it('can be constructed without runtime config (artisan-list safe)', function (): void {
    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: new ConsumerConfig,
    );
    $registry = new QueueBindingRegistry;
    $dispatcher = kitTestDispatcher();

    $command = new SqsConsumeCommand($consumer, $registry, $dispatcher);

    expect($command)->toBeInstanceOf(Command::class)
        ->and($command->getName())->toBe('aws-kit:sqs-consume');
});

it('resolves a named binding from the registry', function (): void {
    $captured = null;
    $dispatcher = kitTestDispatcher($captured);
    $registry = new QueueBindingRegistry;
    $registry->register(new QueueBinding(
        name: 'test-binding',
        queueUrl: 'https://sqs.example.com/test',
    ));

    $sqsClient = Mockery::mock(SqsClient::class);
    $sqsClient->shouldReceive('receiveMessage')
        ->once()
        ->andReturn(new Result(['Messages' => []]));

    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: new ConsumerConfig,
    );

    // Inject the mocked SqsClient directly into the Consumer's
    // private $client cache.
    $reflection = new ReflectionProperty($consumer, 'client');
    $reflection->setValue($consumer, $sqsClient);

    $command = new SqsConsumeCommand($consumer, $registry, $dispatcher);
    $command->setLaravel($this->app);

    $tester = new CommandTester($command);
    $tester->execute(['--binding' => 'test-binding', '--once' => true]);

    expect($tester->getStatusCode())->toBe(0);
});

it('auto-selects the only registered binding when --binding is omitted', function (): void {
    $registry = new QueueBindingRegistry;
    $registry->register(new QueueBinding(
        name: 'only-binding',
        queueUrl: 'https://sqs.example.com/only',
    ));

    $sqsClient = Mockery::mock(SqsClient::class);
    $sqsClient->shouldReceive('receiveMessage')
        ->once()
        ->andReturn(new Result(['Messages' => []]));

    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: new ConsumerConfig,
    );
    $reflection = new ReflectionProperty($consumer, 'client');
    $reflection->setValue($consumer, $sqsClient);

    $dispatcher = kitTestDispatcher();

    $command = new SqsConsumeCommand($consumer, $registry, $dispatcher);
    $command->setLaravel($this->app);

    $tester = new CommandTester($command);
    $tester->execute(['--once' => true]);

    expect($tester->getStatusCode())->toBe(0);
});

it('throws when no bindings are registered', function (): void {
    $registry = new QueueBindingRegistry;
    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: new ConsumerConfig,
    );
    $dispatcher = kitTestDispatcher();

    $command = new SqsConsumeCommand($consumer, $registry, $dispatcher);
    $command->setLaravel($this->app);

    $tester = new CommandTester($command);
    $tester->execute(['--once' => true]);
})->throws(RuntimeException::class, 'No QueueBindings are registered');

it('throws when multiple bindings are registered and --binding is omitted', function (): void {
    $registry = new QueueBindingRegistry;
    $registry->register(new QueueBinding(name: 'a', queueUrl: 'q1'));
    $registry->register(new QueueBinding(name: 'b', queueUrl: 'q2'));

    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: new ConsumerConfig,
    );
    $dispatcher = kitTestDispatcher();

    $command = new SqsConsumeCommand($consumer, $registry, $dispatcher);
    $command->setLaravel($this->app);

    $tester = new CommandTester($command);
    $tester->execute(['--once' => true]);
})->throws(RuntimeException::class, 'Multiple QueueBindings are registered');

it('throws when the named binding does not exist', function (): void {
    $registry = new QueueBindingRegistry;
    $registry->register(new QueueBinding(name: 'a', queueUrl: 'q1'));

    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: new ConsumerConfig,
    );
    $dispatcher = kitTestDispatcher();

    $command = new SqsConsumeCommand($consumer, $registry, $dispatcher);
    $command->setLaravel($this->app);

    $tester = new CommandTester($command);
    $tester->execute(['--binding' => 'does-not-exist', '--once' => true]);
})->throws(InvalidArgumentException::class, 'No QueueBinding registered for name does-not-exist');

it('uses the binding\'s envelope parser when one is declared', function (): void {
    // Build a fake parser that emits a known Envelope.
    $parser = new class implements EnvelopeParser
    {
        public function parse(array $rawMessage, string $source): Envelope
        {
            return new class($source) implements Envelope
            {
                public function __construct(public string $src) {}

                public function source(): string
                {
                    return $this->src;
                }

                public function eventType(): string
                {
                    return 'custom.event';
                }

                public function payload(): array
                {
                    return ['from' => 'custom-parser'];
                }
            };
        }
    };

    $captured = null;
    $dispatcher = kitTestDispatcher($captured);

    // Pre-create a consumer that won't actually call SQS (use --once
    // with the parser-throwing case won't work — we need the consumer
    // to call receiveMessage, get 0 messages, and exit. Verify via
    // the dispatch() path instead, by faking a populated receive).
    $registry = new QueueBindingRegistry;
    $registry->register(new QueueBinding(
        name: 'custom-parser-binding',
        queueUrl: 'https://sqs.example.com/custom',
        envelopeParserClass: $parser::class,
    ));

    // We can't easily run the full command without faking SqsClient.
    // Verify the command resolves the parser class via reflection.
    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: new ConsumerConfig,
    );

    $command = new SqsConsumeCommand($consumer, $registry, $dispatcher);

    // The command resolves the binding, then constructs the parser
    // via `new $binding->envelopeParserClass`. We can confirm the
    // parser class is correctly passed through by invoking handle()
    // with a mock setup. For now, verify the command's resolution
    // path via the binding name passed to handle() indirectly.
    $reflection = new ReflectionMethod($command, 'resolveParser');
    $reflection->setAccessible(true);
    $binding = $registry->get('custom-parser-binding');
    $resolved = $reflection->invoke($command, $binding);

    expect($resolved)->toBeInstanceOf(EnvelopeParser::class)
        ->and($resolved::class)->toBe($parser::class);
});

it('falls back to the global dispatcher when the binding has no dispatcherClass', function (): void {
    $captured = null;
    $dispatcher = kitTestDispatcher($captured);

    $registry = new QueueBindingRegistry;
    $registry->register(new QueueBinding(
        name: 'no-dispatcher',
        queueUrl: 'https://sqs.example.com/n',
    ));

    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: new ConsumerConfig,
    );
    $command = new SqsConsumeCommand($consumer, $registry, $dispatcher);

    // Verify the resolveDispatcher() path returns the global one
    // when the binding doesn't override.
    $reflection = new ReflectionMethod($command, 'resolveDispatcher');
    $reflection->setAccessible(true);
    $binding = $registry->get('no-dispatcher');
    $resolved = $reflection->invoke($command, $binding);

    expect($resolved)->toBe($dispatcher);
});

it('uses the binding\'s dispatcher when one is declared', function (): void {
    $bindingDispatcher = kitTestDispatcher();
    $globalDispatcher = kitTestDispatcher();

    $registry = new QueueBindingRegistry;
    $registry->register(new QueueBinding(
        name: 'custom-dispatcher',
        queueUrl: 'https://sqs.example.com/c',
        dispatcherClass: $bindingDispatcher::class,
    ));

    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: new ConsumerConfig,
    );
    $command = new SqsConsumeCommand($consumer, $registry, $globalDispatcher);

    $reflection = new ReflectionMethod($command, 'resolveDispatcher');
    $reflection->setAccessible(true);
    $binding = $registry->get('custom-dispatcher');
    $resolved = $reflection->invoke($command, $binding);

    // Note: app() resolution will instantiate a NEW anonymous class
    // each call (they're not the same instance even if same source),
    // but they share the same class string. Verify class string.
    expect($resolved::class)->toBe($bindingDispatcher::class);
});

it('falls back to legacy config(aws-kit.sqs.consumer.queue_url) when no bindings are registered', function (): void {
    config()->set('aws-kit.sqs.consumer.queue_url', 'https://sqs.example.com/legacy');

    $sqsClient = Mockery::mock(SqsClient::class);
    $sqsClient->shouldReceive('receiveMessage')
        ->once()
        ->withArgs(function (array $args) {
            expect($args['QueueUrl'])->toBe('https://sqs.example.com/legacy');

            return true;
        })
        ->andReturn(new Result(['Messages' => []]));

    $registry = new QueueBindingRegistry;
    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: new ConsumerConfig,
    );
    $reflection = new ReflectionProperty($consumer, 'client');
    $reflection->setValue($consumer, $sqsClient);
    $dispatcher = kitTestDispatcher();

    $command = new SqsConsumeCommand($consumer, $registry, $dispatcher);
    $command->setLaravel($this->app);

    $tester = new CommandTester($command);
    $tester->execute(['--once' => true]);

    expect($tester->getStatusCode())->toBe(0);
});

it('throws when no bindings AND no legacy config URL is set', function (): void {
    config()->set('aws-kit.sqs.consumer.queue_url', null);

    $registry = new QueueBindingRegistry;
    $consumer = new Consumer(
        clientFactory: new SqsClientFactory,
        config: new ConsumerConfig,
    );
    $dispatcher = kitTestDispatcher();

    $command = new SqsConsumeCommand($consumer, $registry, $dispatcher);
    $command->setLaravel($this->app);

    $tester = new CommandTester($command);
    $tester->execute(['--once' => true]);
})->throws(RuntimeException::class, 'No QueueBindings are registered AND config');
