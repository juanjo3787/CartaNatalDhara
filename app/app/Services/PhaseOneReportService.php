<?php

namespace App\Services;

use App\Models\Chart;
use App\Models\ChartTemplate;
use DOMDocument;
use DOMElement;
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
        $titles = ['sol' => 'El Sol', 'luna' => 'La Luna', 'ascendente' => 'El Ascendente', 'descendente' => 'El Descendente'];
        $numbers = ['I', 'II', 'III', 'IV'];
        $doors = [];
        $sections = [];
        $index = [];
        $missing = [];
        foreach ($titles as $door => $title) {
            $stored = $rows->get($door.'.concepts');
            $blocks = [];
            if ($stored && trim(strip_tags($stored->content)) !== '') {
                $blocks['concepts'] = [$stored->content];
            } else {
                $missing[] = $door.'.concepts';
            }
            $number = $numbers[count($doors)];
            $sections[$door] = ['enabled' => true, 'number' => $number, 'title' => $title];
            $index[] = ['key' => $door, 'number' => $number, 'title' => $title];
            $doors[] = [
                'key' => $door, 'title' => $title,
                'subtitle' => $facts[$door]['position'].' · '.(in_array($door, ['ascendente', 'descendente'], true) ? 'Inicio de la casa ' : 'Casa ').$facts[$door]['house'],
                'blocks' => $blocks,
            ];
        }
        $stored = $rows->get('shared.concepts');
        $opposites = [];
        if ($stored && trim(strip_tags($stored->content)) !== '') {
            $opposites['concepts'] = [$stored->content];
        } else {
            $missing[] = 'shared.concepts';
        }
        if ($storedOnly && $missing !== []) {
            throw new RuntimeException('Faltan secciones del nuevo Informe I: '.implode(', ', $missing));
        }
        $sections['opposites'] = ['enabled' => true, 'number' => 'V', 'title' => 'Los opuestos complementarios'];
        $index[] = ['key' => 'opposites', 'number' => 'V', 'title' => 'Los opuestos complementarios'];

        return [
            'report_schema_version' => InteriorMapMaster::VERSION, 'prompt_version' => 'natal-universal-2',
            'name' => $chart->person->full_name ?: $chart->person->alias,
            'doors' => $doors, 'sections' => $sections, 'index' => $index,
            'opposites' => $opposites, 'complete' => $missing === [], 'facts' => $facts,
            'technical' => [
                'birth_date' => $chart->birthData->local_date->format('d/m/Y'),
                'birth_time' => $chart->birthData->local_time,
                'place' => $chart->birthData->place->city.', '.$chart->birthData->place->country,
                'zodiac' => 'tropical', 'houses' => 'Placidus',
            ],
        ];
    }

    public function editableContent(Chart $chart): array
    {
        $report = $this->build($chart, storedOnly: true);
        $shared = ['concepts' => implode("\n", $report['opposites']['concepts'])];
        $doors = [];
        foreach ($report['doors'] as $door) {
            $doors[$door['key']] = ['concepts' => implode("\n", $door['blocks']['concepts'])];
        }

        return compact('shared', 'doors');
    }

    public function persistGeneratedContent(Chart $chart, array $report): int
    {
        $this->build($chart, storedOnly: true);

        return 0;
    }

    public function storeChapter(Chart $chart, ?string $door, array $content, bool $assisted = true, ?string $editedHtml = null): void
    {
        $this->master->validate($content);
        $this->master->validateFacts($content, $this->master->facts($chart), $door);
        $html = $this->renderConcepts($content['concepts']);
        if ($editedHtml !== null) {
            $html = preg_replace('/<(p|strong|em|u|br|h3|h4)\b[^>]*>/i', '<$1>', strip_tags($editedHtml, '<p><strong><em><u><br><h3><h4>'));
            if ($this->parseChapter($html) !== $content) {
                throw new RuntimeException('El formato editado no conserva los conceptos y características validados.');
            }
        }
        DB::transaction(function () use ($chart, $door, $html, $assisted): void {
            $template = ChartTemplate::firstOrCreate(
                ['name' => 'natal_universal_'.($door ?? 'opposites').'_concepts', 'version' => InteriorMapMaster::VERSION],
                ['door' => $door, 'block' => 'concepts', 'content_type' => 'interior_map', 'status' => 'published', 'content' => 'Informe I — Manual Maestro Universal.'],
            );
            $chart->interpretations()->updateOrCreate(
                ['phase' => 'fase-1', 'door' => $door, 'block' => 'concepts'],
                ['template_id' => $template->id, 'content' => $html, 'ai_assisted' => $assisted, 'rulers_used' => []],
            );
            $chart->update(['phase_one_pdf' => null, 'phase_one_pdf_generated_at' => null]);
        });
    }

    public function parseChapter(string $html): array
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $body = $document->getElementsByTagName('body')->item(0);
        $concepts = [];
        $conceptIndex = -1;
        $characteristicIndex = -1;
        foreach ($body->childNodes as $node) {
            if (trim($node->textContent) === '') {
                continue;
            }
            if (! $node instanceof DOMElement) {
                throw new RuntimeException('Conserva títulos de concepto (H3), características (H4) y párrafos debajo de cada característica.');
            }
            $text = trim($node->textContent);
            if ($node->tagName === 'h3') {
                $concepts[] = ['title' => $text, 'characteristics' => []];
                $conceptIndex++;
                $characteristicIndex = -1;
            } elseif ($node->tagName === 'h4' && $conceptIndex >= 0) {
                $concepts[$conceptIndex]['characteristics'][] = ['title' => $text, 'paragraphs' => []];
                $characteristicIndex++;
            } elseif ($node->tagName === 'p' && $characteristicIndex >= 0) {
                $concepts[$conceptIndex]['characteristics'][$characteristicIndex]['paragraphs'][] = $text;
            } else {
                throw new RuntimeException('Cada concepto requiere características (H4) y párrafos explicativos; no se admiten introducciones ni listas sin desarrollo.');
            }
        }
        $content = ['concepts' => $concepts];
        $this->master->validate($content);

        return $content;
    }

    private function renderConcepts(array $concepts): string
    {
        $html = '';
        foreach ($concepts as $concept) {
            $html .= '<h3>'.e($concept['title']).'</h3>';
            foreach ($concept['characteristics'] as $characteristic) {
                $html .= '<h4>'.e($characteristic['title']).'</h4>';
                foreach ($characteristic['paragraphs'] as $paragraph) {
                    $html .= '<p>'.e($paragraph).'</p>';
                }
            }
        }

        return $html;
    }
}
