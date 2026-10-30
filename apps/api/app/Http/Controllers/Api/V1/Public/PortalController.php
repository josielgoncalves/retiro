<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Portal\PortalAnnouncementResource;
use App\Http\Resources\Portal\PortalEnrollmentResource;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $enrollment = $request->attributes->get('portalEnrollment')
            ->load(['couple', 'retreat', 'installments.allocations.payment', 'activePayments']);

        return response()->json(['data' => new PortalEnrollmentResource($enrollment)]);
    }

    public function announcements(Request $request): JsonResponse
    {
        $enrollment = $request->attributes->get('portalEnrollment');

        $announcements = Announcement::where('retreat_id', $enrollment->retreat_id)
            ->visible()
            ->orderByDesc('published_at')
            ->get();

        return response()->json(['data' => PortalAnnouncementResource::collection($announcements)]);
    }
}
