<?php

namespace App\Services\Doors;

final class DescendentePipeline extends AbstractDoorPipeline
{
    public function door(): string
    {
        return 'descendente';
    }

    public function label(): string
    {
        return 'el Descendente';
    }

    public function focus(): string
    {
        return 'el encuentro con otra persona, la reciprocidad, la confianza, los acuerdos y la autonomía compartida';
    }

    public function rulerParagraphCount(): int
    {
        return 8;
    }

    protected function functionRequirement(): string
    {
        return '3 párrafos de al menos 70 palabras que expliquen que el vínculo empieza cuando existe otra persona con voluntad propia, necesidades propias, límites propios y capacidad real de decir sí o no. Diferencia deseo, petición, acuerdo y obligación; explica que una preferencia expresada por una persona no es todavía un acuerdo. Diferencia el Descendente de Sol (identidad), Luna (necesidad emocional) y Ascendente (inicio y orientación).';
    }

    protected function signRequirement(): string
    {
        return '4 párrafos de al menos 70 palabras sobre cómo este signo del Descendente concreto se expresa en el encuentro con otra persona: reciprocidad, confianza, límites, privacidad y autonomía compartida.';
    }

    protected function houseRequirement(): string
    {
        return '6 párrafos de al menos 80 palabras que interpreten la Casa VII como encuentro de igual a igual: no la limites a la pareja; incluye amistades significativas, asociaciones, colaboraciones, acuerdos y proyectos compartidos.';
    }

    protected function rulerRequirement(): string
    {
        return "{$this->rulerParagraphCount()} párrafos de al menos 80 palabras. Si el signo tiene regente tradicional y regente moderno con posiciones distintas en ASTROLOGICAL_FACTS, trátalos POR SEPARADO: primero el regente tradicional (planeta, signo, casa y función que aporta al vínculo), después el regente moderno (planeta, signo, casa y función que aporta), y solo entonces intégralos en un párrafo final. No fusiones sus posiciones en una sola frase si los datos indican signos o casas distintos; cada posición debe proceder exclusivamente de ASTROLOGICAL_FACTS.";
    }

    protected function integrationRequirement(): string
    {
        return '4 párrafos de al menos 70 palabras que integren Descendente + signo + Casa VII + regente o regentes + signo del regente + casa del regente, mostrando qué cambia porque existe otra voluntad: qué se pregunta, qué se acuerda, qué se revisa y qué queda fuera del control propio.';
    }

    protected function stateThemes(string $stage): string
    {
        return match ($stage) {
            'harmony' => 'Explora, adaptado a la posición real, expresar deseos sin exigir, pedir dejando una respuesta real, acuerdos claros, límites, privacidad, autonomía, confianza basada en hechos, capacidad de revisar acuerdos, reparación después de tensiones y escucha de diferencias.',
            'deficit' => 'Explora patrones como reservar lo importante, no expresar deseos, aplazar límites, evitar preguntas, no revisar acuerdos, delegar la propia decisión, o cerrar la confianza antes de comprobar. Adapta siempre a la combinación real.',
            'excess' => 'Explora patrones como comprobar continuamente la confianza, controlar, pedir confirmación constante, interpretar demasiado, gestionar el proceso del otro, convertir un criterio propio en norma compartida, confundir compromiso con disponibilidad ilimitada, o hacer del vínculo el centro de todas las decisiones. Adapta al signo, casa y regentes concretos.',
            default => '',
        };
    }

    protected function harmonizationRequirement(): string
    {
        return 'Explica específicamente qué significa recuperar espacio y medida PARA ESTE DESCENDENTE, no con una fórmula genérica. Desde el defecto: 2-4 párrafos y 3 puntos concretos sobre cómo recuperar voz, deseo, pregunta, límite y negociación. Desde el exceso: 2-4 párrafos y 3 puntos sobre cómo recuperar autonomía, proporcionalidad y respeto por el proceso ajeno. El punto de equilibrio debe explicar cómo profundidad, confianza, autonomía y acuerdos pueden convivir, integrando Descendente + signo + Casa VII + regente o regentes, con varios párrafos y 4 referencias observables.';
    }

    protected function closingRequirement(): string
    {
        return 'Un párrafo que explique cómo usar 5 preguntas de autoobservación específicas del Descendente (por ejemplo sobre qué se desea frente a qué se pide, qué acuerdo está realmente vigente, o qué límite necesita revisarse). Una frase central y cuatro frases de apoyo: empezar, recuperar medida, revisar y reunir lo aprendido.';
    }

    protected function additionalSystemRules(): array
    {
        return [
            'El Descendente exige un encuentro entre dos voluntades, no solo la interpretación de una posición individual; distingue siempre deseo, petición, acuerdo y obligación.',
            'Si el signo tiene regente tradicional y regente moderno con posiciones distintas en ASTROLOGICAL_FACTS, interprétalos por separado antes de integrarlos; nunca escribas que ambos comparten signo o casa si los datos indican posiciones diferentes.',
        ];
    }
}
