<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Announcement\StoreAnnouncementRequest;
use App\Http\Requests\Announcement\UpdateAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function __construct(protected AuditLogger $auditLogger) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Announcement::class);

        $query = Announcement::query();

        if ($retreatId = $request->integer('retreat_id')) {
            $query->where('retreat_id', $retreatId);
        }

        $announcements = $query->orderByDesc('created_at')->paginate($request->integer('per_page', 15));

        return AnnouncementResource::collection($announcements);
    }

    public function store(StoreAnnouncementRequest $request)
    {
        $announcement = Announcement::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return (new AnnouncementResource($announcement))->response()->setStatusCode(201);
    }

    public function show(Announcement $announcement)
    {
        $this->authorize('view', $announcement);

        return new AnnouncementResource($announcement);
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement)
    {
        $announcement->update($request->validated());

        return new AnnouncementResource($announcement);
    }

    public function publish(Request $request, Announcement $announcement)
    {
        $this->authorize('update', $announcement);

        $announcement->update(['published_at' => now()]);

        $this->auditLogger->log('announcement.published', $announcement, null, null, $request->user());

        return new AnnouncementResource($announcement);
    }

    public function unpublish(Request $request, Announcement $announcement)
    {
        $this->authorize('update', $announcement);

        $announcement->update(['published_at' => null]);

        $this->auditLogger->log('announcement.unpublished', $announcement, null, null, $request->user());

        return new AnnouncementResource($announcement);
    }
}
