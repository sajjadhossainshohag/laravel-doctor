<?php

namespace SajjadHossain\Doctor\Tests\Fixtures\App\Storage;

use Illuminate\Support\Facades\Storage;

class S3UrlService
{
    public function getUrl(): string
    {
        return Storage::disk('s3')->url('avatars/photo.jpg');
    }

    public function getTemporaryUrl(): string
    {
        return Storage::disk('s3')->url('documents/report.pdf');
    }
}
