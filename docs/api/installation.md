# Installation

## Composer

The package lives in a private GitHub repo. Configure Composer to fetch it via VCS:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:VerityCodeLabs-Group/veritypos-aws-kit.git"
        }
    ],
    "require": {
        "veritypos/aws-kit": "^0.2"
    }
}
```

Or via the GitHub HTTPS URL if you don't have SSH keys configured:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/VerityCodeLabs-Group/veritypos-aws-kit.git"
        }
    ]
}
```

## Service Provider

Laravel auto-discovers the service provider via the `extra.laravel.providers` field in our `composer.json`. No manual registration needed.

If you've disabled package discovery, add this to `config/app.php`:

```php
'providers' => [
    // ...
    VerityPOS\AwsKit\Providers\AwsKitServiceProvider::class,
],
```

## Publish the Config

```bash
php artisan vendor:publish --tag=aws-kit-config
```

This creates `config/aws-kit.php` in your application. Override any of the values — the service provider uses `mergeConfigFrom` so your overrides win.

## Verify

```bash
php artisan list | grep aws-kit
```

You should see:
- `aws-kit:event-bridge-invoke` — CLI simulator
- `aws-kit:sqs-consume` — Fargate supervisord worker

## Next Steps

- [Configuration](configuration.md) — environment variables
- [Publishing Events](publishing-events.md) — using `EventBridgePublisher`
- [Consuming Events](consuming-events.md) — Fargate supervisord + Lambda + CLI
