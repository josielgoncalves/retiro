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

    public function manifest(Request $request): JsonResponse
    {
        $enrollment = $request->attributes->get('portalEnrollment')->loadMissing('retreat');
        $token = (string) $request->route('token');
        $portalPath = '/c/'.rawurlencode($token);

        return response()->json([
            'id' => $portalPath,
            'name' => 'Portal do '.$enrollment->retreat->name,
            'short_name' => 'Meu Retiro',
            'description' => 'Portal individual do casal para acompanhamento do retiro.',
            'start_url' => $portalPath,
            'scope' => '/c/',
            'display' => 'standalone',
            'background_color' => '#FAF6EC',
            'theme_color' => '#28594D',
            'icons' => [
                ['src' => '/icons/pwa-icon-192x192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/icons/pwa-icon-512x512.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }
}
