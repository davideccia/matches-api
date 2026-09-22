<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\SettingLogoDestroyRequest;
use App\Http\Requests\Setting\SettingLogoShowRequest;
use App\Http\Requests\Setting\SettingLogoStoreRequest;
use App\Support\AppLogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingController extends Controller
{
    public function logo(SettingLogoShowRequest $request): StreamedResponse
    {
        $path = AppLogo::path();

        abort_if($path === null, 404);

        return Storage::disk('public')->response($path);
    }

    public function updateLogo(SettingLogoStoreRequest $request): JsonResponse
    {
        AppLogo::store($request->file('logo'));

        return response()->json([], 204);
    }

    public function destroyLogo(SettingLogoDestroyRequest $request): JsonResponse
    {
        abort_if(AppLogo::path() === null, 404);

        AppLogo::clear();

        return response()->json([], 204);
    }
}
