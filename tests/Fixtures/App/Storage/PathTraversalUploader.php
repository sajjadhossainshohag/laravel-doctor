<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Storage;

use Illuminate\Http\UploadedFile;

class PathTraversalUploader
{
    public function uploadBad(UploadedFile $file): string
    {
        return $file->storeAs('uploads', '../../etc/passwd');
    }

    public function uploadAbsolute(UploadedFile $file): string
    {
        return $file->storeAs('', '/var/www/uploads/file.txt');
    }

    public function uploadVariable(UploadedFile $file, string $path, string $name): string
    {
        return $file->storeAs($path, $name);
    }
}
