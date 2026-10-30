<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retreat_id')->constrained('retreats')->restrictOnDelete();
            $table->foreignId('couple_id')->constrained('couples')->restrictOnDelete();
            $table->string('payment_mode', 20);
            $table->bigInteger('total_amount_cents');
            $table->string('status', 20)->default('active');
            $table->timestamp('registered_at');
            $table->timestamps();

            $table->unique(['retreat_id', 'couple_id']);
            $table->index('couple_id');
            $table->index('status');
        });

        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_payment_mode_check CHECK (payment_mode IN ('fixed', 'flexible'))");
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_status_check CHECK (status IN ('active', 'cancelled'))");
        DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollments_total_amount_positive_check CHECK (total_amount_cents > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
