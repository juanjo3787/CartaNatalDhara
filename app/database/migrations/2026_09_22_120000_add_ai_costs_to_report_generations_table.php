<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_generations', function (Blueprint $table): void {
            $table->string('door')->nullable()->after('report_type');
            $table->boolean('ai_assisted')->default(false)->after('door');
            $table->string('ai_model')->nullable()->after('ai_assisted');
            $table->unsignedInteger('input_tokens')->nullable()->after('ai_model');
            $table->unsignedInteger('output_tokens')->nullable()->after('input_tokens');
            $table->unsignedInteger('total_tokens')->nullable()->after('output_tokens');
            $table->decimal('cost_input', 12, 8)->nullable()->after('total_tokens');
            $table->decimal('cost_output', 12, 8)->nullable()->after('cost_input');
            $table->decimal('cost_subtotal', 12, 8)->nullable()->after('cost_output');
            $table->decimal('tax_rate', 5, 2)->nullable()->after('cost_subtotal');
            $table->decimal('tax_amount', 12, 8)->nullable()->after('tax_rate');
            $table->decimal('cost_total', 12, 8)->nullable()->after('tax_amount');
            $table->string('cost_currency', 3)->nullable()->after('cost_total');
        });
    }

    public function down(): void
    {
        Schema::table('report_generations', function (Blueprint $table): void {
            $table->dropColumn([
                'door', 'ai_assisted', 'ai_model', 'input_tokens', 'output_tokens', 'total_tokens',
                'cost_input', 'cost_output', 'cost_subtotal', 'tax_rate', 'tax_amount', 'cost_total', 'cost_currency',
            ]);
        });
    }
};