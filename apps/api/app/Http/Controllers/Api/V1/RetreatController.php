<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Retreat\StoreRetreatRequest;
use App\Http\Requests\Retreat\UpdateRetreatRequest;
use App\Http\Resources\RetreatResource;
use App\Models\Retreat;
use App\Services\AuditLogger;
use App\Services\RetreatMediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RetreatController extends Controller
{
    public function __construct(
        protected RetreatMediaService $mediaService,
        protected AuditLogger $auditLogger,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Retreat::class);

        $retreats = Retreat::withCount('enrollments')
            ->orderByDesc('start_date')
            ->paginate($request->integer('per_page', 15));

        return RetreatResource::collection($retreats);
    }

    public function store(StoreRetreatRequest $request)
    {
        $data = $request->safe()->except(['cover_image']);
        $retreat = DB::transaction(function () use ($request, $data) {
            $retreat = Retreat::create($data);

            if ($request->hasFile('cover_image')) {
                $this->mediaService->replaceCover($retreat, $request->file('cover_image'));
            }

            return $retreat->refresh();
        });

        $this->auditLogger->log('retreat.created', $retreat, null, $retreat->toArray(), $request->user());

        return (new RetreatResource($retreat))->response()->setStatusCode(201);
    }

    public function show(Retreat $retreat)
    {
        $this->authorize('view', $retreat);

        return new RetreatResource($retreat->loadCount('enrollments'));
    }

    public function update(UpdateRetreatRequest $request, Retreat $retreat)
    {
        $before = $retreat->toArray();
        $data = $request->safe()->except(['cover_image', 'remove_cover_image']);

        DB::transaction(function () use ($request, $retreat, $data) {
            $retreat->update($data);

            if ($request->hasFile('cover_image')) {
                $this->mediaService->replaceCover($retreat, $request->file('cover_image'));
            } elseif ($request->boolean('remove_cover_image')) {
                $this->mediaService->removeCover($retreat);
            }
        });

        $retreat->refresh();
        $this->auditLogger->log('retreat.updated', $retreat, $before, $retreat->toArray(), $request->user());

        return new RetreatResource($retreat);
    }

    public function cover(Retreat $retreat): BinaryFileResponse
    {
        abort_unless($retreat->cover_image_path && Storage::disk(RetreatMediaService::DISK)->exists($retreat->cover_image_path), 404);

        return response()->file(Storage::disk(RetreatMediaService::DISK)->path($retreat->cover_image_path), [
            'Content-Type' => Storage::disk(RetreatMediaService::DISK)->mimeType($retreat->cover_image_path),
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
