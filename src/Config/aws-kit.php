<?php

declare(strict_types=1);

return [
    'aws' => [
        'region' => env('AWS_REGION', 'ap-southeast-1'),
        'endpoint' => env('AWS_ENDPOINT'),  // null in prod; LocalStack URL in dev
        'credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
    ],

    'eventbridge' => [
        'event_bus_name' => env('EVENTBRIDGE_BUS_NAME', 'veritypos-domain-events'),
        'source_prefix' => env('EVENTBRIDGE_SOURCE_PREFIX', 'veritypos'),
        'region' => env('AWS_REGION', 'ap-southeast-1'),
        'endpoint' => env('EVENTBRIDGE_ENDPOINT'),
    ],

    'sqs' => [
        'consumer' => [
            // Queue URL for the aws-kit:sqs-consume command. Read by
            // default; --queue= on the CLI overrides this. Set in the
            // consumer service's published config/aws-kit.php.
            'queue_url' => env('AWS_KIT_SQS_CONSUMER_QUEUE_URL'),
        ],
    ],
];
