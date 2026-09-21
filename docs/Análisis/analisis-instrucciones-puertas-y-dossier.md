# Análisis: Instrucciones de continuidad por puerta + Dossier de referencia (Fase 1)

## 1. Qué son estos documentos

Se han añadido 4 documentos nuevos que definen **cómo debe redactarse** la interpretación de Fase 1, más un dossier completo ya redactado (Pastora María Villarreal Muñiz) que sirve como **plantilla de referencia real**.

| Documento | Función |
|---|---|
| `Instrucciones generales de desarrollo y continuidad` | Manual maestro: arquitectura obligatoria de cada puerta, niveles desarrollo/pauta/ejemplo, checklist de revisión |
| `INSTRUCCIONES_CONTINUIDAD_LUNA_FASE_1` | Reglas específicas para la Luna + delta de ampliación sobre el borrador actual del dossier |
| `INSTRUCCIONES_CONTINUIDAD_ASCENDENTE_FASE_1` | Reglas específicas para el Ascendente (incluye regla especial del eje I–VII) |
| `INSTRUCCIONES_CONTINUIDAD_DESCENDENTE_FASE_1` | Reglas específicas para el Descendente (incluye regla especial de regencia doble y ejemplos relacionales) |
| `DOSSIER_CARTA_NATAL_PASTORA_MARIA_VILLARREAL_MUNIZ` | Ejemplo completo y real de un informe de Fase 1, con las 4 puertas desarrolladas, ampliación, práctica y datos técnicos |

Confirmado: no existe un documento "INSTRUCCIONES_CONTINUIDAD_SOL" independiente porque **el bloque del Sol del dossier ya se considera la referencia de calidad terminada** ("el bloque del Sol ya revisado"). Las otras tres guías piden explícitamente igualar ese nivel.

## 2. La arquitectura obligatoria de cada puerta (documento maestro)

Todas las puertas deben seguir el mismo recorrido, con mínimos de extensión que son de contenido, no de relleno:

| Bloque | Mínimo | Pregunta que responde |
|---|---|---|
| Función y posición | 3 párrafos + conexión personal | ¿Qué parte de mi experiencia vamos a observar? |
| Qué necesita el signo | 4 párrafos amplios | ¿De qué manera busca expresarse esta función? |
| Casa o eje | 6 párrafos | ¿En qué ámbito de la vida podemos reconocer esta función? |
| Regente y su posición | 6 párrafos (8–10 si hay doble regencia) | ¿Qué matiz añade el regente? |
| Integración (signo+casa+regente) | 4 párrafos | ¿Qué aparece cuando estas piezas funcionan juntas? |
| Expresión armónica | 4 párrafos + correspondencia completa | ¿Cómo reconozco que funciona con equilibrio? |
| Desarmonía por defecto | 4 párrafos + correspondencia completa | ¿Qué pasa cuando esta capacidad tiene poco espacio? |
| Desarmonía por exceso | 4 párrafos + correspondencia completa | ¿Qué pasa cuando ocupa más espacio del necesario? |
| Armonización e integración final | equivalente al Sol | Recorridos desde defecto/exceso, punto de equilibrio, autoobservación, frases |

**"Correspondencia completa"** significa: si hay N características, debe haber exactamente N pautas y N ejemplos, en el mismo orden. Nunca sustituir una característica por otro tema para "evitar repetición".

### Los tres niveles de redacción (regla transversal)

- **Desarrollo**: explica qué ocurre y por qué.
- **Pauta**: concreta qué observar o practicar.
- **Ejemplo**: escena reconocible + una posible respuesta.

### Reglas de no-repetición

- El texto gris fijo (ver sección 3) no se reescribe ni resume.
- Si una puerta necesita recordar una idea ya presentada (en el texto fijo o en otra puerta), se hace con una referencia breve y luego se añade información nueva.
- Cuando un regente ya apareció en otra puerta, **no se repite su lectura**: se cambia la pregunta funcional (ej. Venus ya explicado en el Sol → en la Luna se pregunta cómo esa misma Venus canaliza la necesidad emocional, no qué es Venus).
- Nunca se atribuyen causas históricas/familiares/traumáticas no contadas por la persona. Signo y casa abren preguntas, no prueban biografía.
- Lenguaje no determinista siempre: posibilidades a observar, no hechos ni predicciones.

## 3. Regla del texto fijo en gris

Confirmado por el usuario: **los párrafos resaltados en gris del dossier son texto fijo y general**, común a todas las cartas de Fase 1 que se generen. No se reescriben ni se resumen; solo se adapta:

- `[Nombre]` de la persona.
- Concordancia gramatical.
- Datos personales explícitos dentro de una frase variable.

