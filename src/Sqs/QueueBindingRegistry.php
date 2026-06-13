<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Sqs;

/**
 * Registry of named SQS consumer bindings.
 *
 * Service providers register one `QueueBinding` per queue the
 * service consumes from. The kit's `AwsKitServiceProvider::boot()`
 * reads this registry to auto-register one `SqsConsumeCommand`
 * Artisan command per binding (named `aws-kit:sqs-consume-<name>`).
 *
 * Bindings are config, not behavior: the registry holds the DTOs
 * exactly as registered. The actual long-poll loop lives in
 * `Sqs\SqsConsumer` (instantiated fresh per command invocation
 * with the binding's resolved config).
 *
 * Service code shouldn't reference this class directly — service
 * providers register bindings at boot time, and the SqsConsumeCommand
 * reads them at command time.
 */
final class QueueBindingRegistry
{
    /** @var array<string, QueueBinding> */
    private array $bindings = [];

    public function register(QueueBinding $binding): void
    {
        if (isset($this->bindings[$binding->name])) {
            throw new \InvalidArgumentException(sprintf(
                'QueueBinding %s is already registered. Each binding must have a unique name.',
                $binding->name,
            ));
        }

        $this->bindings[$binding->name] = $binding;
    }

    public function get(string $name): QueueBinding
    {
        if (! isset($this->bindings[$name])) {
            throw new \InvalidArgumentException(sprintf(
                'No QueueBinding registered for name %s. Registered: [%s]',
                $name,
                implode(', ', array_keys($this->bindings)),
            ));
        }

        return $this->bindings[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->bindings[$name]);
    }

    /**
     * @return array<string, QueueBinding>
     */
    public function all(): array
    {
        return $this->bindings;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->bindings);
    }
}
