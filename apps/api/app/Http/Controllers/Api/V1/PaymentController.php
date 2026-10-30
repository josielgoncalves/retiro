<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\ReversePaymentRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Services\AuditLogger;
use App\Services\FinancialService;

class PaymentController extends Controller
{
    public function __construct(
        protected FinancialService $financialService,
        protected AuditLogger $auditLogger,
    ) {}

    public function index(Enrollment $enrollment)
    {
        $this->authorize('managePayments', $enrollment);

        $payments = $enrollment->payments()
            ->with(['createdBy', 'reversedBy', 'allocations.installment'])
            ->orderByDesc('paid_at')
            ->get();

        return PaymentResource::collection($payments);
    }

    public function store(StorePaymentRequest $request, Enrollment $enrollment)
    {
        $payment = $this->financialService->registerPayment($enrollment, $request->validated(), $request->user());

        $this->auditLogger->log('payment.created', $payment, null, $payment->toArray(), $request->user());

        return (new PaymentResource($payment->load(['createdBy', 'allocations.installment'])))
            ->response()->setStatusCode(201);
    }

    public function reverse(ReversePaymentRequest $request, Payment $payment)
    {
        $before = $payment->toArray();

        $reversed = $this->financialService->reversePayment($payment, $request->validated()['reason'], $request->user());

        $this->auditLogger->log('payment.reversed', $reversed, $before, $reversed->toArray(), $request->user());

        return new PaymentResource($reversed->load(['createdBy', 'reversedBy', 'allocations.installment']));
    }
}
