<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Couple\StoreCoupleRequest;
use App\Http\Requests\Couple\UpdateCoupleRequest;
use App\Http\Resources\CoupleResource;
use App\Models\Couple;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CoupleController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Couple::class);

        $query = Couple::query();

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('partner_one_name', 'ilike', "%{$search}%")
                    ->orWhere('partner_two_name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $retreatId = $request->integer('retreat_id');
        $paymentMode = $request->string('payment_mode')->value();
        $financialStatus = $request->string('financial_status')->value();

        if ($retreatId || $paymentMode || $financialStatus) {
            $query->whereHas('enrollments', function (Builder $q) use ($retreatId, $paymentMode, $financialStatus) {
                if ($retreatId) {
                    $q->where('retreat_id', $retreatId);
                }

                if ($paymentMode) {
                    $q->where('payment_mode', $paymentMode);
                }

                if ($financialStatus) {
                    $paidSubquery = DB::table('payments')
                        ->selectRaw('COALESCE(SUM(amount_cents), 0)')
                        ->whereColumn('enrollment_id', 'enrollments.id')
                        ->whereNull('reversed_at');

                    match ($financialStatus) {
                        'paid' => $q->where('total_amount_cents', '<=', $paidSubquery),
                        'pending' => $q->where('total_amount_cents', '>', $paidSubquery),
                        default => null,
                    };
                }
            });
        }

        $sort = $request->string('sort', 'partner_one_name')->value();
        $direction = $request->string('direction', 'asc')->value() === 'desc' ? 'desc' : 'asc';

        if (in_array($sort, ['partner_one_name', 'partner_two_name', 'created_at'], true)) {
            $query->orderBy($sort, $direction);
        }

        $couples = $query->paginate($request->integer('per_page', 15));

        return CoupleResource::collection($couples);
    }

    public function store(StoreCoupleRequest $request)
    {
        $couple = Couple::create($request->validated());

        return (new CoupleResource($couple))->response()->setStatusCode(201);
    }

    public function show(Couple $couple)
    {
        $this->authorize('view', $couple);

        return new CoupleResource($couple);
    }

    public function update(UpdateCoupleRequest $request, Couple $couple)
    {
        $couple->update($request->validated());

        return new CoupleResource($couple);
    }
}
