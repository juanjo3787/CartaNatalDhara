# ASTROLOGIA SARANA VEDA — Informe I

Aplicacion privada Laravel 13 / PHP 8.3. El unico modelo activo de interpretacion
es **Informe I — Mapa interior**. No importa textos de Word, PDF ni informes antiguos.

## Modelo maestro

- Prompt aprobado, esquema, opuestos y validaciones:
  [InteriorMapMaster](app/Services/InteriorMapMaster.php).
- Modelo generico y campos variables:
  [PhaseOneReportService](app/Services/PhaseOneReportService.php).
- Generacion estructurada:
  [PhaseOneAiGenerationService](app/Services/PhaseOneAiGenerationService.php).
- Presentacion web/PDF y estilos editoriales originales:
  [template.blade.php](resources/views/reports/phase-one/template.blade.php).

El Word de referencia conservado en `../docs` contiene datos personales y no se
lee en ejecucion. Sus posiciones y su nombre no son valores predeterminados.
Tambien se entrega `../docs/plantilla_Mapa_Interior_Generica.docx`, sin identidad
ni posiciones personales, con la estructura y los campos variables del modelo.
El modelo definitivo es generico: nombre y nacimiento de la carta actual,
signo/casa/longitud de Sol, Luna, Ascendente y Descendente calculados, y opuestos
derivados de esos signos. La IA no recibe el nombre ni la fecha de nacimiento:
la aplicacion los incorpora a la portada.

## Estructura del Informe I

Introduccion breve, cuatro posiciones y cinco capitulos:

1. Sol: identidad, voluntad y eleccion.
2. Luna: necesidades emocionales, seguridad y cuidado.
3. Ascendente: orientacion y comienzo.
4. Descendente: reciprocidad, acuerdos y encuentro.
5. Integracion individual de las cuatro puertas.

Cada puerta contiene funcion, pregunta de entrada, signo, casa, armonia, defecto,
exceso, pregunta para distinguirlos, opuesto complementario, ejemplo hipotetico,
sintesis, pregunta esencial y practica observable. La integracion contiene de tres
a cinco temas con preguntas y una practica conjunta.

Se exige un total de **4000–5500 palabras**. Cada puerta tiene 780–1050 y la
integracion 800–1050. No hay capitulos interpretativos de regentes, aspectos,
otros planetas, desarrollo de doce casas ni ampliacion comercial. Se conservan
la rueda y la matriz visual de la portada; no se interpretan exhaustivamente.
El nuevo recorrido tiene su propia paginacion: no inserta la pagina nueve vacia
del dossier retirado.

La lectura es simbolica, no diagnostica ni predictiva. No se deduce biografia ni
genero a partir del nombre. Los estados son modos de expresion, no etiquetas
morales.

## Protocolo y datos

- Zodiaco tropical, posiciones geocentricas, casas Placidus.
- Swiss Ephemeris (`swetest`); datos normalizados a UTC.
- Coordenadas persistidas a seis decimales.
- Datos y calculos faltantes o inconsistentes detienen la generacion.
- Signos/casas/opuestos se derivan del snapshot actual, nunca del Word.
- La IA recibe solo el maestro y hechos actuales; la integracion recibe las
  cuatro puertas del mismo trabajo, no interpretaciones historicas.

El formulario y el servicio rechazan otros sistemas de casas para este nuevo
protocolo. El calculador astronomico conserva su implementacion y capacidades.
La version de modelo es 100; la version editorial PDF es 15.

## Generacion y actualizaciones

El proceso usa cinco llamadas estructuradas (cuatro puertas y una integracion),
mas preparacion, validacion, montaje y PDF. La cola `reports` debe tener un
worker activo. No genera en una peticion HTTP larga.

El 11% indica que la preparacion termino y se esta generando el Sol. Si un
capitulo no supera la validacion, la cola aplica sus reintentos con espera; la
barra conserva el porcentaje hasta que ese capitulo sea valido. Cada peticion
incluye objetivos de palabras por seccion (no por parrafo). El motivo del rechazo
se conserva como feedback para corregir la siguiente respuesta y se muestra en
el aviso de progreso. No se persiste ni acepta el capitulo invalido, no se amplian
los limites y no se hacen llamadas adicionales fuera de los reintentos de cola.
Al agotarlos, se informa del motivo y se permite reintentar la etapa pendiente.
Desplegar cambios del generador exige recrear tambien el worker, no solo la web.

