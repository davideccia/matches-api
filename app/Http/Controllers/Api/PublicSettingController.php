<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSetting\PublicSettingLogoShowRequest;
use App\Support\AppLogo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicSettingController extends Controller
{
    public function logo(PublicSettingLogoShowRequest $request): StreamedResponse
    {
        $path = AppLogo::path();

        abort_if($path === null, 404);

        return Storage::disk('public')->response($path);
    }
}
