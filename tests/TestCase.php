<?php

declare(strict_types=1);

namespace VerityPOS\AwsKit\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use VerityPOS\AwsKit\Providers\AwsKitServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AwsKitServiceProvider::class,
        ];
    }
}
