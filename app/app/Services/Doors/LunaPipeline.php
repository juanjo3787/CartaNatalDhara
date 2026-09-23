<?php

namespace App\Services\Doors;

final class LunaPipeline extends AbstractDoorPipeline
{
    public function door(): string
    {
        return 'luna';
    }

    public function label(): string
    {
        return 'la Luna';
    }

    public function focus(): string
    {
        return 'las necesidades emocionales, la seguridad, el afecto, la regulación y el cuidado';
    }

    public function rulerParagraphCount(): int
    {
        return 6;
    }

    protected function functionRequirement(): string
    {
        return '3 párrafos de al menos 70 palabras que expliquen ampliamente la función de la Luna: qué representa una necesidad emocional, cómo puede aparecer una emoción antes de poder explicarla, la diferencia entre sentir y actuar, la regulación emocional, la seguridad, la capacidad de pedir apoyo, la intimidad y la capacidad de reconocer lo que se necesita. Diferencia explícitamente la Luna (necesidad emocional y regulación) del Sol (identidad y voluntad), el Ascendente (orientación e inicio) y el Descendente (vínculo y negociación).';
    }

    protected function signRequirement(): string
    {
        return '4 párrafos de al menos 70 palabras sobre cómo este signo lunar concreto necesita sentir seguridad, expresar afecto y regular sus emociones. Responde con matices propios de esta carta a preguntas como: ¿qué estoy sintiendo?, ¿qué necesito?, ¿qué me ayuda a recuperar seguridad?, ¿qué gesto afectivo necesito?, ¿estoy expresando la necesidad o esperando que se descubra sola?, ¿cómo distingo emoción, interpretación y necesidad?';
    }

    protected function houseRequirement(): string
    {
        return '6 párrafos de al menos 80 palabras sobre el territorio de la casa lunar: qué situaciones cotidianas activan la necesidad de seguridad emocional, qué responsabilidades o hábitos de cuidado aparecen en ese territorio, y cómo cambia con el tiempo. Separa siempre casa y signo.';
    }

    protected function rulerRequirement(): string
    {
        return "{$this->rulerParagraphCount()} párrafos de al menos 80 palabras: explica qué aporta el regente del signo lunar a la regulación emocional, el cuidado y la seguridad; después su signo y su casa; si hay dos regentes, trátalos por separado antes de integrarlos. Usa exclusivamente las posiciones de ASTROLOGICAL_FACTS.";
    }

    protected function integrationRequirement(): string
    {
        return '4 párrafos de al menos 70 palabras que integren Luna + signo + casa + regente + signo del regente + casa del regente, mostrando consecuencias que solo aparecen al reunir estas piezas: cómo se regula la emoción, cómo se pide apoyo y cómo se sostiene la seguridad afectiva en la vida cotidiana.';
    }

    protected function stateThemes(string $stage): string
    {
        return match ($stage) {
            'harmony' => 'Explora, adaptado a la posición real y sin repetir estas palabras literalmente, cómo la persona reconoce lo que siente, da espacio a la emoción sin quedar dominada por ella, puede pedir y recibir afecto, distingue la respuesta ajena de su propio valor, y permite alegría, vulnerabilidad y expresión.',
            'deficit' => 'Explora patrones de desconexión de la propia necesidad emocional (por ejemplo: ocultar la necesidad de afecto, minimizar una emoción o la alegría, cuidar antes de pedir, esperar a que el otro adivine la necesidad, reducirla para no molestar, desconectarse del disfrute o esconder la vulnerabilidad), adaptados siempre a signo, casa y regente reales; no los reproduzcas literalmente.',
            'excess' => 'Explora cómo una necesidad emocional legítima puede ocupar demasiado espacio (por ejemplo: buscar reconocimiento de forma continua, interpretar una diferencia como falta de cariño, sobrecuidar para obtener confirmación, dejar que una emoción momentánea ocupe toda la escena, esperar una devolución afectiva idéntica, o usar el orgullo o la intensidad como protección), adaptado siempre a signo, casa y regente; no reproduzcas estos ejemplos literalmente.',
            default => '',
        };
    }

    protected function harmonizationRequirement(): string
    {
        return "Explica específicamente qué significa recuperar espacio y medida PARA ESTA LUNA, no con una fórmula genérica. Desde el defecto: 2-4 párrafos y 3 puntos concretos sobre cómo devolver lenguaje, espacio y petición a la necesidad emocional. Desde el exceso: 2-4 párrafos y 3 puntos sobre cómo conservar sensibilidad y expresión recuperando medida. El punto de equilibrio debe integrar realmente Luna + signo + casa + regente, con varios párrafos y 4 referencias observables.";
    }

    protected function closingRequirement(): string
    {
        return 'Un párrafo que explique cómo usar 5 preguntas de autoobservación específicas de la Luna (por ejemplo sobre qué se siente, qué se necesita, qué ayuda a recuperar seguridad, qué gesto afectivo se necesita, o si la necesidad se expresa o se espera que se descubra sola). Una frase central y cuatro frases de apoyo: empezar, recuperar medida, revisar y reunir lo aprendido.';
    }

    protected function additionalSystemRules(): array
    {
        return [
            'La Luna no es una versión emocional del Sol: responde a necesidad emocional, seguridad, respuesta espontánea, regulación, cuidado, vulnerabilidad, afecto, refugio y capacidad de pedir y recibir; no a identidad ni voluntad.',
            'No utilices frases genéricas que podrían aparecer igual en Sol, Ascendente o Descendente; cada párrafo debe usar el signo, la casa y el regente lunares concretos de esta carta.',
        ];
    }
}
