<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Livewire\V3\BrokenCounter;

/**
 * Livewire v3 component (in app/Livewire/). The view has a wire:click
 * referencing 'nonExistentMethod' which is NOT defined here.
 *
 * Standalone class — no Livewire Component base class needed; the
 * check uses reflection and just needs the public methods.
 *
 * Sets the `view` property explicitly so the check can find the
 * fixture Blade view via the package's `view.paths` config.
 */
class V3BrokenCounter
{
    public string $view = 'livewire.v3-broken-counter';

    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }
}