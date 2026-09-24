# Auditoría de estructura y geometría del informe

Fecha: 24 de septiembre de 2026. Caso original: carta 4.

## Causa del solapamiento

El motor es Dompdf 3.1, a través de barryvdh/laravel-dompdf. El controlador dibuja header y footer mediante `canvas->page_script`, después de paginar. No hay `headerTemplate` ni un segundo header fijo dentro del documento. Las barras `position: fixed` corresponden a la interfaz web y se excluyen del PDF.

`layouts/app.blade.php` declaraba `@page { margin: 18mm }`, pero `reports/phase-one/template.blade.php` añadía `html, body { margin: 0 }`. Dompdf aplica los estilos del elemento raíz al estilo de página (`Css/Stylesheet.php`), sobrescribiendo esos márgenes. La medición del containing block antes del cambio daba Y=0 y altura=841,89 pt. El padding de las secciones solo protegía su comienzo; no sus continuaciones. La página 14 reproducía claramente la invasión del header.

Se elimina el reset de margen de `html`, se conserva el de `body` y se retira el padding vertical usado como reserva de cabecera en secciones interiores. `PdfPageGeometry` centraliza la geometría compartida con el CSS y el lienzo:

| Zona | Coordenada en puntos A4 |
| --- | --- |
| Header: texto de 7,5 pt | Y=20 |
| Límite inferior del header, incluida línea | Y=32 |
| Inicio del área útil | Y=51,0236 |
| Fin del área útil | Y=790,8664 |
| Inicio del footer, incluida línea | Y=808,89 |
| Texto del footer | Y=817,89 |

La reserva superior e inferior es de 18 mm por página; las separaciones con los límites de header/footer son 19,02 y 18,02 pt. La portada sigue sin header/footer. La llamada a `canvas->new_page()` para inicios impares no fue la causa del solapamiento reproducido y se conserva.

## Procedencia de las cabeceras

La plantilla tenía un condicional `! $solarAi` que escribía las tres cabeceras antes de recorrer los párrafos. Se ejecutaba en Luna, Ascendente y Descendente; el Sol quedaba excluido. Además, solo el Sol podía insertar directamente bloques `<p>` y `<h3>`: las otras puertas recibían envoltorios `<p>` adicionales, produciendo HTML anidado inválido.

La eliminación del condicional ya estaba como modificación local al comenzar esta auditoría. Se conserva y se completa unificando la inserción de bloques para las cuatro puertas. `ReportState` valida y representa desarrollo y tres listas; su renderer es la autoridad única de las cabeceras. Web y PDF reciben los mismos bloques del modelo de informe.

| Capa del caso histórico | Evidencia |
| --- | --- |
| RAW OpenAI | No conservado: no puede compararse retrospectivamente |
| Parser/normalización histórica | No se conservó su salida; el código decodifica JSON y valida las etapas |
| Persistencia, Luna exceso | Interpretación 1223: desarrollo primero y una cabecera de características |
| Persistencia, Ascendente exceso | Interpretación 1234: desarrollo primero y una cabecera de características |
| Persistencia, Descendente exceso | Interpretación 1245: desarrollo primero y una cabecera de características |
| Sol exceso, control | Interpretación 1256: estructura correcta |
| Modelo anterior | Divide el HTML guardado en bloques; no antepone las tres cabeceras |
| Plantilla compartida web/PDF anterior | Anteponía las tres cabeceras únicamente en las puertas no solares |

Las generaciones registradas 124 (Luna), 125 (Ascendente), 126 (Descendente) y 134 (Sol) tienen prompts por etapas. Existe también un camino monolítico antiguo en `PhaseOnePromptBuilder` para contextos sin `astrological_facts`; el flujo actual de las cuatro puertas recibe esos datos y utiliza `AbstractDoorPipeline`. No se han cambiado los prompts.

Las frases «Antes de aplicar símbolos, explico términos prácticos: Descendente...» y «Práctica y recursos para no sobredimensionar la capacidad útil...» están en el contenido IA persistido de la interpretación 1245. No se encontraron como literales en código, plantillas, seeds ni fallbacks. La fuente comprobada es la base de datos, asociada a una generación IA por etapas; sin RAW histórico no se puede certificar el texto original del proveedor. Se conservan íntegramente como narrativa, sin eliminarlas por parecer antiguas.

## Integridad y versiones

