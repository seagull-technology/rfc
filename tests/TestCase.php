<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Mcamara\LaravelLocalization\LaravelLocalization;

abstract class TestCase extends BaseTestCase
{
    protected function fakePdf(string $name, int $kilobytes = 1): UploadedFile
    {
        $minimum = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n";
        $targetBytes = max(1, $kilobytes) * 1024;
        $padding = str_repeat("% RFC test document\n", (int) ceil($targetBytes / 20));
        $contents = substr($minimum.$padding, 0, max(strlen($minimum), $targetBytes - 6))."\n%%EOF";

        return UploadedFile::fake()->createWithContent($name, $contents);
    }

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
