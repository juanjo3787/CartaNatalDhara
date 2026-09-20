<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            // door=null identifies fixed/shared text usable across all doors (gray text in the dossier).
            $table->enum('door', ['sol', 'luna', 'ascendente', 'descendente'])->nullable()->after('name');
            $table->enum('block', [
                'shared_intro', 'shared_states', 'shared_conclusions',
                'function', 'sign', 'house', 'ruler', 'integration', 'harmony', 'deficit', 'excess', 'closing',
            ])->after('door');
        });

        Schema::table('interpretations', function (Blueprint $table) {
            // door=null for shared/fixed blocks (intro, states, conclusions) not tied to a single door.
            $table->enum('door', ['sol', 'luna', 'ascendente', 'descendente'])->nullable()->after('phase');
            $table->enum('block', [
                'shared_intro', 'shared_states', 'shared_conclusions',
                'function', 'sign', 'house', 'ruler', 'integration', 'harmony', 'deficit', 'excess', 'closing',
            ])->after('door');
            // Planet keys explained as rulers in this block, so later doors can skip re-explaining them.
            $table->json('rulers_used')->nullable()->after('ai_assisted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interpretations', function (Blueprint $table) {
            $table->dropColumn(['door', 'block', 'rulers_used']);
        });

        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn(['door', 'block']);
        });
    }
};
