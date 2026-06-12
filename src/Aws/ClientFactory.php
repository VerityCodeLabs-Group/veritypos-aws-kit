<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Aws;

use Aws\AwsClient;
use Aws\Credentials\Credentials;

/**
 * Builds configured AWS SDK clients.
 *
 * The same factory is reused for every AWS service in this package
 * (EventBridge, SQS, SNS, CloudWatch, etc.). It applies the standard
 * configuration: region, optional endpoint override (LocalStack), and
 * credentials.
 *
 * Credentials resolution order (matches AWS SDK defaults):
 *   1. Explicit `key` + `secret` passed to the factory
 *   2. AWS_ACCESS_KEY_ID + AWS_SECRET_ACCESS_KEY env vars
 *   3. ECS task role / EC2 instance role / etc.
 *
 * In production, we rely on (3) — the Fargate task role provides creds.
 * For LocalStack, we pass (1) explicitly with dummy "test" / "test" creds
 * (LocalStack doesn't validate them).
 */
final class ClientFactory
{
    /**
     * @param  string                $service  the AWS service identifier (e.g. "eventbridge", "sqs")
     * @param  string                $region
     * @param  string|null           $endpoint  null in prod; LocalStack URL in dev
     * @param  array{key: string, secret: string}|null  $credentials
     */
    public function build(
        string $service,
        string $region = 'ap-southeast-1',
        ?string $endpoint = null,
        ?array $credentials = null,
    ): AwsClient {
        $config = [
            'region' => $region,
            'version' => 'latest',
            'service' => $service,
        ];

        if ($endpoint !== null && $endpoint !== '') {
            $config['endpoint'] = $endpoint;
            // LocalStack ignores credentials, but the SDK still requires them
            // to be set when an endpoint is overridden.
            $config['credentials'] = $credentials ?? ['key' => 'test', 'secret' => 'test'];
        } elseif ($credentials !== null) {
            $config['credentials'] = new Credentials($credentials['key'], $credentials['secret']);
        }

        // else: SDK will use the default credential provider chain (env, role, etc.)

        return new AwsClient($config);
    }
}
