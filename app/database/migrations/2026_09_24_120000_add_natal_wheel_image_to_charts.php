<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charts', function (Blueprint $table): void {
            $table->longText('natal_wheel_image')->nullable()->after('phase_one_pdf_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('charts', function (Blueprint $table): void {
            $table->dropColumn('natal_wheel_image');
        });
    }
};
