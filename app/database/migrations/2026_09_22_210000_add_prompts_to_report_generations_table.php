<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_generations', function (Blueprint $table): void {
            $table->longText('system_prompt')->nullable()->after('cost_currency');
            $table->longText('user_prompt')->nullable()->after('system_prompt');
        });
    }

    public function down(): void
    {
        Schema::table('report_generations', function (Blueprint $table): void {
            $table->dropColumn(['system_prompt', 'user_prompt']);
        });
    }
};