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
            $sections[$door] = ['enabled' => true, 'title' => $title];
            $index[] = ['key' => $door, 'title' => $title];
            $doors[] = [
                'key' => $door, 'title' => $title,
                'subtitle' => $facts[$door]['sign'],
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
        $sections['opposites'] = ['enabled' => true, 'title' => 'Los opuestos complementarios'];
        $index[] = ['key' => 'opposites', 'title' => 'Los opuestos complementarios'];

        return [
            'report_schema_version' => InteriorMapMaster::VERSION, 'prompt_version' => 'informe-i-states-keywords-1',
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
        $html = $this->renderChapter($content);
        if ($editedHtml !== null) {
            $html = $this->sanitizeChapterHtml($editedHtml);
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
                ['phase' => 'fase-1', 'door' => $door, 'block' => 'concepts', 'template_id' => $template->id],
                ['template_id' => $template->id, 'content' => $html, 'ai_assisted' => $assisted, 'rulers_used' => []],
            );
            $chart->update(['phase_one_pdf' => null, 'phase_one_pdf_generated_at' => null]);
        });
    }

    public function sanitizeChapterHtml(string $html): string
    {
        return $this->normalizeEditorSpaces(preg_replace('/<(p|strong|em|u|br|h3|h4|ul|li)\b[^>]*>/i', '<$1>', strip_tags($html, '<p><strong><em><u><br><h3><h4><ul><li>')));
    }

    private function normalizeEditorSpaces(string $html): string
    {
        // Quill's semantic export encodes ordinary spaces as non-breaking spaces.
        return str_replace(["\u{00A0}", '&nbsp;', '&#160;', '&#xA0;', '&#xa0;'], ' ', $html);
    }

    public function parseChapter(string $html): array
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$this->normalizeEditorSpaces($html).'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $body = $document->getElementsByTagName('body')->item(0);
        $concepts = [];
        $conceptIndex = -1;
        $characteristicIndex = -1;
        $states = [];
        $inStates = false;
        $stateKey = null;
        $awaitingKeywords = false;
        foreach ($body->childNodes as $node) {
            if (trim($node->textContent) === '') {
                continue;
            }
            if (! $node instanceof DOMElement) {
                throw new RuntimeException('Conserva títulos de concepto (H3), características (H4) y párrafos debajo de cada característica.');
            }
            $text = trim($node->textContent);
            if ($awaitingKeywords) {
                if ($node->tagName !== 'ul') {
                    throw new RuntimeException('Al rótulo Palabras clave debe seguir una lista de viñetas.');
                }
                $keywords = [];
                foreach ($node->childNodes as $item) {
                    if (trim($item->textContent) === '') {
                        continue;
                    }
                    if (! $item instanceof DOMElement || $item->tagName !== 'li' || $item->getElementsByTagName('ul')->length || $item->getElementsByTagName('ol')->length) {
                        throw new RuntimeException('Las palabras clave requieren viñetas simples sin sublistas.');
                    }
                    $keywords[] = trim($item->textContent);
                }
                if ($inStates && $stateKey !== null) {
                    $states[$stateKey]['keywords'] = $keywords;
                } elseif (! $inStates && $conceptIndex >= 0) {
                    $concepts[$conceptIndex]['keywords'] = $keywords;
                } else {
                    throw new RuntimeException('La lista de palabras clave debe pertenecer a un apartado o estado.');
                }
                $awaitingKeywords = false;

                continue;
            }
            if ($node->tagName === 'h3' && $text === 'Estados de expresión' && ! $inStates) {
                $inStates = true;
            } elseif ($node->tagName === 'h3' && ! $inStates) {
                $concepts[] = ['title' => $text, 'characteristics' => [], 'keywords' => []];
                $conceptIndex++;
                $characteristicIndex = -1;
            } elseif ($node->tagName === 'h4' && $inStates) {
                $key = array_search($text, InteriorMapMaster::STATES, true);
                if ($key === false || isset($states[$key]) || count($states) !== array_search($key, array_keys(InteriorMapMaster::STATES), true)) {
                    throw new RuntimeException('Conserva los tres estados y su orden: armónico, exceso y defecto.');
                }
                $stateKey = $key;
                $states[$key] = ['paragraphs' => [], 'keywords' => []];
            } elseif ($node->tagName === 'h4' && $conceptIndex >= 0 && $concepts[$conceptIndex]['keywords'] === []) {
                $concepts[$conceptIndex]['characteristics'][] = ['title' => $text, 'paragraphs' => []];
                $characteristicIndex++;
            } elseif ($node->tagName === 'p' && $text === 'Palabras clave' && ($conceptIndex >= 0 || $stateKey !== null)) {
                if (($inStates && ($stateKey === null || $states[$stateKey]['keywords'] !== [])) || (! $inStates && $concepts[$conceptIndex]['keywords'] !== [])) {
                    throw new RuntimeException('Cada apartado o estado lleva una única lista de palabras clave.');
                }
                $awaitingKeywords = true;
            } elseif ($node->tagName === 'p' && $inStates && $stateKey !== null && $states[$stateKey]['keywords'] === []) {
                $states[$stateKey]['paragraphs'][] = $text;
            } elseif ($node->tagName === 'p' && ! $inStates && $characteristicIndex >= 0 && $concepts[$conceptIndex]['keywords'] === []) {
                $concepts[$conceptIndex]['characteristics'][$characteristicIndex]['paragraphs'][] = $text;
            } else {
                throw new RuntimeException('Cada concepto requiere características (H4) y párrafos explicativos; no se admiten introducciones ni listas sin desarrollo.');
            }
        }
        if ($awaitingKeywords) {
            throw new RuntimeException('Falta la lista de palabras clave.');
        }
        $content = ['concepts' => $concepts, 'states' => $states];
        $this->master->validate($content);

        return $content;
    }

    public function renderChapter(array $content): string
    {
        $html = '';
        foreach ($content['concepts'] as $concept) {
            $html .= '<h3>'.e($concept['title']).'</h3>';
            foreach ($concept['characteristics'] as $characteristic) {
                $html .= '<h4>'.e($characteristic['title']).'</h4>';
                foreach ($characteristic['paragraphs'] as $paragraph) {
                    $html .= '<p>'.e($paragraph).'</p>';
                }
            }
            $html .= $this->renderKeywords($concept['keywords']);
        }
        $html .= '<h3>Estados de expresión</h3>';
        foreach (InteriorMapMaster::STATES as $key => $label) {
            $html .= '<h4>'.e($label).'</h4>';
            foreach ($content['states'][$key]['paragraphs'] as $paragraph) {
                $html .= '<p>'.e($paragraph).'</p>';
            }
            $html .= $this->renderKeywords($content['states'][$key]['keywords']);
        }

        return $html;
    }

    private function renderKeywords(array $keywords): string
    {
        return '<p><strong>Palabras clave</strong></p><ul>'.implode('', array_map(static fn (string $word): string => '<li>'.e($word).'</li>', $keywords)).'</ul>';
    }
}
