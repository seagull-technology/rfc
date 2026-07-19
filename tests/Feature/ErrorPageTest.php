<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    public function test_missing_arabic_page_uses_the_branded_error_view(): void
    {
        $this->refreshApplicationWithLocale('ar');
        config(['app.debug' => false]);

        $this->get('/ar/__missing-rfc-page')
            ->assertNotFound()
            ->assertSeeText('تعذر العثور على هذه الصفحة')
            ->assertSeeText('الصفحة الرئيسية')
            ->assertSee('error-shell', false)
            ->assertSee('images/logo.svg', false)
            ->assertSee('DIN Next LT Arabic Regular', false)
            ->assertDontSee('&#039;DIN Next LT Arabic Regular&#039;', false)
            ->assertDontSee('images/loginBg.jpeg', false);
    }

    public function test_missing_english_page_uses_the_branded_error_view(): void
    {
        $this->refreshApplicationWithLocale('en');
        config(['app.debug' => false]);

        $this->get('/en/__missing-rfc-page')
            ->assertNotFound()
            ->assertSeeText('We could not find this page')
            ->assertSeeText('Home page')
            ->assertSee('error-shell', false);
    }

    public function test_server_error_uses_the_branded_error_view(): void
    {
        $this->refreshApplicationWithLocale('en');
        config(['app.debug' => true]);

        Route::get('/en/__rfc-error-page-test', static fn () => abort(500));

        $this->get('/en/__rfc-error-page-test')
            ->assertStatus(500)
            ->assertSeeText('Something went wrong')
            ->assertSeeText('Error 500')
            ->assertSee('error-shell', false);
    }

    public function test_json_errors_keep_the_api_response_format(): void
    {
        $this->refreshApplicationWithLocale('en');
        config(['app.debug' => true]);

        $this->getJson('/en/__missing-rfc-api-endpoint')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json');
    }
}
