<?php

namespace App\Http\Controllers;

use App\Domain\Astrology\BirthDataNormalizer;
use App\Models\BirthData;
use App\Models\Chart;
use App\Models\Person;
use App\Models\PersonChangeLog;
use App\Models\Place;
use App\Models\ReportGeneration;
use App\Models\ReportJob;
use App\Services\ChartService;
use App\Services\PdfPageGeometry;
use App\Services\PhaseOneManualSaveService;
use App\Services\PhaseOneReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChartController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $query = Chart::query()
            ->with('person')
            ->with(['interpretations:id,chart_id,phase'])
            ->latest();

        if ($search !== '') {
            $query->whereHas('person', function ($personQuery) use ($search) {
                $personQuery->where('alias', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%");
            });
        }

        return view('charts.index', [
            'charts' => $query->paginate(20)->appends(['search' => $search]),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('charts.create');
    }

    public function store(Request $request, BirthDataNormalizer $normalizer, ChartService $chartService): RedirectResponse
    {
        $validated = $request->validate($this->registrationRules(), $this->registrationMessages());

        $validated['local_date'] = \DateTime::createFromFormat('d/m/Y', $validated['local_date'])->format('Y-m-d');

        $normalized = $normalizer->normalize($validated);

        $place = Place::create([
            'city' => $validated['city'],
            'country' => $validated['country'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'timezone_identifier' => $validated['timezone_identifier'],
        ]);

        $person = Person::create([
            'alias' => $validated['alias'],
            'full_name' => $validated['full_name'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $birthData = BirthData::create([
            'person_id' => $person->id,
            'place_id' => $place->id,
            'local_date' => $normalized->localDate,
            'local_time' => $normalized->localTime,
            'timezone_identifier' => $normalized->timezoneIdentifier,
            'utc_offset' => $normalized->utcOffset,
            'utc_datetime' => $normalized->utcDatetime,
            'time_source' => $normalized->timeSource,
            'time_precision' => $normalized->timePrecision,
        ]);

        $chart = $chartService->calculateFor($birthData->load('place'));

        return redirect()->route('charts.show', $chart);
    }

    public function show(Chart $chart): View
    {
        $chart->load('person', 'birthData.place');

        return view('charts.show', ['chart' => $chart]);
    }

    public function report(Chart $chart, PhaseOneReportService $reportService): View
    {
        return view('charts.report', [
            'chart' => $chart,
            'report' => $reportService->build($chart),
        ]);
    }

    public function downloadReport(Chart $chart, PhaseOneReportService $reportService)
    {
        if ($chart->phase_one_pdf && str_ends_with($chart->phase_one_pdf, '-v'.PdfPageGeometry::VERSION.'.pdf') && Storage::disk('local')->exists($chart->phase_one_pdf)) {
            return response()->streamDownload(function () use ($chart) {
                echo Storage::disk('local')->get($chart->phase_one_pdf);
            }, 'carta-natal-fase-1-'.$chart->id.'.pdf', [
                'Content-Type' => 'application/pdf',
            ]);
        }

        $response = app(ReportJobController::class)->store(request(), $chart, []);

        return request()->expectsJson() ? $response : redirect()->route('charts.show', $chart)->with('success', 'El PDF se está preparando en segundo plano.');
    }

    public function validateAndStoreReport(Chart $chart): JsonResponse
    {
        return app(ReportJobController::class)->store(request(), $chart, []);
    }

    public function regenerateReport(Chart $chart): JsonResponse
    {
        return app(ReportJobController::class)->store(request(), $chart);
    }

    private function registrationRules(?Person $person = null): array
    {
        $aliasRule = Rule::unique('people', 'alias');
        $fullNameRule = Rule::unique('people', 'full_name')->whereNotNull('full_name');

        if ($person) {
            $aliasRule->ignore($person->id);
            $fullNameRule->ignore($person->id);
        }

        return [
            'alias' => ['required', 'string', 'max:255', $aliasRule],
            'full_name' => ['required', 'string', 'max:255', $fullNameRule],
            'notes' => ['nullable', 'string'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'timezone_identifier' => ['required', 'timezone'],
            'local_date' => ['required', 'date_format:d/m/Y'],
            'local_time' => ['required', 'date_format:H:i'],
            'time_source' => ['required', 'in:document,family,estimated,unknown'],
            'time_precision' => ['required', 'in:exact,approximate,unknown'],
        ];
    }

    private function registrationMessages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute no puede superar los :max caracteres.',
            'numeric' => 'El campo :attribute debe ser numérico.',
            'between' => 'El campo :attribute está fuera del rango permitido.',
            'timezone' => 'La zona horaria indicada no es válida.',
            'date_format' => 'El campo :attribute debe tener el formato :format.',
            'in' => 'El valor seleccionado para :attribute no es válido.',
            'alias.unique' => 'Ya existe una carta natal para esa persona con este alias.',
            'full_name.unique' => 'Ya existe una carta natal para esa persona con este nombre completo.',
            'attributes' => [
                'alias' => 'alias',
                'full_name' => 'nombre completo',
                'city' => 'ciudad',
                'country' => 'país',
                'latitude' => 'latitud',
                'longitude' => 'longitud',
                'timezone_identifier' => 'zona horaria',
                'local_date' => 'fecha de nacimiento',
                'local_time' => 'hora de nacimiento',
                'time_source' => 'fuente de la hora',
                'time_precision' => 'precisión de la hora',
            ],
        ];
    }

    public function editRegistration(Chart $chart): View
    {
        $chart->load('person', 'birthData.place');

        return view('charts.registration-edit', ['chart' => $chart]);
    }

    public function updateRegistration(Request $request, Chart $chart, BirthDataNormalizer $normalizer): RedirectResponse
    {
        abort_if(ReportJob::where('active_chart_id', $chart->id)->exists(), 409, 'Espera a que termine la generación antes de modificar esta carta.');
        $chart->load('person', 'birthData.place');
        $validated = $request->validate(
            $this->registrationRules($chart->person),
            $this->registrationMessages(),
        );

        $validated['local_date'] = \DateTime::createFromFormat('d/m/Y', $validated['local_date'])->format('Y-m-d');
        $normalized = $normalizer->normalize($validated);
        $person = $chart->person;
        $changes = [];
        foreach (['alias', 'full_name', 'notes'] as $field) {
            $old = (string) ($person->{$field} ?? '');
            $new = (string) ($validated[$field] ?? '');
            if ($old !== $new) {
                $changes[] = [$field, $old, $new];
            }
        }
        $person->update(['alias' => $validated['alias'], 'full_name' => $validated['full_name'] ?? null, 'notes' => $validated['notes'] ?? null]);

        $place = $chart->birthData->place;
        foreach (['city', 'country', 'latitude', 'longitude', 'timezone_identifier'] as $field) {
            $old = (string) ($place->{$field} ?? '');
            $new = (string) $validated[$field];
            if ($old !== $new) {
                $changes[] = ['place.'.$field, $old, $new];
            }
        }
        $place->update(array_intersect_key($validated, array_flip(['city', 'country', 'latitude', 'longitude', 'timezone_identifier'])));
        $chart->birthData->update([
            'local_date' => $normalized->localDate, 'local_time' => $normalized->localTime,
            'timezone_identifier' => $normalized->timezoneIdentifier, 'utc_offset' => $normalized->utcOffset,
            'utc_datetime' => $normalized->utcDatetime, 'time_source' => $normalized->timeSource, 'time_precision' => $normalized->timePrecision,
        ]);

        foreach ($changes as [$field, $old, $new]) {
            PersonChangeLog::create(['person_id' => $person->id, 'chart_id' => $chart->id, 'field' => $field, 'old_value' => $old, 'new_value' => $new]);
        }

        return redirect()->route('charts.index')->with('success', 'Datos de registro actualizados.');
    }

    public function reportHistory(Chart $chart): View
    {
        $generations = $chart->reportGenerations()->latest()->get();
        $aiGenerations = $generations->where('ai_assisted', true);

        return view('charts.report-history', [
            'chart' => $chart,
            'generations' => $generations,
            'totals' => [
                'tokens' => (int) $aiGenerations->sum('total_tokens'),
                'subtotal' => (float) $aiGenerations->sum('cost_subtotal'),
                'tax' => (float) $aiGenerations->sum('tax_amount'),
                'total' => (float) $aiGenerations->sum('cost_total'),
                'currency' => $aiGenerations->pluck('cost_currency')->filter()->first() ?? config('ai.currency', 'EUR'),
            ],
        ]);
    }

    public function promptHistory(Chart $chart, ReportGeneration $generation): View
    {
        abort_unless($generation->chart_id === $chart->id && $generation->ai_assisted, 404);

        return view('charts.prompt-history', [
            'chart' => $chart,
            'generation' => $generation,
        ]);
    }

    public function personHistory(Chart $chart): View
    {
        $chart->load('person');

        return view('charts.person-history', ['chart' => $chart, 'changes' => PersonChangeLog::where('person_id', $chart->person_id)->latest()->get()]);
    }

    public function editReport(Chart $chart, PhaseOneReportService $reportService): View
    {
        return view('charts.report-edit', [
            'chart' => $chart,
            'report' => $reportService->build($chart),
            'editable' => $reportService->editableContent($chart),
        ]);
    }

    public function saveReport(Request $request, Chart $chart, PhaseOneManualSaveService $saveService): RedirectResponse
    {
        $validated = $request->validate([
            'shared' => ['required', 'array'],
            'shared.*' => ['nullable', 'string'],
            'doors' => ['required', 'array'],
            'doors.*' => ['required', 'array'],
            'doors.*.*' => ['nullable', 'string'],
        ]);

        $saved = $saveService->save($chart, $validated['shared'], $validated['doors']);

        return redirect()->route('charts.report', $chart)
            ->with('success', "Informe guardado correctamente ({$saved} bloques).");
    }

    public function generateAiReport(Request $request, Chart $chart, string $door): JsonResponse
    {
        return app(ReportJobController::class)->store($request, $chart, [$door]);
    }

    public function generateDoorAiStage(Request $request, Chart $chart, string $door, string $stage): JsonResponse
    {
        return response()->json(['message' => 'Use the asynchronous report generation endpoint.'], 410);
    }

    public function generateAllAiReport(Request $request, Chart $chart): JsonResponse
    {
        return app(ReportJobController::class)->store($request, $chart);
    }

    public function destroy(Chart $chart): RedirectResponse
    {
        abort_if(ReportJob::where('active_chart_id', $chart->id)->exists(), 409, 'Espera a que termine la generación antes de eliminar esta carta.');
        $chart->delete();

        return redirect()->route('charts.index')->with('success', 'La carta natal ha sido eliminada.');
    }
}
