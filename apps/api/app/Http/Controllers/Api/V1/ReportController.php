<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Retreat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function financial(Request $request): JsonResponse
    {
        $retreat = $this->resolveRetreat($request);

        $enrollments = $this->enrollmentsFor($retreat)->load('installments');

        $overdueInstallments = $enrollments
            ->filter(fn (Enrollment $e) => $e->isFixed())
            ->flatMap(function (Enrollment $e) {
                return $e->installments
                    ->filter(fn (Installment $i) => $i->status() === Installment::STATUS_OVERDUE)
                    ->map(fn (Installment $i) => [
                        'enrollment_id' => $e->id,
                        'couple_name' => $e->couple->partner_one_name.' & '.$e->couple->partner_two_name,
                        'installment_number' => $i->installment_number,
                        'due_date' => $i->due_date->toDateString(),
                        'balance_cents' => $i->balanceCents(),
                    ]);
            })
            ->values();

        return response()->json([
            'data' => [
                'enrollments' => $enrollments->map(fn (Enrollment $e) => $this->enrollmentRow($e))->values(),
                'overdue_installments' => $overdueInstallments,
            ],
        ]);
    }

    public function financialExport(Request $request)
    {
        $retreat = $this->resolveRetreat($request);

        $enrollments = $this->enrollmentsFor($retreat);

        $filename = 'relatorio-financeiro-'.$retreat->id.'-'.now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return Response::stream(function () use ($enrollments) {
            // UTF-8 BOM so Excel on Windows (pt-BR locale) detects the
            // encoding correctly instead of mangling accented characters.
            echo "\xEF\xBB\xBF";

            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Casal', 'Modalidade', 'Valor contratado (R$)', 'Total recebido (R$)', 'Saldo (R$)', 'Situação',
            ], ';');

            foreach ($enrollments as $enrollment) {
                $row = $this->enrollmentRow($enrollment);

                fputcsv($handle, [
                    $this->sanitizeCsvField($row['couple_name']),
                    $row['payment_mode'] === Enrollment::MODE_FIXED ? 'Fixa' : 'Flexível',
                    number_format($row['total_amount_cents'] / 100, 2, ',', '.'),
                    number_format($row['total_paid_cents'] / 100, 2, ',', '.'),
                    number_format($row['balance_cents'] / 100, 2, ',', '.'),
                    $row['balance_cents'] === 0 ? 'Quitado' : 'Com saldo',
                ], ';');
            }

            fclose($handle);
        }, 200, $headers);
    }

    protected function enrollmentRow(Enrollment $enrollment): array
    {
        return [
            'enrollment_id' => $enrollment->id,
            'couple_name' => $enrollment->couple->partner_one_name.' & '.$enrollment->couple->partner_two_name,
            'payment_mode' => $enrollment->payment_mode,
            'total_amount_cents' => $enrollment->total_amount_cents,
            'total_paid_cents' => $enrollment->totalPaidCents(),
            'balance_cents' => $enrollment->balanceCents(),
            'status' => $enrollment->status,
        ];
    }

    protected function enrollmentsFor(Retreat $retreat)
    {
        return Enrollment::where('retreat_id', $retreat->id)
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->with(['couple', 'payments' => fn ($q) => $q->whereNull('reversed_at')])
            ->get();
    }

    protected function resolveRetreat(Request $request): Retreat
    {
        $retreatId = $request->integer('retreat_id');

        if (! $retreatId) {
            throw ValidationException::withMessages([
                'retreat_id' => ['Selecione um retiro para gerar o relatório.'],
            ]);
        }

        $retreat = Retreat::findOrFail($retreatId);

        $this->authorize('view', $retreat);

        return $retreat;
    }

    /**
     * Neutralize formula-injection payloads (=, +, -, @, tab, CR) that
     * spreadsheet apps would otherwise execute as a formula when the CSV
     * is opened.
     */
    protected function sanitizeCsvField(string $value): string
    {
        if (preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
