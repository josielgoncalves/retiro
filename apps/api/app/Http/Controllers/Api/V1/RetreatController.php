<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Retreat\StoreRetreatRequest;
use App\Http\Requests\Retreat\UpdateRetreatRequest;
use App\Http\Resources\RetreatResource;
use App\Models\Retreat;
use Illuminate\Http\Request;

class RetreatController extends Controller
{
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
        $retreat = Retreat::create($request->validated());

        return (new RetreatResource($retreat))->response()->setStatusCode(201);
    }

    public function show(Retreat $retreat)
    {
        $this->authorize('view', $retreat);

        return new RetreatResource($retreat->loadCount('enrollments'));
    }

    public function update(UpdateRetreatRequest $request, Retreat $retreat)
    {
        $retreat->update($request->validated());

        return new RetreatResource($retreat);
    }
}
