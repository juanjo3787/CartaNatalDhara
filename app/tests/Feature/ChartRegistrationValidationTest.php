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
        ], $overrides);
    }
}
