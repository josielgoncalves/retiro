<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retreats', function (Blueprint $table) {
            $table->string('cover_image_path')->nullable();
            $table->string('pix_key_type', 20)->nullable();
            $table->string('pix_key')->nullable();
            $table->string('pix_beneficiary_name')->nullable();
            $table->string('receipt_whatsapp', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('retreats', function (Blueprint $table) {
            $table->dropColumn([
                'cover_image_path',
                'pix_key_type',
                'pix_key',
                'pix_beneficiary_name',
                'receipt_whatsapp',
            ]);
        });
    }
};
