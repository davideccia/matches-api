<?php

namespace App\Http\Controllers;

use App\Http\Requests\Media\MediaDestroyRequest;
use App\Http\Requests\Media\MediaDownloadRequest;
use App\Models\Media;
use Illuminate\Http\JsonResponse;

class MediaController extends Controller
{
    public function download(MediaDownloadRequest $request, Media $media): Media
    {
        return $media;
    }

    public function destroy(MediaDestroyRequest $request, Media $media): JsonResponse
    {
        $media->delete();

        return response()->json([], 204);
    }
}
