<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiRequestLog\ApiRequestLogIndexRequest;
use App\Http\Resources\ApiRequestLogResource;
use App\Models\ApiRequestLog;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Carbon;

class ApiRequestLogController extends Controller
{
    /**
     * Always paginated, unlike the other index endpoints: the table is
     * append-only and unbounded between prune runs.
     */
    public function index(ApiRequestLogIndexRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        $logs = ApiRequestLog::with($validated['with'] ?? [])->latest('created_at');

        if (isset($validated['user_id'])) {
            $logs->ofUser($validated['user_id']);
        }

        if (isset($validated['method'])) {
            $logs->ofMethod($validated['method']);
        }

        if (isset($validated['status'])) {
            $logs->ofStatus((int) $validated['status']);
        }

        if (isset($validated['status_class'])) {
            $logs->ofStatusClass((int) $validated['status_class']);
        }

        if (isset($validated['path'])) {
            $logs->pathContains($validated['path']);
        }

        if (isset($validated['date_from'])) {
            $logs->createdFrom(Carbon::parse($validated['date_from']));
        }

        if (isset($validated['date_to'])) {
            $logs->createdUntil(Carbon::parse($validated['date_to']));
        }

        return ApiRequestLogResource::collection(
            $logs->paginate(($validated['per_page'] ?? null), ['*'], 'page', ($validated['page'] ?? null))
        );
    }
}
