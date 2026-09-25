# Generación asíncrona de informes

## Diagnóstico previo (25-09-2026)

Backend instalado: Laravel 13.32.0, PHP 8.3, MySQL, Dompdf 3.1. OpenAI: Chat Completions `/chat/completions`, modelo configurado `gpt-5-mini`, respuestas JSON estructuradas. No se usa Responses API; `background: true` no se ha incorporado ni sustituye la cola de la aplicación.

La interfaz anterior encadenaba 22 peticiones por puerta (88 por informe) a `POST /charts/{chart}/report/ai/{door}/stages/{stage}`. Cada petición esperaba sincrónicamente a OpenAI. Los endpoints `/report/ai/{door}` y `/report/ai` esperaban una puerta o todas. Después se solicitaba el PDF en otra petición, también síncrona. Descargar un PDF inexistente lo renderizaba dentro del GET.

El Dockerfile arrancaba `php artisan serve`, sin varios workers configurados, y Compose tenía únicamente el servicio web. Esta configuración explica que una petición larga pueda bloquear a todos los usuarios (caso 2). No se han inspeccionado los procesos efectivos del NAS ni se ha medido una generación en producción: el caso real del servidor desplegado permanece por confirmar con `/up` durante una generación.

Sesiones: `database`, `session.block=false`, sin rutas `->block()`. El middleware StartSession de la versión instalada solo adquiere el lock cuando se habilita una de esas opciones. Mantener la sesión hasta terminar la request no equivale aquí a mantener un lock exclusivo. No se encontró lock global de generación. La base configurada es MySQL, no SQLite. Las transacciones de persistencia empiezan después de responder OpenAI; no incluyen la espera de red.

Ya existían la conexión Laravel `database` y las tablas `jobs`/`failed_jobs`. No existía un worker de informes en Compose. No se necesita instalar Redis. No hay telemetría suficiente para afirmar una duración típica: el timeout configurado de una llamada era 95 s y la validación podía repetirla; esto no es una medición de duración real.

## Implementación

- `POST /charts/{chart}/report/jobs`: valida carta y captura de la rueda, crea `report_jobs`, inserta en `jobs` y responde 202 con `job_id`, estado y URLs. Los endpoints anteriores por puerta, informe, validar y regenerar usan la misma cola. La antigua ruta HTTP de etapas responde 410: no ejecuta OpenAI.
- `GET /reports/jobs/{job}`: progreso y error; acceso exclusivo a la cuenta que lo creó. `GET /reports/jobs`: últimos trabajos por carta de esa cuenta, incluidos activos y fallidos aunque sean antiguos. La consulta de polling no carga los borradores grandes.
- `POST /reports/jobs/{job}/retry`: continúa desde el cursor persistido. Rechaza reintentar un trabajo antiguo si existe uno posterior para la carta.
- `ProcessReportStep`: una etapa por mensaje de cola. Estados: queued, preparing, generating_sun/moon/ascendant/descendant, generating_integration, validating, building_document, generating_pdf, completed/failed. Integración conserva el ensamblado editorial existente; no añade ni elimina llamadas del contenido anterior.
- `PhaseOneAiGenerationService`: guarda cada resultado validado en `report_jobs.drafts`, sin expiración ni dependencia de una sesión del navegador. Cada puerta se publica al completar su validación. Las etapas anteriores sobreviven a errores y reinicios. Publicación final de puerta y checkpoint se guardan en una misma transacción.
- La reserva de un trabajo usa una transacción breve y un índice único nullable por carta. Un doble clic devuelve el trabajo activo. Incluso una regeneración explícita espera a que termine el trabajo activo, evitando escritores concurrentes sobre el mismo informe.
- Los mensajes incluyen el cursor esperado y un lock por trabajo. Una entrega antigua no vuelve a generar etapas ya confirmadas. El siguiente mensaje y el avance del cursor se guardan juntos en MySQL.
- `ReportPdfService` conserva el renderizador y la geometría existentes. El PDF se escribe en el worker, en una ruta propia del trabajo; solo después se publica su referencia. Descargar un PDF ya disponible sirve el archivo. Si falta, se encola y se vuelve a la carta.
- La interfaz usa polling cada 4 s, sin pantalla de bloqueo. El estado procede del servidor al volver a entrar o usar otro navegador. Al completar se muestran enlaces al informe y al PDF. Si falla, ofrece reintentar la etapa pendiente.

No se han cambiado prompts, modelo, límites de tokens, profundidad narrativa, datos astrológicos ni geometría del PDF.

## Workers, límites y recuperación

La imagen pasa de PHP CLI/servidor de desarrollo a PHP Apache. Apache usa prefork con 2 procesos iniciales y máximo 4. Un contenedor `report-worker` independiente procesa la conexión `reports`, cuyo driver está fijado a `database`: no se vuelve síncrono aunque `QUEUE_CONNECTION=sync`.