Identifico como texto fijo (gris) en el dossier analizado:

- Toda la sección **"Tu primera lectura"** (páginas 3-5): explicación de las cuatro puertas, sus preguntas centrales, advertencia de no-determinismo.
- La sección **"ESTADOS DE CADA PUERTA: cómo reconocer armonía, defecto y exceso"** con su ejemplo genérico (páginas 5-6).
- La sección **"CONCLUSIONES IMPORTANTES PARA TU LECTURA"** completa (página 7): motivos distintos tras una misma respuesta, qué significa armonizar, cómo se leerá la carta.

Esto es **texto de plantilla puro**: no depende de signo, casa ni persona. Debe vivir en nuestro sistema como una plantilla base versionada (`templates`, `content_type = intro_fase1`), reutilizada sin cambios salvo el nombre.

Lo que **no** es texto fijo (aunque a veces se presenta en un recuadro de color distinto, como los recuadros naranjas de "Qué necesita este signo" o "El regente y su posición"): esos son encabezados de sección con contenido 100% personalizado (signo, casa, grado y regente reales de la persona).

## 4. Lo específico de cada puerta

### Luna
- Pregunta central: **"¿Qué estoy sintiendo y qué necesito en este momento?"**
- Se diferencia de: Sol (identidad/elección), Ascendente (ritmo de entrada), Descendente (negociación con el otro).
- Regla de continuidad Sol→Luna: si el regente de la Luna ya se explicó en el Sol, no se repite; se retoma lo imprescindible y se cambia la pregunta hacia la regulación emocional.
- Estados: 7 características en defecto y 7 en exceso (definidas ya en el borrador del dossier, deben conservarse).

### Ascendente
- Pregunta central: **"¿Cómo puedo dar este paso de una manera que pueda sostener?"**
- No es "la imagen que doy": es cómo entro, me oriento, qué necesito para dar un primer paso, ritmo corporal/atencional.
- **Regla especial del eje I–VII**: el Ascendente narra desde "yo entro y me posiciono"; la casa VII solo se menciona como contrapunto breve. El desarrollo profundo de confianza/intimidad se reserva para el Descendente (evita duplicar el mismo bloque en las dos puertas).
- Estados: 7 características en defecto y 7 en exceso.

### Descendente
- Pregunta central: **"¿Cómo puedo compartir mi vida sin dejar de escucharme?"**
- No predice qué tipo de persona llegará a la vida de quien consulta; no convierte cada conducta ajena en "proyección propia" del consultante.
- **Regla especial de regencia doble**: si el signo tiene regente tradicional y moderno (caso de Escorpio: Marte + Plutón), se recomienda 8–10 párrafos: introducción común + 3 sobre el tradicional + 3 sobre el moderno + 1-2 relacionándolos. El regente tradicional aporta una vía de acción directa; el moderno aporta una capa generacional/transformadora — advertir que Plutón permanece años en un signo (no es un rasgo individual exclusivo).
- Distingue explícitamente **deseo, petición, acuerdo y norma**: desear algo no obliga al otro; pedir permite respuesta; acordar crea responsabilidad compartida; una norma exige aceptación real.
- Estados: 5 características en armonía (no 7), 7 en defecto y 7 en exceso.

## 5. El dossier de Pastora como plantilla completa

Estructura real de un informe terminado, en este orden:

1. Portada.
2. Recorrido del dossier (índice con enlaces internos).
3. "Tu primera lectura" (texto fijo).
4. Introducción breve a las 4 puertas con tabla Puerta/Posición/Pregunta (parcialmente personalizado).
5. Estados de cada puerta explicados con un ejemplo genérico (texto fijo).
6. "Conclusiones importantes" (texto fijo).
7. **Primera puerta: el Sol** — desarrollado en el mayor nivel de detalle (referencia de calidad).
8. **Segunda puerta: la Luna** — en el dossier actual está *menos desarrollada* que el Sol (esto es justo lo que la guía de Luna pide ampliar).
9. **Tercera puerta: el Ascendente** — mismo caso, pendiente de ampliar según su guía.
10. **Cuarta puerta: el Descendente** — mismo caso, con doble regencia (Marte + Plutón).
11. **Las cuatro puertas juntas**: tabla resumen, relación Sol↔Luna vía regencia, Venus como vínculo Sol/Ascendente, una escena que integra las 4 puertas, una "secuencia posible para reconocer a tiempo" (patrón de 5 pasos reutilizable).
12. **Ampliación del mapa interior**: Mercurio, Venus, Marte, Júpiter, Saturno, Urano, Neptuno, Plutón — todos con signo/casa, recurso, exceso/defecto breve, ejemplo y "pregunta útil". Esto es contenido complementario fuera de las 4 puertas principales, aún dentro del criterio tropical/Placidus, sin aspectos ni tránsitos.
13. **Práctica de autoobservación**: plan de 4 semanas (una por puerta) + hoja de registro diario duplicable con las 4 preguntas centrales de las puertas.
14. **Cierre de la primera lectura**: síntesis final + frase de integración global.
15. **Datos y criterios de cálculo** (apéndice técnico): fecha/hora/lugar, ajuste de huso horario documentado con fuente oficial (BOE), coordenadas, motor usado (Swiss Ephemeris), tabla de posiciones y casas, regencias aplicadas, aclaración de qué NO se ha calculado todavía (nodos, Lilith, orbes, aspectos, tránsitos, revolución solar), y referencias verificables (BOE, timeanddate, astro.com).

