<?php

namespace App\Services\Doors;

final class AscendentePipeline extends AbstractDoorPipeline
{
    public function door(): string
    {
        return 'ascendente';
    }

    public function label(): string
    {
        return 'el Ascendente';
    }

    public function focus(): string
    {
        return 'la manera de entrar en una experiencia nueva, orientarse, dar el primer paso y sostener un ritmo';
    }

    public function rulerParagraphCount(): int
    {
        return 6;
    }

    protected function functionRequirement(): string
    {
        return '3 párrafos de al menos 70 palabras que expliquen inicio, orientación, ritmo, primeras referencias, adaptación y la relación entre preparación y experiencia. Diferencia preparación útil de esperar certeza total, reacción automática de respuesta elegida, y continuidad de inercia. Diferencia el Ascendente de Sol (identidad), Luna (necesidad emocional) y Descendente (vínculo).';
    }

    protected function signRequirement(): string
    {
        return '4 párrafos de al menos 70 palabras sobre cómo este signo ascendente concreto orienta el modo de entrar en una experiencia nueva: qué referencias necesita, qué ritmo puede sostener y qué tensión aparece entre preparación y acción.';
    }

    protected function houseRequirement(): string
    {
        return '6 párrafos de al menos 80 palabras que interpreten la Casa I o el eje I-VII cuando corresponda: qué territorio de inicio, orientación y ritmo se activa. La Casa VII solo puede aparecer como contrapunto breve si los datos lo permiten; no la conviertas en el tema principal.';
    }

    protected function rulerRequirement(): string
    {
        return "{$this->rulerParagraphCount()} párrafos de al menos 80 palabras: explica qué aporta el regente del Ascendente a la comprobación de recursos, el ritmo y la actualización de decisiones; después su signo y su casa; si hay dos regentes, trátalos por separado antes de integrarlos. Usa exclusivamente las posiciones de ASTROLOGICAL_FACTS.";
    }

    protected function integrationRequirement(): string
    {
        return '4 párrafos de al menos 70 palabras que integren Ascendente + signo + casa I o eje + regente + signo del regente + casa del regente, mostrando qué necesita para empezar, cómo comprueba recursos, qué ritmo utiliza, qué mantiene y cuándo necesita actualizarse.';
    }

    protected function stateThemes(string $stage): string
    {
        return match ($stage) {
            'harmony' => 'Explora, adaptado a la posición real, cómo existe tiempo suficiente para orientarse, decisión sin precipitación, continuidad, uso consciente de recursos, disfrute, flexibilidad y capacidad de ajustar una decisión cuando aparecen datos nuevos.',
            'deficit' => 'Explora la falta de espacio para orientarse (por ejemplo: avanzar al ritmo de otros, no comprobar recursos, perder preferencias propias, comprometer la propia base, abandonar demasiado pronto, no dar tiempo suficiente a una experiencia, o dejar el disfrute siempre para después), adaptado siempre a los datos reales.',
            'excess' => 'Explora cuándo el recurso para crear estabilidad ocupa demasiado espacio (por ejemplo: mantener algo solo porque es familiar, esperar seguridad completa, convertir un hábito en regla, preparar indefinidamente, controlar detalles, resistirse a actualizar una decisión, o evitar experiencias por incomodidad inicial). Interprétalo siempre como un recurso útil sin medida, no como un defecto moral.',
            default => '',
        };
    }

    protected function harmonizationRequirement(): string
    {
        return 'Explica específicamente qué significa recuperar espacio y medida PARA ESTE ASCENDENTE, no con una fórmula genérica. Desde el defecto: 2-4 párrafos y 3 puntos concretos sobre cómo devolver espacio a la orientación, los recursos y el ritmo, proponiendo pruebas pequeñas y reales. Desde el exceso: 2-4 párrafos y 3 puntos sobre cómo distinguir estabilidad de rigidez recuperando medida. El punto de equilibrio debe integrar realmente Ascendente + signo + casa o eje + regente, con varios párrafos y 4 referencias observables.';
    }

    protected function closingRequirement(): string
    {
        return 'Un párrafo que explique cómo usar 5 preguntas de autoobservación específicas del Ascendente (por ejemplo sobre qué referencias se necesitan para empezar, qué ritmo se puede sostener, o cuándo una preparación deja de ser útil y se vuelve espera de certeza total). Una frase central y cuatro frases de apoyo: empezar, recuperar medida, revisar y reunir lo aprendido.';
    }

    protected function additionalSystemRules(): array
    {
        return [
            'El Ascendente no habla principalmente de identidad psicológica: habla de cómo se comienza, se entra en una experiencia nueva y se sostiene un ritmo. No lo conviertas en una descripción de personalidad ni en una máscara social.',
            'Distingue siempre preparación útil de esperar certeza total, reacción automática de respuesta elegida, y continuidad de inercia.',
        ];
    }
}
