<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartRegistrationValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('El entorno no tiene instalado el driver pdo_sqlite.');
        }

        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_required_registration_fields_are_reported(): void
    {
        $response = $this->post('/charts', []);

        $response->assertSessionHasErrors([
            'alias',
            'full_name',
            'city',
            'country',
            'latitude',
            'longitude',
            'timezone_identifier',
            'local_date',
            'local_time',
            'time_source',
            'time_precision',
            'houses',
        ]);
    }

    public function test_duplicate_alias_is_rejected(): void
    {
        Person::create(['alias' => 'Pastora']);

        $response = $this->post('/charts', $this->validRegistration(['alias' => 'Pastora']));

        $response->assertSessionHasErrors('alias');
        $this->assertStringContainsString(
            'Ya existe una carta natal para esa persona con este alias.',
            session('errors')->get('alias')[0],
        );
    }

    public function test_duplicate_full_name_is_rejected_when_provided(): void
    {
        Person::create(['alias' => 'Pastora', 'full_name' => 'Pastora Garcia']);

        $response = $this->post('/charts', $this->validRegistration([
            'alias' => 'Pastora nueva',
            'full_name' => 'Pastora Garcia',
        ]));

        $response->assertSessionHasErrors('full_name');
        $this->assertStringContainsString(
            'Ya existe una carta natal para esa persona con este nombre completo.',
            session('errors')->get('full_name')[0],
        );
    }

    public function test_unsupported_house_system_is_rejected(): void
    {
        $response = $this->post('/charts', $this->validRegistration(['houses' => 'alcabitius']));

        $response->assertSessionHasErrors('houses');
    }

    public function test_coordinates_are_rounded_before_storage_and_calculation(): void
    {
        $this->mock(\App\Contracts\AstrologyCalculator::class)
            ->shouldReceive('calculate')->once()
            ->withArgs(fn (\App\Domain\Astrology\BirthData $birth): bool => $birth->latitude === 40.416776 && $birth->longitude === -3.703791)
            ->andReturn(new \App\Domain\Astrology\ChartSnapshot('2026-10-13', [], ['houses' => 'placidus']));

        $this->post('/charts', $this->validRegistration([
            'latitude' => '40.4167759',
            'longitude' => '-3.7037908',
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $place = \App\Models\Place::firstOrFail();
        $this->assertSame('40.416776', $place->latitude);
        $this->assertSame('-3.703791', $place->longitude);
    }

    private function validRegistration(array $overrides = []): array
    {
        return array_merge([
            'alias' => 'Persona nueva',
            'full_name' => 'Persona Nueva',
            'city' => 'Madrid',
            'country' => 'Espana',
            'latitude' => '40.4168',
            'longitude' => '-3.7038',
            'timezone_identifier' => 'Europe/Madrid',
            'local_date' => '15/06/1990',
            'local_time' => '14:30',
            'time_source' => 'unknown',
            'time_precision' => 'unknown',
            'houses' => 'placidus',
        ], $overrides);
    }
}
