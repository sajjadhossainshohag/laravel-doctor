<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Http\Livewire\V2\BrokenCounter;

/**
 * Livewire v2 component (in app/Http/Livewire/). The view has a
 * wire:click referencing 'nonExistentMethod' which is NOT defined.
 *
 * Bug regression: with the OLD hardcoded `app_path('Livewire')` path,
 * this component was silently skipped — a Livewire v2 project would
 * have passed the check with no work done.
 *
 * Sets the `view` property explicitly so the check can find the
 * fixture Blade view via the package's `view.paths` config.
 */
class V2BrokenCounter
{
    public string $view = 'livewire.v2-broken-counter';

    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }
}