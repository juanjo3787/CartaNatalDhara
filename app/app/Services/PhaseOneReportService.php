<?php

namespace App\Services;

use App\Models\Chart;
use App\Models\ChartTemplate;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PhaseOneReportService
{
    public function __construct(private readonly InteriorMapMaster $master) {}

    public function build(Chart $chart, bool $storedOnly = false): array
    {
        $chart->loadMissing('person', 'birthData.place');
        $facts = $this->master->facts($chart);
        $rows = $chart->interpretations()->where('phase', 'fase-1')
            ->whereHas('template', fn ($query) => $query->where('version', InteriorMapMaster::VERSION))
            ->orderBy('id')->get()->keyBy(fn ($row) => ($row->door ?? 'shared').'.'.$row->block);
        $labels = [
            'sol' => ['Tu centro — Sol', '¿Qué quiero expresar y elegir?'],
            'luna' => ['Tu refugio emocional — Luna', '¿Qué siento y qué necesito?'],
            'ascendente' => ['Tu manera de entrar en la vida — Ascendente', '¿Cómo comienzo?'],
            'descendente' => ['Tu espejo — Descendente', '¿Cómo me encuentro con otra persona?'],
        ];
        $doors = [];
        $index = [];
        $sections = [];
        $missing = [];
        foreach ($labels as $key => [$title, $question]) {
            $blocks = [];
            foreach (InteriorMapMaster::TITLES as $block => $heading) {
                $stored = $rows->get($key.'.'.$block);
                if ($stored && trim(strip_tags($stored->content)) !== '') {
                    $blocks[$block] = [$stored->content];
                } else {
                    $missing[] = $key.'.'.$block;
                }
            }
            $number = (string) (count($doors) + 1);
            $sections[$key] = ['enabled' => true, 'number' => $number, 'title' => $title];
            $index[] = ['key' => $key, 'number' => $number, 'title' => $title, 'summary' => $question];
            $doors[] = [
                'key' => $key, 'title' => $title, 'question' => $question,
                'subtitle' => $facts[$key]['sign'].' · Casa '.$facts[$key]['house'],
                'position' => $facts[$key]['sign'].' · Casa '.$facts[$key]['house'],
                'blocks' => $blocks,
            ];
        }
        $integration = [];
        foreach (InteriorMapMaster::INTEGRATION_TITLES as $key => $title) {
            $stored = $rows->get('shared.'.$key);
            if ($stored && trim(strip_tags($stored->content)) !== '') {
                $integration[$key] = [$stored->content];
            } else {
                $missing[] = 'shared.'.$key;
            }
        }
        if ($storedOnly && $missing !== []) {
            throw new RuntimeException('Faltan secciones del nuevo Informe I: '.implode(', ', $missing));
        }
        $name = $chart->person->full_name ?: $chart->person->alias;
        $intro = $this->introduction();
        $storedIntro = $rows->get('shared.shared_intro');
        if ($storedIntro) {
            $intro = [$storedIntro->content];
        }
        $sections['integration'] = ['enabled' => true, 'number' => '5', 'title' => 'La integración de tus cuatro puertas'];
        $index[] = ['key' => 'integration', 'number' => '5', 'title' => $sections['integration']['title'], 'summary' => 'Reunir lo que quieres, sientes, comienzas y compartes'];

        return [
            'report_schema_version' => InteriorMapMaster::VERSION,
            'prompt_version' => 'interior-map-1',
            'name' => $name, 'shared' => ['intro' => $intro],
            'doors' => $doors, 'sections' => $sections, 'index' => $index,
            'integration' => $integration, 'complete' => $missing === [], 'facts' => $facts,
            'technical' => [
                'birth_date' => $chart->birthData->local_date->format('d/m/Y'),
                'birth_time' => $chart->birthData->local_time,
                'place' => $chart->birthData->place->city.', '.$chart->birthData->place->country,
                'zodiac' => 'tropical', 'houses' => 'Placidus',
            ],
        ];
    }

    public function introduction(): array
    {
        return [
            'Este mapa propone una lectura simbólica para explorar tu experiencia. Sus descripciones son posibilidades que puedes reconocer, matizar o descartar, no una prueba científica de personalidad ni una predicción.',
            'El signo describe una forma de expresar una función; la casa señala el ámbito de la vida donde puede manifestarse. El opuesto complementario aporta recursos para ampliar tu respuesta, sin sustituir tu signo ni suponer que tengas planetas en él.',
            'Recorreremos identidad y voluntad con el Sol, necesidades emocionales con la Luna, comienzo con el Ascendente y encuentro con el Descendente. Al final reuniremos sus preguntas en una práctica de integración.',
            'Armonía significa disponibilidad, flexibilidad y medida. Por defecto cuesta acceder a una cualidad; por exceso esa cualidad domina y pierde flexibilidad. No son juicios ni etiquetas: puedes reconocer respuestas distintas según la situación.',
        ];
    }

    public function editableContent(Chart $chart): array
    {
        $report = $this->build($chart, storedOnly: true);
        $shared = ['intro' => implode("\n", $report['shared']['intro'])];
        foreach ($report['integration'] as $key => $paragraphs) {
            $shared[$key] = implode("\n", $paragraphs);
        }
        $doors = [];
        foreach ($report['doors'] as $door) {
            foreach ($door['blocks'] as $key => $paragraphs) {
                $doors[$door['key']][$key] = implode("\n", $paragraphs);
            }
        }

        return compact('shared', 'doors');
    }

    public function persistGeneratedContent(Chart $chart, array $report): int
    {
        $this->build($chart, storedOnly: true);

        return 0;
    }

    public function storeChapter(Chart $chart, ?string $door, array $content, bool $assisted = true): void
    {
        DB::transaction(function () use ($chart, $door, $content, $assisted): void {
            foreach ($content as $block => $paragraphs) {
                $template = ChartTemplate::firstOrCreate(
                    ['name' => 'interior_map_'.($door ?? 'shared').'_'.$block, 'version' => InteriorMapMaster::VERSION],
                    ['door' => $door, 'block' => $block, 'content_type' => 'interior_map', 'status' => 'published', 'content' => 'Modelo genérico del Informe I — Mapa interior.'],
                );
                $chart->interpretations()->updateOrCreate(
                    ['phase' => 'fase-1', 'door' => $door, 'block' => $block],
                    ['template_id' => $template->id, 'content' => implode('', array_map(static fn (string $text): string => '<p>'.e($text).'</p>', $paragraphs)), 'ai_assisted' => $assisted, 'rulers_used' => []],
                );
            }
            $chart->update(['phase_one_pdf' => null, 'phase_one_pdf_generated_at' => null]);
        });
    }
}
