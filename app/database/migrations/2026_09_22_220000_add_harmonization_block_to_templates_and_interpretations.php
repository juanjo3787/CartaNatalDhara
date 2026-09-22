<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $blocks = [
            'shared_intro', 'shared_states', 'shared_conclusions',
            'function', 'sign', 'house', 'ruler', 'integration', 'harmony', 'deficit', 'excess', 'harmonization', 'closing',
        ];

        Schema::table('templates', function (Blueprint $table) use ($blocks): void {
            $table->enum('block', $blocks)->change();
        });

        Schema::table('interpretations', function (Blueprint $table) use ($blocks): void {
            $table->enum('block', $blocks)->change();
        });
    }

    public function down(): void
    {
        $blocks = [
            'shared_intro', 'shared_states', 'shared_conclusions',
            'function', 'sign', 'house', 'ruler', 'integration', 'harmony', 'deficit', 'excess', 'closing',
        ];

        Schema::table('templates', function (Blueprint $table) use ($blocks): void {
            $table->enum('block', $blocks)->change();
        });

        Schema::table('interpretations', function (Blueprint $table) use ($blocks): void {
            $table->enum('block', $blocks)->change();
        });
    }
};
