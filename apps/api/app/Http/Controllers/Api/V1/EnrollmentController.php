<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Enrollment\StoreEnrollmentRequest;
use App\Http\Requests\Enrollment\UpdateEnrollmentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Models\Couple;
use App\Models\Enrollment;
use App\Models\Retreat;
use App\Services\AuditLogger;
use App\Services\FinancialService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnrollmentController extends Controller
{
    public function __construct(
        protected FinancialService $financialService,
        protected AuditLogger $auditLogger,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Enrollment::class);

        $query = Enrollment::query()->with(['retreat', 'couple']);

        if ($retreatId = $request->integer('retreat_id')) {
            $query->where('retreat_id', $retreatId);
        }

        if ($coupleId = $request->integer('couple_id')) {
            $query->where('couple_id', $coupleId);
        }

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        $enrollments = $query->orderByDesc('registered_at')->paginate($request->integer('per_page', 15));

        return EnrollmentResource::collection($enrollments);
    }

    public function store(StoreEnrollmentRequest $request)
    {
        $data = $request->validated();

        $retreat = Retreat::findOrFail($data['retreat_id']);
        $couple = Couple::findOrFail($data['couple_id']);

        $enrollment = $this->financialService->createEnrollment($retreat, $couple, $data);

        $this->auditLogger->log('enrollment.created', $enrollment, null, $enrollment->toArray());

        return (new EnrollmentResource($enrollment->load(['retreat', 'couple', 'installments'])))
            ->response()->setStatusCode(201);
    }

    public function show(Enrollment $enrollment)
    {
        $this->authorize('view', $enrollment);

        return new EnrollmentResource(
            $enrollment->load(['retreat', 'couple', 'installments.allocations.payment', 'payments.createdBy', 'payments.reversedBy'])
        );
    }

    public function update(UpdateEnrollmentRequest $request, Enrollment $enrollment)
    {
        $data = $request->validated();

        $this->financialService->assertFinancialsAreEditable($enrollment, $data);

        $before = $enrollment->toArray();
        $enrollment->update($data);

        $this->auditLogger->log('enrollment.updated', $enrollment, $before, $enrollment->toArray());

        return new EnrollmentResource($enrollment->load(['retreat', 'couple']));
    }

    public function cancel(Enrollment $enrollment)
    {
        $this->authorize('update', $enrollment);

        if ($enrollment->status === Enrollment::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'status' => ['Esta inscrição já está cancelada.'],
            ]);
        }

        $before = $enrollment->toArray();
        $enrollment->update(['status' => Enrollment::STATUS_CANCELLED]);

        $this->auditLogger->log('enrollment.cancelled', $enrollment, $before, $enrollment->toArray());

        return new EnrollmentResource($enrollment->load(['retreat', 'couple']));
    }
}
