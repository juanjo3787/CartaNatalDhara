<?php

namespace App\Providers;

use App\Contracts\AstrologyCalculator;
use App\Contracts\AiTextGenerator;
use App\Domain\Astrology\Calculators\SwissEphemerisCalculator;
use App\Services\OpenAiTextGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AstrologyCalculator::class, function () {
            return new SwissEphemerisCalculator(
                binaryPath: config('astrology.swetest_bin'),
                ephemerisPath: config('astrology.ephemeris_path'),
            );
        });

        $this->app->bind(AiTextGenerator::class, function () {
            return match (config('ai.provider')) {
                'openai' => new OpenAiTextGenerator(),
                default => throw new \RuntimeException('Proveedor de IA no soportado: '.config('ai.provider')),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
