<?php

namespace SajjadHossain\Doctor\Tests\Unit\Checks\Routes;

use SajjadHossain\Doctor\Tests\TestCase;
use SajjadHossain\Doctor\Checks\Routes\MissingControllerMethodCheck;
use SajjadHossain\Doctor\Enums\Severity;
use Illuminate\Support\Facades\Route;

class MissingControllerMethodCheckTest extends TestCase
{
    /** @test */
    public function it_detects_missing_method_on_existing_controller(): void
    {
        // Create a real controller class so class_exists passes but method doesn't
        if (!class_exists('App\\Http\\Controllers\\TestController')) {
            class_alias(\SajjadHossain\Doctor\Tests\Fixtures\App\Http\Controllers\EmptyController::class, 'App\\Http\\Controllers\\TestController');
        }

        Route::get('/missing-method', 'App\\Http\\Controllers\\TestController@nonExistentMethod')
            ->name('missingMethod');

        $result = (new MissingControllerMethodCheck())->run();

        $this->assertCheckFailed($result, Severity::Error);
    }

    /** @test */
    public function it_passes_when_controller_method_exists(): void
    {
        Route::get('/ok', fn () => '')->name('ok');

        $result = (new MissingControllerMethodCheck())->run();

        $this->assertCheckPassed($result);
    }
}
