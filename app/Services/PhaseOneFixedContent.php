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
                'Para observar esas diferencias, a lo largo del informe utilizaremos tres expresiones: armonía, defecto y exceso. Vamos a explicar qué significan con un ejemplo sencillo. Esta situación sirve para presentar los tres estados; cada persona puede reconocerlos en experiencias diferentes.',
                'Imagina que alguien te pide ayuda en un día en el que ya tienes bastantes cosas que hacer. Quieres acompañar a esa persona, pero también necesitas atender tus asuntos y descansar. ¿Cómo podrías responder?',
                '<strong>Cuando hay armonía, puedes tener en cuenta a la otra persona y también a ti.</strong> Te detienes a comprobar si tienes tiempo, si te apetece ayudar y qué puedes ofrecer realmente. Quizá respondas «Hoy puedo ayudarte con esta parte, pero después necesito seguir con lo mío».',
                'También podrías decir que sí con gusto o explicar que ese día no puedes. Lo que indica equilibrio es que tu respuesta incluye tus posibilidades y las de la situación.',
                'La armonía no significa sentir siempre tranquilidad, acertar en todo o conseguir que nadie se moleste. Puedes sentir cierta incomodidad al poner un límite y, aun así, estar respondiendo de una manera que te cuida. Estás utilizando tu capacidad de ayudar sin dejar que ocupe todo tu tiempo.',
                '<strong>Por defecto, alguna capacidad que necesitas tiene poco espacio para expresarse.</strong> En este ejemplo, podría costarte reconocer tu disponibilidad o decir hasta dónde puedes llegar. Antes de preguntarte cómo estás, ya has respondido: «Sí, claro, yo me encargo».',
                'Después descubres que no tenías tiempo o que necesitabas descansar. Tal vez pienses: «¿Por qué he dicho que sí tan rápido?». Aquí observamos si te ha faltado espacio para escucharte, elegir o expresar un límite. La palabra defecto no significa que tengas un defecto como persona. Se refiere a una capacidad que, en esa situación, apenas has podido utilizar.',
                'En otros momentos podría ocurrir de otra forma: necesitas compañía, pero no te permites pedirla; tienes una opinión, pero la dejas sin decir; deseas probar algo, pero no llegas a darte la oportunidad.',
                '<strong>Por exceso, una capacidad se utiliza tanto que empieza a pasar factura.</strong> Siguiendo el mismo ejemplo, puedes estar muy pendiente de ayudar, anticiparte a lo que falta y asumir incluso cosas que nadie te ha pedido. Tu disposición a colaborar ocupa cada vez más espacio: «Ya que estoy, hago también esto. Y esto otro. Así queda todo resuelto».',
                'Al terminar, quizá sientas agotamiento o malestar porque los demás no han visto cuánto has hecho. La capacidad de ayudar está presente, pero necesita una medida. Lo mismo puede suceder con otras cualidades: organizar tanto que cualquier cambio resulte difícil, pensar tanto una decisión que nunca llegue el momento de elegir o cuidar tanto una relación que apenas quede tiempo para ti.',
            ],
            'conclusions' => [
                '<strong>Una misma respuesta puede tener motivos diferentes.</strong> Desde fuera, dos situaciones pueden parecer iguales. Puedes decir que sí porque lo deseas y tienes disponibilidad, porque te cuesta reconocer que puedes negarte o porque sientes que debes resolverlo todo.',
                'Por eso, para comprenderte, necesitamos mirar algo más que la respuesta final. Puedes preguntarte:',
                '<ul><li>¿Qué quería hacer yo?</li><li>¿Me di tiempo para comprobar cómo estaba?</li><li>¿Sentía que podía elegir?</li><li>¿Qué esperaba que ocurriera si decía que no?</li><li>¿Cómo me quedé después de responder?</li></ul>',
                'Estas preguntas te ayudarán a reconocer qué estaba pasando en ese momento. No necesitas clasificar cada conducta de inmediato. A veces comprenderás algo al recordarlo más tarde.',
                '<strong>Qué significa armonizar.</strong> Cuando el informe hable de armonizar, se referirá a encontrar una respuesta que atienda mejor lo que necesitas. Si te ha faltado espacio para expresar tu opinión, puedes empezar por decir una preferencia pequeña. Si has asumido demasiado, puedes revisar el reparto y devolver una tarea que no te corresponde. Si necesitas afecto, quizá el paso sea pedir compañía en lugar de seguir haciendo cosas para que alguien note que estás ahí.',
                'El cambio puede ser tan sencillo como responder: «Antes de decirte que sí, déjame mirar si puedo». Esa pausa te permite escucharte y elegir con más claridad.',
                '<strong>Cómo lo iremos viendo en tu carta.</strong> En cada apartado explicaremos primero qué parte de tu experiencia estamos observando: tus decisiones, tus emociones, tu manera de empezar o tus relaciones. Después veremos qué aportan las posiciones de tu carta, explicando los términos astrológicos a medida que aparezcan.',
                'Encontrarás ejemplos de armonía, defecto y exceso, junto con preguntas y acciones concretas. No son una lista de cosas que debas corregir. Puedes detenerte en una situación que te resulte familiar y preguntarte qué pequeño cambio te habría ayudado.',
                'Quizá descubras que en el trabajo expresas tus límites con facilidad, pero en una relación cercana te cuesta más. O que normalmente disfrutas ayudando y solo empiezas a sobrecargarte cuando algo te preocupa. Esas diferencias importan: nos permiten mirar tu experiencia con más cuidado.',
                'La intención es que puedas reconocer lo que haces bien, comprender dónde te cuesta escucharte y disponer de más opciones la próxima vez.',
            ],
        ];
    }
}
