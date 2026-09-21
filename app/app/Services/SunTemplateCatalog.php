<?php

namespace App\Services;

use App\Domain\Astrology\DoorSequence;

final class SunTemplateCatalog
{
    public function door(): string
    {
        return DoorSequence::SOL;
    }

    /**
     * @return array<string, string>
     */
    public function sharedBlocks(): array
    {
        return [
            'shared_intro' => 'La carta natal pone en primer plano la forma en que la persona se reconoce a sí misma y se orienta hacia su propósito, su dignidad de ser y su capacidad para sostener su identidad en el mundo.',
            'shared_states' => 'El Sol expresa la necesidad de claridad, de coherencia y de una dirección que permita sentirse auténtico, visible y sostenido por una identidad reconocible.',
            'shared_conclusions' => 'Cuando la persona integra este centro vital, encuentra equilibrio entre su impulso de expresión personal y la necesidad de servir desde una verdad interior clara.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function doorBlocks(): array
    {
        return [
            'function' => 'El Sol representa la función fundamental de la persona: su necesidad de afirmarse, expresarse, mostrarse tal cual es y sostener una identidad reconocible.',
            'sign' => 'El signo solar revela la forma más natural en que la persona ilumina, decide y reivindica su valor propio dentro de la experiencia vital.',
            'house' => 'La casa donde se encuentra el Sol describe el ámbito de la vida en el que la persona siente que debe hacerse visible y tomar posición con autoridad.',
            'ruler' => 'El regente del Sol aporta la clave del modo en que este eje de identidad se organiza y se mueve en relación con las decisiones de vida.',
            'integration' => 'La integración del Sol ocurre cuando la persona acepta su centro, deja de buscar validación exterior y actúa desde su propia verdad.',
            'harmony' => 'La armonía solar se expresa cuando la persona puede ser clara, consciente y coherente sin depender de aprobación ajena.',
            'deficit' => 'El defecto aparece cuando la persona se desarma, duda de su valor, necesita aprobación o se siente insegura de su lugar.',
            'excess' => 'El exceso se manifiesta cuando la identidad se vuelve rígida, dominadora o demasiado centrada en el ego y en la necesidad de ser observado.',
            'closing' => 'La solución es desarrollar una identidad serena, auténtica y sostenible, que permita expresarse sin perder su centro ni su sentido de verdad.',
        ];
    }
}