### Detalle importante de precisión técnica

El dossier documenta explícitamente:
- El sistema de cálculo (Swiss Ephemeris 2.10.03 vía pysweph, efemérides Moshier) — coherente con lo ya implementado en `aplicacion`.
- El ajuste de horario de verano con cita a la fuente legal oficial (BOE) para ese año/país concreto — esto es exactamente lo que ya resuelve nuestro `TimezoneResolver`.
- Una advertencia de sensibilidad de casa cuando un planeta está muy cerca de una cúspide (Venus a 1°47' del Descendente) — dato que deberíamos poder señalar automáticamente en el snapshot.
- Aclara qué regencias se usan: Venus (Libra y Tauro), Sol (Leo), Marte (tradicional de Escorpio) y Plutón (moderno de Escorpio) — coincide con nuestro protocolo de regencia dual ya implementado en `RegencyResolver`.

## 6. Implicaciones para la aplicación (`aplicacion`)

Esto define con precisión qué debe producir nuestro motor de plantillas (`ChartTemplate` / `Interpretation`):

1. **Una plantilla de texto fijo única** (el bloque gris), parametrizada solo por `[Nombre]`, reutilizable para las 4 puertas y para todas las cartas.
2. **Un catálogo de contenido por puerta** con 8 bloques obligatorios cada uno (función, signo, casa/eje, regente, integración, armonía, defecto, exceso) + cierre de armonización.
3. **Reglas de correspondencia estructural** que el motor debe validar: número de características = número de pautas = número de ejemplos, mismo orden.
4. **Un mecanismo de "regente ya usado"**: si Venus/Sol/Marte/Plutón ya aparecieron como regentes de una puerta anterior en la misma carta, la siguiente puerta debe generar una pregunta funcional distinta en vez de repetir la explicación base del planeta. Esto requiere que el motor sepa qué regentes ya se "consumieron" en puertas previas de esa misma carta.
5. **Reglas especiales de eje I–VII**: Ascendente y Descendente comparten signo/casa opuestos y deben evitar duplicar contenido; el motor necesita saber qué mitad del eje corresponde a cada puerta.
6. **Soporte de regencia doble** (tradicional + moderno) ya cubierto por `RegencyResolver`, pero el render debe usar la plantilla de 8-10 párrafos cuando haya dos regentes con igual peso.
7. **Sección de "ampliación"**: catálogo adicional para los demás planetas (Mercurio, Marte si no es regente, Júpiter, Saturno, Urano, Neptuno) con menor profundidad, mismo criterio tropical/Placidus, sin aspectos.
8. **Apéndice técnico obligatorio**: cada carta generada debe incluir su propia sección de "Datos y criterios de cálculo" con fuente del huso horario, coordenadas, motor y versión, advertencias de sensibilidad de cúspide — esto ya es coherente con nuestro `ChartSnapshot` guardado en `charts.snapshot`, solo falta renderizarlo como sección visible del informe.
9. **Checklist de revisión** de cada documento (¿responde a su pregunta?, ¿evita repetir el texto gris?, ¿mantiene correspondencia característica→pauta→ejemplo?) podría implementarse como una validación automática antes de marcar una `Interpretation` como publicada.

## 7. Siguiente paso recomendado

Antes de generar contenido con estas reglas, conviene:

1. Diseñar el esquema de `templates` para separar explícitamente **texto fijo compartido** de **bloques personalizables por puerta**.
2. Modelar el catálogo de "regentes ya usados por carta" para evitar repeticiones entre puertas.
3. Empezar por completar/ampliar el bloque del Sol como referencia (ya cumple el estándar) y usarlo de patrón para escribir el catálogo de contenido de Luna, Ascendente y Descendente siguiendo estas mismas guías.
