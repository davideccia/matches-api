<?php

namespace App\Http\Controllers\Api;

use App\Actions\StoreTemporaryUploadAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\TemporaryUpload\TemporaryUploadStoreRequest;
use App\Http\Resources\TemporaryFileResource;

class TemporaryUploadController extends Controller
{
    public function store(TemporaryUploadStoreRequest $request): TemporaryFileResource
    {
        $temporaryFile = StoreTemporaryUploadAction::handle($request->file('file'));

        return new TemporaryFileResource($temporaryFile);
    }
}
