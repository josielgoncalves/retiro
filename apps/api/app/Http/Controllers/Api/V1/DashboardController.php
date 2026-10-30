<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Retreat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $retreatId = $request->integer('retreat_id');

        if (! $retreatId) {
            throw ValidationException::withMessages([
                'retreat_id' => ['Selecione um retiro para ver o dashboard.'],
            ]);
        }

        $retreat = Retreat::findOrFail($retreatId);

        $this->authorize('view', $retreat);

        $enrollments = Enrollment::where('retreat_id', $retreat->id)
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->with(['payments' => fn ($q) => $q->whereNull('reversed_at'), 'installments'])
            ->get();

        $totalContracted = (int) $enrollments->sum('total_amount_cents');
        $totalReceived = (int) $enrollments->sum(fn (Enrollment $e) => $e->totalPaidCents());
        $settledCount = $enrollments->filter(fn (Enrollment $e) => $e->balanceCents() === 0)->count();

        $overdueCents = $enrollments
            ->filter(fn (Enrollment $e) => $e->isFixed())
            ->flatMap(fn (Enrollment $e) => $e->installments)
            ->filter(fn (Installment $i) => $i->status() === Installment::STATUS_OVERDUE)
            ->sum(fn (Installment $i) => $i->balanceCents());

        $lastPayments = \App\Models\Payment::whereIn('enrollment_id', $enrollments->pluck('id'))
            ->whereNull('reversed_at')
            ->with(['enrollment.couple', 'createdBy'])
            ->orderByDesc('paid_at')
            ->limit(10)
            ->get();

        $receiptsByMonth = \App\Models\Payment::whereIn('enrollment_id', $enrollments->pluck('id'))
            ->whereNull('reversed_at')
            ->where('paid_at', '>=', now()->subMonths(5)->startOfMonth())
            ->get()
            ->groupBy(fn ($payment) => $payment->paid_at->format('Y-m'))
            ->map(fn ($group) => (int) $group->sum('amount_cents'))
            ->sortKeys();

        return response()->json([
            'data' => [
                'retreat_id' => $retreat->id,
                'active_enrollments_count' => $enrollments->count(),
                'total_contracted_cents' => $totalContracted,
                'total_received_cents' => $totalReceived,
                'balance_cents' => max(0, $totalContracted - $totalReceived),
                'settled_enrollments_count' => $settledCount,
                'enrollments_with_balance_count' => $enrollments->count() - $settledCount,
                'total_overdue_cents' => (int) $overdueCents,
                'receipts_by_month' => $receiptsByMonth,
                'last_payments' => PaymentResource::collection($lastPayments),
            ],
        ]);
    }
}
