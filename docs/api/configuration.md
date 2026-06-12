# Configuration

## Config File

After publishing (see [installation.md](installation.md)), `config/aws-kit.php` is yours to edit:

```php
return [
    'aws' => [
        'region' => env('AWS_REGION', 'ap-southeast-1'),
        'endpoint' => env('AWS_ENDPOINT'),
        'credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
    ],

    'eventbridge' => [
        'event_bus_name' => env('EVENTBRIDGE_BUS_NAME', 'veritypos-domain-events'),
        'source_prefix' => env('EVENTBRIDGE_SOURCE_PREFIX', 'veritypos'),
        'region' => env('EVENTBRIDGE_REGION', 'ap-southeast-1'),
        'endpoint' => env('EVENTBRIDGE_ENDPOINT'),
    ],
];
```

## Environment Variables

### General AWS

| Var | Default | Purpose |
|---|---|---|
| `AWS_REGION` | `ap-southeast-1` | AWS region. Used by the SDK as the default. |
| `AWS_ENDPOINT` | _(null)_ | Override for LocalStack (`http://veritypos-localstack:4566`). Auto-detected from the queue URL too. |
| `AWS_ACCESS_KEY_ID` | _(null)_ | Explicit credentials. In prod, leave unset and let the SDK use the ECS task role / EC2 instance role. |
| `AWS_SECRET_ACCESS_KEY` | _(null)_ | Same as above. |

### EventBridge

| Var | Default | Purpose |
|---|---|---|
| `EVENTBRIDGE_BUS_NAME` | `veritypos-domain-events` | The bus to publish to / read rules from. |
| `EVENTBRIDGE_SOURCE_PREFIX` | `veritypos` | Prepended to the source (so a publish of `auth` becomes `veritypos.auth`). The eventbridge rule pattern must match this prefix. |
| `EVENTBRIDGE_REGION` | `AWS_REGION` | EventBridge region (usually same as AWS_REGION). |
| `EVENTBRIDGE_ENDPOINT` | _(null)_ | Override for LocalStack. |

### SQS

| Var | Default | Purpose |
|---|---|---|
| `SQS_QUEUE_URL` | _(none)_ | The queue to consume from. Passed as `--queue=...` to `aws-kit:sqs-consume`. Not read from config — supplied per-process. |
| (other SQS env) | _AWS SDK defaults_ | Uses the standard `AWS_*` env vars (region, credentials, endpoint). |

## Recommended Production Settings

```dotenv
AWS_REGION=ap-southeast-1
# No AWS_ENDPOINT (real AWS)
# No AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY (use the Fargate task role)
EVENTBRIDGE_BUS_NAME=veritypos-production-veritypos-domain-events
EVENTBRIDGE_SOURCE_PREFIX=veritypos
```

## Local Development Settings

```dotenv
AWS_REGION=ap-southeast-1
AWS_ENDPOINT=http://veritypos-localstack:4566
AWS_ACCESS_KEY_ID=test
AWS_SECRET_ACCESS_KEY=test
EVENTBRIDGE_BUS_NAME=veritypos-domain-events
EVENTBRIDGE_SOURCE_PREFIX=veritypos
EVENTBRIDGE_ENDPOINT=http://veritypos-localstack:4566
```

LocalStack ignores the credentials but the SDK still requires them to be set when `endpoint` is overridden.
