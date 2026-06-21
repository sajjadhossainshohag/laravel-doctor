<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Storage;

use Illuminate\Support\Facades\Storage;

class FileStorageService
{
    public function getFile(string $path): string
    {
        return Storage::disk('undefined-disk')->url($path);
    }
}
