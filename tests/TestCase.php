<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mcamara\LaravelLocalization\LaravelLocalization;

abstract class TestCase extends BaseTestCase
{
    protected function refreshApplicationWithLocale(string $locale): void
    {
        $this->tearDown();
        putenv(LaravelLocalization::ENV_ROUTE_KEY.'='.$locale);
        $this->setUp();
    }

    protected function tearDown(): void
    {
        putenv(LaravelLocalization::ENV_ROUTE_KEY);

        parent::tearDown();
    }
}
