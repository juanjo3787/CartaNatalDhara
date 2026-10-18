<?php

namespace Tests\Feature;

use App\Contracts\AstrologyCalculator;
use App\Domain\Astrology\BirthData;
use App\Domain\Astrology\ChartSnapshot;
use App\Models\Chart;
use App\Models\Person;
use App\Models\Place;
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

    public function test_deleting_the_last_chart_releases_its_person_name_for_reuse(): void
    {
        $this->mock(AstrologyCalculator::class)
            ->shouldReceive('calculate')->twice()
            ->andReturn(new ChartSnapshot('2026-10-13', [], ['houses' => 'placidus']));
        $registration = $this->validRegistration(['alias' => 'Reutilizable', 'full_name' => 'Persona Reutilizable']);

        $this->post('/charts', $registration)->assertSessionHasNoErrors();
        $chart = Chart::query()->firstOrFail();

        $this->delete(route('charts.destroy', $chart))->assertRedirect(route('charts.index'));
        $this->assertDatabaseMissing('people', ['alias' => 'Reutilizable']);

        $this->post('/charts', $registration)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('people', ['alias' => 'Reutilizable', 'full_name' => 'Persona Reutilizable']);
        $this->assertDatabaseCount('charts', 1);
    }

    public function test_updating_natal_inputs_recalculates_snapshot_and_marks_existing_report_stale(): void
    {
        $initialSnapshot = new ChartSnapshot(
            '2026-10-13',
            ['sun' => ['longitude' => 10]],
            ['zodiac' => 'tropical', 'houses' => 'placidus'],
        );
        $updatedSnapshot = new ChartSnapshot(
            '2026-10-18',
            ['sun' => ['longitude' => 20]],
            ['zodiac' => 'tropical', 'houses' => 'koch'],
        );
        $calculator = $this->mock(AstrologyCalculator::class);
        $calculator->shouldReceive('calculate')->twice()->andReturn($initialSnapshot, $updatedSnapshot);
        $registration = $this->validRegistration();

        $this->post('/charts', $registration)->assertSessionHasNoErrors();
        $chart = Chart::query()->firstOrFail();
        $chart->update(['phase_one_pdf' => 'pdfs/old.pdf']);

        $response = $this->put(route('charts.registration.update', $chart), $this->validRegistration([
            'local_date' => '16/06/1990',
            'local_time' => '15:30',
            'latitude' => '41.000000',
            'houses' => 'koch',
        ]));

        $response->assertRedirect(route('charts.show', $chart))
            ->assertSessionHas('success', 'Carta natal recalculada. Los cambios no actualizan el informe automáticamente; vuelve a regenerarlo.');
        $chart->refresh();
        $this->assertSame($updatedSnapshot->positions, $chart->snapshot);
        $this->assertSame('koch', $chart->configuration['houses']);
        $this->assertTrue($chart->report_is_stale);
        $this->assertNull($chart->phase_one_pdf);
        $this->assertSame('1990-06-16', $chart->birthData->fresh()->local_date->format('Y-m-d'));
        $this->assertSame('15:30', substr((string) $chart->birthData->fresh()->local_time, 0, 5));
    }

    public function test_unsupported_house_system_is_rejected(): void
    {
        $response = $this->post('/charts', $this->validRegistration(['houses' => 'alcabitius']));

        $response->assertSessionHasErrors('houses');
    }

    public function test_coordinates_are_rounded_before_storage_and_calculation(): void
    {
        $this->mock(AstrologyCalculator::class)
            ->shouldReceive('calculate')->once()
            ->withArgs(fn (BirthData $birth): bool => $birth->latitude === 40.416776 && $birth->longitude === -3.703791)
            ->andReturn(new ChartSnapshot('2026-10-13', [], ['houses' => 'placidus']));

        $this->post('/charts', $this->validRegistration([
            'latitude' => '40.4167759',
            'longitude' => '-3.7037908',
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $place = Place::firstOrFail();
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
