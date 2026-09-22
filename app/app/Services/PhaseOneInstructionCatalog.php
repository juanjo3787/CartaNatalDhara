<?php

namespace App\Services;

use InvalidArgumentException;

final class PhaseOneInstructionCatalog
{
    private const SOURCES = [
        'sol' => 'docs/Instrucciones generales de desarrollo y continuidad.pdf',
        'luna' => 'docs/INSTRUCCIONES_CONTINUIDAD_LUNA_FASE_1.pdf',
        'ascendente' => 'docs/INSTRUCCIONES_CONTINUIDAD_ASCENDENTE_FASE_1.pdf',
        'descendente' => 'docs/INSTRUCCIONES_CONTINUIDAD_DESCENDENTE_FASE_1.pdf',
    ];

    /** @return array{source: string, rules: list<string>} */
    public function forDoor(string $door): array
    {
        if (! isset(self::SOURCES[$door])) {
            throw new InvalidArgumentException("Puerta Fase 1 no válida: {$door}");
        }

        $rules = [
            'sol' => [
                'Mantén el Sol centrado en identidad, voluntad, elección y dirección personal.',
                'Explica primero la función del Sol y después cómo el signo, la casa y el regente modifican esa función.',
                'No conviertas la identidad en una etiqueta fija ni confundas cooperación con renuncia al criterio propio.',
            ],
            'luna' => [
                'Mantén la Luna centrada en necesidades emocionales, seguridad, afecto, regulación y respuestas espontáneas.',
                'Distingue sentir una necesidad de obedecerla automáticamente y diferencia cuidado de aprobación externa.',
                'Si el regente lunar ya apareció en el Sol, retómalo desde la regulación emocional y no repitas su definición.',
            ],
            'ascendente' => [
                'Mantén el Ascendente centrado en cómo la persona entra, se orienta, se posiciona y da el primer paso.',
                'Trata el eje I-VII desde la perspectiva del Ascendente; la casa VII solo puede aparecer como contrapunto breve.',
                'No conviertas el Ascendente en una máscara ni en un diagnóstico: describe ritmo, atención, recursos y práctica.',
            ],
            'descendente' => [
                'Mantén el Descendente centrado en el encuentro con otra persona, la reciprocidad, la confianza y los acuerdos.',
                'Distingue siempre deseo, petición, acuerdo y norma; no atribuyas intenciones ni predigas qué persona llegará.',
                'Para Escorpio o cualquier doble regencia, distingue la vía tradicional directa de la capa moderna transformadora y generacional.',
            ],
        ][$door];

        return ['source' => self::SOURCES[$door], 'rules' => $rules];
    }

    /** @return list<string> */
    public function general(): array
    {
        return [
            'Redacta para una persona sin conocimientos de astrología, con lenguaje cercano, claro y concreto.',
            'Explica cada término la primera vez y conecta la interpretación con experiencias comprensibles sin inventar biografía.',
            'Revisa el contenido anterior antes de redactar; cada apartado debe responder una pregunta diferente y aportar información propia.',
            'Distingue desarrollo, pauta y ejemplo. No rellenes extensiones con reformulaciones ni conclusiones repetidas.',
            'Conserva las características proporcionadas y mantén una correspondencia exacta entre característica, pauta y ejemplo.',
            'Sigue el orden función, signo, casa o eje, regente, integración, armonía, defecto y exceso.',
            'La armonía es un uso proporcionado, el defecto es falta de espacio y el exceso es una capacidad rígida o desproporcionada.',
            'Revisa continuidad, repeticiones, coherencia de datos y comprensión desde la perspectiva de quien lee el informe.',
            'No hagas predicciones, diagnósticos ni afirmaciones biográficas; signo y casa abren preguntas, no demuestran hechos.',
        ];
    }
}