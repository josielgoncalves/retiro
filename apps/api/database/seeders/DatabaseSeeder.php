<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Couple;
use App\Models\Enrollment;
use App\Models\Retreat;
use App\Services\FinancialService;
use Illuminate\Database\Seeder;

/**
 * Development/demo data only — fictitious names, no real personal data.
 * The admin user is created separately via `php artisan app:create-admin`
 * (or DEMO_ADMIN_* env vars), never with a hardcoded credential here.
 */
class DatabaseSeeder extends Seeder
{
    public function run(FinancialService $financialService): void
    {
        $retreat = Retreat::create([
            'name' => 'Retiro de Casais — Outono 2026',
            'description' => 'Um fim de semana dedicado a fortalecer o vínculo do casal.',
            'start_date' => now()->addMonths(2)->startOfMonth()->addDays(9),
            'end_date' => now()->addMonths(2)->startOfMonth()->addDays(11),
            'location' => 'Casa de Retiros Vale da Paz',
            'registration_open' => true,
            'status' => Retreat::STATUS_ACTIVE,
        ]);

        $couples = collect([
            ['partner_one_name' => 'João Pereira', 'partner_two_name' => 'Maria Pereira', 'phone' => '+55 11 91111-0001', 'email' => 'joao.maria@example.com'],
            ['partner_one_name' => 'Pedro Alves', 'partner_two_name' => 'Ana Alves', 'phone' => '+55 11 91111-0002', 'email' => 'pedro.ana@example.com'],
            ['partner_one_name' => 'Lucas Souza', 'partner_two_name' => 'Camila Souza', 'phone' => '+55 11 91111-0003', 'email' => 'lucas.camila@example.com'],
            ['partner_one_name' => 'Rafael Costa', 'partner_two_name' => 'Fernanda Costa', 'phone' => '+55 11 91111-0004', 'email' => 'rafael.fernanda@example.com'],
            ['partner_one_name' => 'Bruno Lima', 'partner_two_name' => 'Juliana Lima', 'phone' => '+55 11 91111-0005', 'email' => 'bruno.juliana@example.com'],
        ])->map(fn (array $data) => Couple::create($data));

        // 3 fixed-mode enrollments, with varied payment progress.
        $fixedConfigs = [
            ['total' => 120000, 'installments' => 3, 'paid_installments' => 3], // fully settled
            ['total' => 90000, 'installments' => 3, 'paid_installments' => 1],  // partially paid
            ['total' => 150000, 'installments' => 5, 'paid_installments' => 0], // untouched, includes an overdue one
        ];

        foreach ($fixedConfigs as $index => $config) {
            $couple = $couples[$index];

            // The third config (no payments yet) starts its installments in
            // the past on purpose, so the demo data includes an overdue one.
            $anchor = $config['paid_installments'] === 0 ? now()->subMonths(2) : now()->subMonths(1);

            $dueDates = collect(range(1, $config['installments']))->map(
                fn ($n) => $anchor->copy()->addMonths($n)->startOfMonth()->addDays(4)
            )->all();

            $enrollment = $financialService->createEnrollment($retreat, $couple, [
                'payment_mode' => Enrollment::MODE_FIXED,
                'total_amount_cents' => $config['total'],
                'installments' => array_map(fn ($date) => ['due_date' => $date], $dueDates),
            ]);

            $installments = $enrollment->installments()->orderBy('installment_number')->take($config['paid_installments'])->get();

            foreach ($installments as $installment) {
                $financialService->registerPayment($enrollment, [
                    'amount_cents' => $installment->amount_cents,
                    'paid_at' => $installment->due_date->copy()->subDays(2),
                    'payment_method' => 'pix',
                    'installment_ids' => [$installment->id],
                ], null);
            }
        }

        // 2 flexible-mode enrollments.
        $flexibleConfigs = [
            ['total' => 80000, 'paid' => 80000],  // fully paid
            ['total' => 60000, 'paid' => 25000],  // partially paid
        ];

        foreach ($flexibleConfigs as $index => $config) {
            $couple = $couples[3 + $index];

            $enrollment = $financialService->createEnrollment($retreat, $couple, [
                'payment_mode' => Enrollment::MODE_FLEXIBLE,
                'total_amount_cents' => $config['total'],
            ]);

            if ($config['paid'] > 0) {
                $financialService->registerPayment($enrollment, [
                    'amount_cents' => $config['paid'],
                    'paid_at' => now()->subWeek(),
                    'payment_method' => 'transfer',
                ], null);
            }
        }

        Announcement::create([
            'retreat_id' => $retreat->id,
            'title' => 'Bem-vindos ao retiro!',
            'content' => "Estamos muito felizes em receber vocês. Tragam roupas confortáveis, um caderno para anotações e um coração aberto.\n\nQualquer dúvida, fale com a organização.",
            'published_at' => now()->subDays(3),
        ]);

        Announcement::create([
            'retreat_id' => $retreat->id,
            'title' => 'Informações sobre hospedagem (rascunho)',
            'content' => 'Detalhes sobre acomodação — ainda em revisão pela organização.',
            'published_at' => null,
        ]);
    }
}
