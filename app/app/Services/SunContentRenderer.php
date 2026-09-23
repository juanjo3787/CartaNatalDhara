<?php

namespace App\Services;

final class SunContentRenderer
{
    private const STATE_HEADINGS = [
        'harmony' => ['Pautas y consideraciones para reconocer este equilibrio', 'Ejemplos cotidianos de estas pautas'],
        'deficit' => ['Pautas y consideraciones para empezar a armonizar', 'Ejemplos cotidianos y formas de empezar a armonizar'],
        'excess' => ['Pautas y consideraciones para recuperar una medida adecuada', 'Ejemplos cotidianos y formas de recuperar medida'],
    ];

    public function render(array $content): array
    {
        $blocks = [];
        foreach (['shared_intro', 'function', 'sign', 'house', 'ruler', 'integration'] as $key) {
            $blocks[$key] = $this->paragraphs($content[$key]['paragraphs']);
        }

        foreach (self::STATE_HEADINGS as $key => [$guidelineHeading, $exampleHeading]) {
            $state = $content[$key];
            $blocks[$key] = [
                ...$this->paragraphs($state['development']),
                '<h3>Características que puedes observar</h3>',
                $this->orderedList(array_column($state['characteristics'], 'text')),
                '<h3>'.e($guidelineHeading).'</h3>',
                $this->orderedList(array_column($state['guidelines'], 'text')),
                '<h3>'.e($exampleHeading).'</h3>',
                $this->orderedList(array_column($state['examples'], 'text')),
            ];
        }

        $harmonization = $content['harmonization'];
        $blocks['harmonization'] = [
            '<h3>Desde el defecto</h3>',
            ...$this->paragraphs($harmonization['from_deficit']['paragraphs']),
            '<p>Puntos concretos para comenzar:</p>',
            $this->orderedList($harmonization['from_deficit']['points']),
            '<h3>Desde el exceso</h3>',
            ...$this->paragraphs($harmonization['from_excess']['paragraphs']),
            '<p>Puntos concretos para recuperar medida:</p>',
            $this->orderedList($harmonization['from_excess']['points']),
            '<h3>El punto de equilibrio</h3>',
            ...$this->paragraphs($harmonization['equilibrium']['paragraphs']),
            '<p>Referencias para reconocer ese equilibrio:</p>',
            $this->unorderedList($harmonization['equilibrium']['references']),
        ];

        $closing = $content['closing'];
        $blocks['closing'] = [
            '<h3>Preguntas de autoobservación</h3>',
            ...$this->paragraphs($closing['question_intro']),
            $this->orderedList($closing['questions']),
            '<h3>Frases de integración</h3>',
            '<p>Frase central: «'.e($closing['central_phrase']).'»</p>',
            '<p>Para empezar: «'.e($closing['support_phrases']['to_begin']).'»</p>',
            '<p>Para recuperar medida: «'.e($closing['support_phrases']['to_restore_measure']).'»</p>',
            '<p>Para revisar: «'.e($closing['support_phrases']['to_review']).'»</p>',
            '<p>Para reunir lo aprendido: «'.e($closing['support_phrases']['to_integrate']).'»</p>',
        ];

        return $blocks;
    }

    private function paragraphs(array $items): array
    {
        return array_map(static fn (string $text): string => '<p>'.e($text).'</p>', $items);
    }

    private function orderedList(array $items): string
    {
        return '<ol>'.implode('', array_map(static fn (string $text): string => '<li>'.e($text).'</li>', $items)).'</ol>';
    }

    private function unorderedList(array $items): string
    {
        return '<ul>'.implode('', array_map(static fn (string $text): string => '<li>'.e($text).'</li>', $items)).'</ul>';
    }
}
