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
        'region' => env('EVENTBRIDGE_REGION', 'ap-southeast-1'),
        'endpoint' => env('EVENTBRIDGE_ENDPOINT'),
    ],

    'sqs' => [
        // Default single-consumer config (v0.4.x style, kept for
        // backward compat). New code should register named bindings
        // via QueueBindingRegistry from a service provider. The
        // SqsConsumeCommand auto-picks the binding (single-binding
        // case) or requires --binding=NAME (multi-binding case).
        'consumer' => [
            'queue_url' => env('AWS_KIT_SQS_CONSUMER_QUEUE_URL'),
        ],
    ],
];
