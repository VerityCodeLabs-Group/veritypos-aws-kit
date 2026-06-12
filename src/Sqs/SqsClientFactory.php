<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Sqs;

use Aws\Sqs\SqsClient;
use InvalidArgumentException;

/**
 * Builds configured SqsClient instances.
 *
 * The factory handles the LocalStack-vs-prod distinction by inspecting
 * the queue URL: if it points to a localstack host or :4566, an
 * explicit endpoint override + dummy credentials are applied.
 *
 * For prod, the SDK's default credential chain (env vars / task role /
 * instance role) is used.
 */
final class SqsClientFactory
{
    /**
     * @param  string                $queueUrl
     * @param  string                $region
     * @param  string|null           $endpoint  null in prod; LocalStack URL in dev
     * @param  array{key: string, secret: string}|null  $credentials
     */
    public function create(
        string $queueUrl,
        string $region = 'ap-southeast-1',
        ?string $endpoint = null,
        ?array $credentials = null,
    ): SqsClient {
        if ($queueUrl === '') {
            throw new InvalidArgumentException('Queue URL cannot be empty');
        }

        $config = [
            'region' => $region,
            'version' => 'latest',
        ];

        $resolvedEndpoint = $this->resolveEndpoint($queueUrl, $endpoint);

        if ($resolvedEndpoint !== null) {
            $config['endpoint'] = $resolvedEndpoint;
            $config['credentials'] = $credentials ?? ['key' => 'test', 'secret' => 'test'];
        } elseif ($credentials !== null) {
            $config['credentials'] = $credentials;
        }
        // else: SDK will use the default credential provider chain

        return new SqsClient($config);
    }

    private function resolveEndpoint(string $queueUrl, ?string $endpoint): ?string
    {
        if ($endpoint !== null && $endpoint !== '') {
            return $endpoint;
        }

        if ($this->isLocalStackUrl($queueUrl)) {
            return $this->extractEndpointFromUrl($queueUrl);
        }

        return null;
    }

    private function isLocalStackUrl(string $url): bool
    {
        return str_contains($url, 'localstack') || str_contains($url, ':4566');
    }

    private function extractEndpointFromUrl(string $url): string
    {
        $parsed = parse_url($url);

        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'])) {
            throw new InvalidArgumentException("Invalid queue URL: {$url}");
        }

        $port = isset($parsed['port']) ? ":{$parsed['port']}" : '';

        return "{$parsed['scheme']}://{$parsed['host']}{$port}";
    }
}