Siempre se regeneran las cuatro puertas y su integracion juntas. Una peticion
antigua de generacion individual se convierte en regeneracion completa para
evitar mezclar textos de trabajos distintos.

Al cambiar datos relevantes de nacimiento se recalcula la carta y se marca el
informe como desactualizado. Se muestra el aviso de regeneracion y se bloquea su
descarga o la creacion de un PDF reutilizando textos anteriores. Las notas
privadas no fuerzan un nuevo calculo.

El PDF se descarga como `Informe I - Nombre de la carta natal.pdf`.
La edicion manual conserva el esquema completo y el presupuesto de palabras e
invalida el PDF. Guardar el PDF no vuelve a interpretar.

`reports:refresh-pdfs` solo sirve para textos completos del modelo nuevo. No
convierte el dossier antiguo: este requiere una generacion completa desde cero.
Los trabajos pendientes del modelo anterior no pueden procesarse ni reintentarse.

## Configuracion necesaria

Mantener `.env`, `APP_KEY`, cuentas de acceso y credenciales fuera del repositorio.
La configuracion existente apunta al MySQL del NAS: **no es una base local de
pruebas**. No ejecutar limpiezas, migraciones destructivas ni seeds contra ella
sin revision y autorizacion especifica.

La IA requiere `AI_ENABLED=true`, `AI_API_KEY`, `AI_MODEL` compatible con JSON
schema y `AI_BASE_URL`. El limite por capitulo es
`AI_CHAPTER_MAX_COMPLETION_TOKENS` (5000 por defecto). No se usan las antiguas
cuotas del Sol ni las 22 etapas por puerta. Precios, cambio de divisa y tasas
siguen en `config/ai.php`.

Para activar este codigo en otro entorno, revisar sus cambios, desplegar la
imagen actualizada y aplicar las migraciones, incluida la ampliacion de nombres
de seccion; limpiar configuracion/vistas y reiniciar el worker. La nueva
migracion **no borra personas ni informes**. La limpieza del NAS debe ser una
operacion separada confirmada, con workers detenidos y conservando `users`,
credenciales y migraciones.

La imagen Docker crea los directorios de ejecucion de Laravel antes de ejecutar
los scripts de Composer y asignar permisos. `storage` puede no existir en un clon
limpio porque sus archivos generados estan excluidos del contexto de build; no
deben copiarse informes ni caches antiguos para resolverlo. Los volumenes
persistentes del NAS se preparan por separado en `deploy.sh`.

## Pruebas

Desde `app`, con dependencias existentes:

```powershell
php vendor\bin\phpunit
php vendor\bin\pint --test
node --test tests\Feature\*.test.mjs tests\Feature\*.test.cjs
```

PHPUnit usa SQLite en memoria, HTTP simulado y almacenamiento aislado. No llama
al proveedor ni escribe personas en MySQL. Si el PHP de Windows tiene SQLite
instalado pero deshabilitado, activarlo solo para el proceso:

```powershell
$phpDir = Split-Path (Get-Command php).Source
php -d "extension=$phpDir\ext\php_pdo_sqlite.dll" vendor\bin\phpunit
```

Las pruebas del generador retirado se sustituyeron por pruebas del maestro,
hechos/opuestos, limites, ausencia de defaults, cola completa, web/PDF/editor,
rechazo de trabajos antiguos, stale y nombres de descarga. Se mantienen las
pruebas de registro, calculo, autenticacion, costes/metricas y componentes.

## Limites del reinicio

Eliminar archivos del directorio de trabajo no los elimina del historial Git,
GitHub, otros clones, descargas o copias de seguridad. El codigo local no acredita
la limpieza de MySQL ni del almacenamiento persistente de produccion. Consultar
el resumen de reinicio en `../docs` antes de declarar un reinicio total.
