<?php

namespace App\Services;

final class PhaseOneFixedContent
{
    /**
     * Text marked as shared/gray in the reference dossier.
     * It is reused for every Phase 1 report and only receives the person's name.
     *
     * @return array{intro: list<string>, states: list<string>, conclusions: list<string>}
     */
    public function sections(string $name): array
    {
        return [
            'intro' => [
                sprintf('%s, no necesitas saber astrología para leer este informe. Vamos a comenzar por algo que ya conoces: tu propia vida. Cómo tomas decisiones, qué te hace sentir querida, qué necesitas cuando algo cambia y cómo te encuentras en tus relaciones.', $name),
                'Puede que alguna vez hayas aceptado algo por mantener un buen ambiente y después hayas descubierto que preferías otra cosa. O que hayas cuidado de alguien sin saber muy bien cómo pedir el cuidado que tú necesitabas. También habrá momentos en los que hayas elegido con claridad, expresado lo que sentías o encontrado una manera de estar cerca de otra persona sin dejar de atenderte.',
                'Este informe te invita a mirar situaciones como esas con un poco más de atención. No damos por hecho que te ocurran: las utilizaremos como ejemplos para ayudarte a reconocer cómo vives tú cada experiencia.',
                'Para hacerlo, vamos a recorrer cuatro partes de tu carta natal. Podemos entenderlas como cuatro puertas, cada una abierta a una pregunta diferente.',
                'El Sol nos acerca a lo que quieres para ti: tu identidad, voluntad, dirección y desarrollo del yo. Hablaremos de tus elecciones, de aquello que deseas desarrollar y del espacio que das a tu propia opinión cuando también tienes en cuenta a los demás. Su pregunta será: «¿Qué quiero yo y cómo puedo darle un lugar en mi vida?».',
                'La Luna nos acerca a lo que necesitas emocionalmente y a tu seguridad. Nos detendremos en lo que te hace sentir querida, en cómo reaccionas cuando algo te duele y en qué tipo de compañía o cuidado te ayuda. Su pregunta será: «¿Qué estoy sintiendo y qué necesito en este momento?».',
                'El Ascendente nos ayuda a observar cómo empiezas, cómo entras y respondes a la vida. Veremos cómo llegas a una situación nueva, qué necesitas antes de tomar una decisión y qué ritmo te permite avanzar con confianza. Su pregunta será: «¿Cómo puedo dar este paso de una manera que pueda sostener?».',
                'El Descendente nos lleva a tus relaciones, a tu aprendizaje a través del vínculo y del otro. Exploraremos cómo construyes confianza, qué esperas de un vínculo y cómo expresas tus deseos y límites cuando otra persona también tiene los suyos. Su pregunta será: «¿Cómo puedo compartir mi vida sin dejar de escucharme?».',
                'Estas partes pueden aparecer juntas en una misma situación. Imagina, por ejemplo, que alguien importante para ti te pide ayuda. Quizá quieras acompañarle y, al mismo tiempo, estés cansada. Puede que necesites pensar cuánto tiempo puedes ofrecer o que te cueste decirlo por miedo a decepcionar. Detenerte a distinguir qué quieres, cómo te sientes y qué puedes sostener puede ayudarte a dar una respuesta más clara.',
                'A lo largo del informe encontrarás nombres de signos y casas. Los explicaremos cuando aparezcan, con palabras sencillas y ejemplos cotidianos. No necesitas memorizarlos: lo importante será comprender qué pregunta te propone cada apartado y comprobar si tiene sentido en tu experiencia.',
                'Utilizaremos la astrología como un lenguaje simbólico para reflexionar. No puede demostrar cómo eres ni predecir lo que te sucederá. Tú podrás reconocer qué explicaciones te ayudan, cuáles no te representan y qué preguntas deseas seguir explorando.',
                'Puedes leer despacio. Si una frase te recuerda una situación, detente ahí: ¿qué ocurrió?, ¿qué necesitabas?, ¿pudiste expresarlo? A veces, una sola pregunta bien comprendida puede ser un buen comienzo para conocerte un poco mejor.',
            ],
            'states' => [
                sprintf('%s, puede que hayas notado que no respondes siempre de la misma manera, incluso ante situaciones parecidas. Hay días en los que puedes decir lo que necesitas con tranquilidad. Otros, te cuesta encontrar las palabras y acabas callando. Y también puede ocurrir que hayas guardado tantas pequeñas molestias que, cuando finalmente hablas, salga todo junto.', $name),
                'Para observar esas diferencias, a lo largo del informe utilizaremos tres expresiones: armonía, defecto y exceso. Esta situación sirve para presentar los tres estados; cada persona puede reconocerlos en experiencias diferentes.',
                'Cuando hay armonía, puedes tener en cuenta a la otra persona y también a ti. Te detienes a comprobar si tienes tiempo, si te apetece ayudar y qué puedes ofrecer realmente. La armonía no significa sentir siempre tranquilidad, acertar en todo o conseguir que nadie se moleste.',
                'Por defecto, alguna capacidad que necesitas tiene poco espacio para expresarse. La palabra defecto no significa que tengas un defecto como persona. Se refiere a una capacidad que, en esa situación, apenas has podido utilizar.',
                'Por exceso, una capacidad se utiliza tanto que empieza a pasar factura. La capacidad está presente, pero necesita una medida. Lo mismo puede suceder con otras cualidades: organizar tanto que cualquier cambio resulte difícil, pensar tanto una decisión que nunca llegue el momento de elegir o cuidar tanto una relación que apenas quede tiempo para ti.',
            ],
            'conclusions' => [
                'Una misma respuesta puede tener motivos diferentes. Desde fuera, dos situaciones pueden parecer iguales. Puedes decir que sí porque lo deseas y tienes disponibilidad, porque te cuesta reconocer que puedes negarte o porque sientes que debes resolverlo todo.',
                'Por eso, para comprenderte, necesitamos mirar algo más que la respuesta final. Puedes preguntarte: ¿qué quería hacer yo?, ¿me di tiempo para comprobar cómo estaba?, ¿sentía que podía elegir?, ¿qué esperaba que ocurriera si decía que no?, ¿cómo me quedé después de responder?',
                'Cuando el informe hable de armonizar, se referirá a encontrar una respuesta que atienda mejor lo que necesitas. Si te ha faltado espacio para expresar tu opinión, puedes empezar por decir una preferencia pequeña. Si has asumido demasiado, puedes revisar el reparto. Si necesitas afecto, quizá el paso sea pedir compañía en lugar de seguir haciendo cosas para que alguien note que estás ahí.',
                'El cambio puede ser tan sencillo como responder: «Antes de decirte que sí, déjame mirar si puedo». Esa pausa te permite escucharte y elegir con más claridad.',
                'En cada apartado explicaremos primero qué parte de tu experiencia estamos observando: tus decisiones, tus emociones, tu manera de empezar o tus relaciones. Después veremos qué aportan las posiciones de tu carta, explicando los términos astrológicos a medida que aparezcan.',
                'Encontrarás ejemplos de armonía, defecto y exceso, junto con preguntas y acciones concretas. No son una lista de cosas que debas corregir. Puedes detenerte en una situación que te resulte familiar y preguntarte qué pequeño cambio te habría ayudado.',
                'La intención es que puedas reconocer lo que haces bien, comprender dónde te cuesta escucharte y disponer de más opciones la próxima vez.',
            ],
        ];
    }
}
