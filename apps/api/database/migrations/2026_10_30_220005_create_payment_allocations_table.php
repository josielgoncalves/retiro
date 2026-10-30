<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('installment_id')->constrained('installments')->restrictOnDelete();
            $table->bigInteger('amount_cents');
            $table->timestamps();

            $table->unique(['payment_id', 'installment_id']);
            $table->index('installment_id');
        });

        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_amount_positive_check CHECK (amount_cents > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
