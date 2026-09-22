<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $pricing = config('ai.pricing', []);
        $exchangeRate = (float) config('ai.usd_to_eur', 0.92);
        $defaultTaxRate = (float) config('ai.tax_rate', 21);

        DB::table('report_generations')
            ->where('ai_assisted', true)
            ->where('total_tokens', '>', 0)
            ->where('cost_total', 0)
            ->orderBy('id')
            ->each(function (object $generation) use ($pricing, $exchangeRate, $defaultTaxRate): void {
                $modelPricing = $pricing[$generation->ai_model] ?? null;

                if (! is_array($modelPricing)) {
                    return;
                }

                $inputCost = ((int) $generation->input_tokens / 1_000_000) * (float) $modelPricing['input'] * $exchangeRate;
                $outputCost = ((int) $generation->output_tokens / 1_000_000) * (float) $modelPricing['output'] * $exchangeRate;
                $subtotal = $inputCost + $outputCost;
                $taxRate = $generation->tax_rate === null ? $defaultTaxRate : (float) $generation->tax_rate;
                $taxAmount = $subtotal * ($taxRate / 100);

                DB::table('report_generations')->where('id', $generation->id)->update([
                    'cost_input' => $inputCost,
                    'cost_output' => $outputCost,
                    'cost_subtotal' => $subtotal,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'cost_total' => $subtotal + $taxAmount,
                    'cost_currency' => $generation->cost_currency ?: config('ai.currency', 'EUR'),
                ]);
            });
    }

    public function down(): void
    {
    }
};