<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couples', function (Blueprint $table) {
            $table->id();
            $table->string('partner_one_name');
            $table->string('partner_two_name');
            $table->string('phone');
            $table->string('secondary_phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('partner_one_name');
            $table->index('partner_two_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couples');
    }
};