Punto de partida conservador: **un worker de informes, una etapa en ejecución y una llamada OpenAI a la vez**. Puede haber varias cartas esperando o avanzando por turnos. No escalar el servicio sin revisar CPU, memoria y límites RPM/TPM reales de la cuenta; no se dispone de esas medidas del NAS. El worker tiene límite de contenedor 768 MiB/1 CPU y se recicla al superar 512 MiB entre trabajos o una hora. Son valores iniciales, pendientes de medición con informes reales en ese servidor.

Cada etapa admite cuatro intentos con esperas de 60, 180 y 540 s. Los 429, fallos de conexión y timeouts no mantienen ocupada una petición web. Se conserva el timeout individual de OpenAI (95 s por defecto) y los intentos existentes de validación. El worker tiene timeout 360 s, lock con expiración 420 s y reserva de cola 480 s, en ese orden. No aumentar el timeout individual o el número de validaciones sin revisar estas relaciones. `pcntl` se instala en Linux para hacer efectivo el timeout del worker.

Si el worker muere, la reserva vuelve a estar disponible después de 480 s y otro proceso continúa desde el checkpoint. Un timeout detectado o agotamiento de intentos marca failed; el usuario puede reintentar. No existe una transacción abierta durante OpenAI ni durante el renderizado del PDF. El cierre de sesión o de la pestaña no afecta al worker.

## Verificación

Suite final: **70 pruebas, 887 assertions**. Cobertura: HTTP 202 sin OpenAI, deduplicación, autorización, cola real en pruebas, 88 etapas con respuestas simuladas, persistencia de 44 bloques de IA y contenido editorial compartido, PDF generado por el worker sin sesión, respuesta 429, reintento exitoso de la etapa pendiente conservando la anterior, y descartado de mensajes antiguos. Las pruebas mantienen una transacción de aislamiento; verifican que el código no abre otra durante OpenAI. Mientras una carta tiene una generación activa se rechaza guardar su edición, modificar su registro o eliminarla; la navegación y las demás cartas siguen disponibles.

Prueba adicional local: servidor PHP y worker en **procesos distintos**, SQLite de pruebas aislado, OpenAI simulado y captura de rueda de prueba. El cliente que inició el trabajo terminó; el worker continuó. Durante una llamada simulada de 30 s en `sol.function`:

| Operación | HTTP | Tiempo |
|---|---:|---:|
| Crear trabajo | 202 | 0,591 s |
| `/charts`, misma sesión | 200 | 0,394 s |
| `/up`, cliente independiente sin cookies | 200 | 0,318 s |
| Consultar progreso, misma cuenta | 200 | 0,277 s |

La prueba completa también generó el PDF después de terminar el cliente inicial. Son medidas locales, no del NAS. Docker Desktop no estaba activo: se valida la sintaxis de Compose, pero queda pendiente construir y arrancar la imagen Apache y comprobar el servidor real. No se ha afirmado una prueba física desde otro dispositivo ni inspección visual del navegador.

## Despliegue y comprobación en el NAS

1. Desplegar el código y la imagen actualizados. `deploy.sh` usa ahora el Compose del repositorio por defecto (puede sobreescribirse con `COMPOSE_FILE`), conserva el archivo `.env` externo y arranca el worker después de migrar y limpiar/cachear configuración.
2. Ejecutar la migración de `report_jobs`: `php artisan migrate --force`. No se ha ejecutado en la base de producción desde esta sesión.
3. Arrancar ambos servicios: `docker compose up -d app report-worker`. Al actualizar código, reiniciar también el worker; no basta con reiniciar Apache. Las rutas de storage y bootstrap/cache deben ser compartidas y escribibles.
4. Confirmar `docker compose ps` y `docker compose logs --tail=100 report-worker`. La cola usada es `reports`, no `default`. Alternativa fuera de Compose: `php artisan queue:work reports --queue=reports --sleep=2 --timeout=360 --tries=4 --memory=512 --max-time=3600`, mantenido por Supervisor/systemd.
5. Abrir una carta, esperar a que se dibuje la rueda y pulsar Generar. Comprobar 202 en pocos segundos; cerrar la pestaña. Entrar otra vez con la misma cuenta y verificar que el progreso avanza.
6. Durante OpenAI y durante generating_pdf, consultar `/up` desde la misma sesión, incógnito y otro dispositivo. Probar también otra cuenta; debe navegar sin acceder al job ajeno. Medir latencia, memoria y CPU antes de aumentar concurrencia.
7. Descargar el PDF completado y revisar el informe real. Si el estado permanece queued, comprobar que el worker está vivo y conectado a la misma base de datos; aumentar timeouts HTTP no resuelve ese problema.

Pendiente operativo: despliegue real, comprobación multidispositivo, recursos y límites efectivos de OpenAI. No se han regenerado datos de producción para estas pruebas.
