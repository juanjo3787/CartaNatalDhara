<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('charts', function (Blueprint $table): void {
            $table->longText('phase_one_pdf')->nullable()->after('status');
            $table->timestamp('phase_one_pdf_generated_at')->nullable()->after('phase_one_pdf');
        });
    }

    public function down(): void
    {
        Schema::table('charts', function (Blueprint $table): void {
            $table->dropColumn(['phase_one_pdf', 'phase_one_pdf_generated_at']);
        });
    }
};
