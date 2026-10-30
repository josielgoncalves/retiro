<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->restrictOnDelete();
            $table->bigInteger('amount_cents');
            $table->timestamp('paid_at');
            $table->string('payment_method', 20);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->index('enrollment_id');
            $table->index('paid_at');
            $table->index('reversed_at');
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (payment_method IN ('pix', 'cash', 'transfer', 'other'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive_check CHECK (amount_cents > 0)');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_reversal_consistency_check CHECK ((reversed_at IS NULL) = (reversed_by IS NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
