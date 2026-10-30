<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retreats', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('location')->nullable();
            $table->boolean('registration_open')->default(true);
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });

        DB::statement("ALTER TABLE retreats ADD CONSTRAINT retreats_status_check CHECK (status IN ('draft', 'active', 'finished', 'archived'))");
        DB::statement('ALTER TABLE retreats ADD CONSTRAINT retreats_dates_check CHECK (end_date >= start_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('retreats');
    }
};
