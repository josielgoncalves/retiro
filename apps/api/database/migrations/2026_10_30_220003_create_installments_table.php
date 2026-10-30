<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->unsignedInteger('installment_number');
            $table->bigInteger('amount_cents');
            $table->date('due_date');
            $table->timestamps();

            $table->unique(['enrollment_id', 'installment_number']);
            $table->index('due_date');
        });

        DB::statement('ALTER TABLE installments ADD CONSTRAINT installments_amount_positive_check CHECK (amount_cents > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
