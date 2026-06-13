<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Sqs;

/**
 * Registry of named `SqsPublisherBinding` configs.
 *
 * Symmetric to `QueueBindingRegistry` but for the publisher side.
 * Service providers register one `SqsPublisherBinding` per queue
 * the service publishes to. Call sites resolve publishers by name:
 *
 *   $publisher = app(SqsPublisherFactory::class)
 *       ->make(app(SqsPublisherRegistry::class)->get('sync-events'));
 *
 *   $publisher->publish($body, $attrs);
 *
 * The registry is **just the config** — it doesn't hold publisher
 * instances. Construction is lazy and cached in
 * `SqsPublisherFactory` (per-process, singleton).
 */
final class SqsPublisherRegistry
{
    /** @var array<string, SqsPublisherBinding> */
    private array $bindings = [];

    public function register(SqsPublisherBinding $binding): void
    {
        if (isset($this->bindings[$binding->name])) {
            throw new \InvalidArgumentException(sprintf(
                'SqsPublisherBinding %s is already registered. Each binding must have a unique name.',
                $binding->name,
            ));
        }

        $this->bindings[$binding->name] = $binding;
    }

    public function get(string $name): SqsPublisherBinding
    {
        if (! isset($this->bindings[$name])) {
            throw new \InvalidArgumentException(sprintf(
                'No SqsPublisherBinding registered for name %s. Registered: [%s]',
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
     * @return array<string, SqsPublisherBinding>
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
