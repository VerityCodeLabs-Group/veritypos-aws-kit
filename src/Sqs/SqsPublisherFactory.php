<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Sqs;

/**
 * Factory that builds `SqsPublisher` instances from `SqsPublisherBinding`
 * configs.
 *
 * Publishers are constructed on first access (per binding name) and
 * cached. The cache lives in the singleton `SqsPublisherFactory`
 * instance, so each Laravel application process has its own cache
 * (one cache per Fargate task).
 *
 * The factory does NOT register publishers globally — that's the
 * registry's job (see `SqsPublisherRegistry` for the consumer-side
 * equivalent). This class is the *builder*.
 */
final class SqsPublisherFactory
{
    /** @var array<string, SqsPublisher> */
    private array $cache = [];

    public function __construct(
        private readonly SqsClientFactory $clientFactory,
    ) {}

    public function make(SqsPublisherBinding $binding): SqsPublisher
    {
        if (isset($this->cache[$binding->name])) {
            return $this->cache[$binding->name];
        }

        return $this->cache[$binding->name] = new SqsPublisher(
            clientFactory: $this->clientFactory,
            queueUrl: $binding->queueUrl,
            region: $binding->region,
            endpoint: $binding->endpoint,
        );
    }

    /**
     * Drop a cached publisher (e.g. after the queue URL is reconfigured
     * at runtime). The next `make()` call rebuilds from the binding.
     */
    public function forget(string $name): void
    {
        unset($this->cache[$name]);
    }
}