- `SECTION_SCHEMA_CONTAMINATION` rechaza cabeceras en desarrollo, listas incompletas, IDs duplicados, orden incorrecto y cabeceras consecutivas vacías.
- El validador de etapas utiliza sus reintentos existentes: se repite la etapa que falló, sin reducir extensión.
- La persistencia ya reemplazaba una puerta en transacción. La combinación recursiva sí podía conservar la cola de una lista antigua: ahora las listas se sustituyen, salvo los dos lotes de ejemplos, unidos por ID. Una lista completa de siete ejemplos sustituye toda la anterior.
- La caché de borrador incluye carta, puerta, sesión, versión de schema y versión de prompt; cada estado permanece identificado dentro del borrador. El modelo declara `report_schema_version`; los estados declaran `section_schema_version`; las nuevas plantillas IA usan la versión actual.
- El HTML histórico compatible se convierte explícitamente a un estado validado. El incompatible se rechaza sin borrar contenido. La respuesta HTTP 422 permite regenerar solo la puerta afectada, evitando un 500 genérico por esta validación.
- El guardado manual conserva `<h3>`; antes las eliminaba. Guardar contenido nuevo invalida la referencia al PDF. El nombre del PDF incluye versión de geometría para no servir archivos anteriores a esta corrección.
- `REPORT_TRACE_STRUCTURE=true` activa un log estructural separado y copias privadas del contenido RAW recibido, sin tokens de acceso ni cabeceras HTTP. Se desactiva por defecto. No recupera respuestas históricas inexistentes.

## Verificación del caso original

El PDF se renderizó desde cero con los datos exportados de la carta 4: 137 páginas. Se inspeccionaron imágenes de las páginas 9, 13, 15, 108, 109, 110, 137 e inicios de Luna (41), Ascendente (71) y Descendente (99). Header, bandas, contenido y footer están separados. El análisis de coordenadas de texto en las 137 páginas no encontró invasiones de las zonas reservadas.

Los doce estados guardados se convirtieron y volvieron a renderizar con igualdad exacta de sus bloques HTML: no se redujo ni se eliminó narrativa. Las posiciones canónicas no se recalcularon.

Las pruebas automatizadas cubren los doce estados, contaminación, orden DOM, siete elementos por lista, idempotencia, caché antigua, guardado manual, recuperación HTTP 422 y geometría real multipágina de Dompdf. La integración crea una carta y una generación aisladas, pasa por el cliente OpenAI con respuestas controladas, guarda 44 bloques, vuelve a generar Descendente y compara los DOM finales de web y PDF.

Se ejecutaron además generaciones reales de OpenAI en una base SQLite local aislada, copiando exactamente el snapshot canónico de la carta original. Las cuatro puertas terminaron y generaron los registros locales 1 a 4, con 11 bloques por puerta y 44 en total.

Después se regeneró dos veces `descendente.excess`, con trazas `descendant-excess-audit-1` y `descendant-excess-audit-2`. Se conservaron los RAW de sus cinco etapas. La verificación compara sus valores con los campos normalizados y los DOM finales de web y PDF: coinciden exactamente en ambas iteraciones, con siete elementos en cada lista. La base de datos contiene exclusivamente la segunda versión; el total sigue siendo 44 bloques. El snapshot canónico conserva igualdad exacta con el original.

El PDF final de esta generación limpia tiene 138 páginas. Se comprobó la geometría de todas y se inspeccionaron visualmente 9, 13, 15, 41, 71, 99, 108, 109, 110 y 138, sin solapamientos. Es una copia de prueba con contenido recién generado; el contenido original de la carta 4 no se sobrescribió.

Suite completa: 67 tests y 755 assertions, todos correctos, ejecutados con `php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit`. Se actualizaron también fixtures antiguos del prompt y las pruebas de rutas que necesitaban un usuario autenticado. Pint y `git diff --check` pasan.

Los artefactos de diagnóstico contienen datos del informe y permanecen en `app/storage/app/private/report-audit`. Se añadió exclusión Git para diagnósticos y RAW nuevos; esto no retira los archivos que ya habían sido indexados o confirmados durante la sesión.

## Límites de evidencia

El HTML adjuntado posteriormente solo contiene la página genérica HTTP 500 de Laravel. No incluye URL ni excepción: no permite identificar la causa de ese incidente específico. El error de sintaxis que quedó durante una edición interrumpida de `ReportState` fue corregido y cubierto por ejecución de tests.

La estructura web se verifica mediante su DOM final. No había navegador disponible en la herramienta de automatización para una inspección visual interactiva.
