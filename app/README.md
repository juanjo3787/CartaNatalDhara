# ASTROLOGIA SARANA VEDA — Informe I

Aplicacion privada Laravel 13 / PHP 8.3. El modelo activo del Informe I aplica
el prompt universal y el **Manual Maestro Universal de Interpretacion Natal
Copilot, edicion revisada**. No reutiliza interpretaciones de Word, PDF ni
informes historicos.

## Modelo y referencia

- Reglas del manual, esquema, hechos y validaciones:
  [InteriorMapMaster](app/Services/InteriorMapMaster.php).
- Generacion secuencial y auditoria:
  [PhaseOneAiGenerationService](app/Services/PhaseOneAiGenerationService.php).
- Persistencia, estructura y edicion:
  [PhaseOneReportService](app/Services/PhaseOneReportService.php) y
  [PhaseOneManualSaveService](app/Services/PhaseOneManualSaveService.php).
- Vista web/PDF:
  [template.blade.php](resources/views/reports/phase-one/template.blade.php).

Las reglas se incorporaron desde el manual facilitado, identificado por SHA256
`B1AED789726FC2EE08678DC60C171B70F51DF52D9FBFF6E26D31E05CB847CFED`.
No se abre un DOCX en cada llamada: la IA recibe las reglas incorporadas y esta
procedencia se declara en los hechos. Modificar un Word externo no actualiza
automaticamente el generador.

Los documentos anteriores conservados en `../docs`, incluidas sus plantillas,
son referencias historicas, **no el contrato del modelo actual**. No se leen
en ejecucion ni se importan sus nombres o posiciones. La IA no recibe el nombre
ni la fecha de nacimiento; la aplicacion los incorpora a la portada.

## Cinco bloques

1. **El Sol:** identidad y voluntad consciente.
2. **La Luna:** necesidades y respuestas emocionales (incluida la fase lunar calculada).
3. **El Ascendente:** orientacion expresiva y actuacion.
4. **El Descendente:** vinculo y reciprocidad.
5. **Los opuestos complementarios:** polaridad solar, polaridad lunar y eje angular,
   con complementariedad bidireccional.

Los titulos de bloque no llevan la palabra "natal". Cada bloque tiene conceptos
principales; cada concepto contiene entre cinco y ocho caracteristicas cuando
los datos lo sostienen (nunca mas de ocho, validado) y, debajo, normalmente dos
o tres parrafos sustanciales en segunda persona y lenguaje llano. La aplicacion
anade al renderizar los rotulos CONCEPTO: y Caracteristica N.; la IA solo
escribe los nombres. El prompt maestro (version 105) incluye la jerarquia de
instrucciones, la intensidad en lenguaje natural (importancia astrologica,
intensidad simbolica y manifestacion personal), el procedimiento de 10 pasos
para casas, cuspides y regentes, las dimensiones constructiva, desequilibrada,
contradiccion, integracion y maduracion, y la diferenciacion entre bloques.
El validador rechaza nombres internos de datos (positions, ulers,
occupants, snapshot, current_report, maximum_orb, 	ime_precision o
cualquier identificador con guion bajo). El JSON es:

```json
{
  "concepts": [{
    "title": "Concepto pertinente",
    "characteristics": [{
      "title": "Caracteristica concreta",
      "paragraphs": ["Desarrollo individualizado sustentado en la carta."]
    }]
  }]
}
```

No hay introduccion interpretativa, resumen, conclusion ni sintesis final.
Se retiraron las cuotas de cinco conductas y las secciones obligatorias de
armonia/defecto/exceso, DON/NECESIDAD/SOMBRA/RECURSO y practica de cierre.
La portada y el indice no incorporan interpretaciones adicionales.

El objetivo editorial es **siete paginas Word orientativas por bloque**, no
una paginacion garantizada. Se propone una referencia flexible de 3200 palabras;
no hay minimo obligatorio por bloque, caracteristica o informe que fuerce
relleno. El limite tecnico de seguridad es 6000 palabras por bloque. La
profundidad debe ajustarse a los datos y no alcanzarse repitiendo ideas.
No se exporta Word ni se verifica su paginacion: el producto de la aplicacion
es web/PDF A4. El renderizado PDF registra las paginas reales del indice y
rechaza paginas sin contenido, sin insertar paginas vacias para llegar a siete.
Se conservan la rueda, la matriz, los colores y los encabezados/pies editoriales.

## Hechos tecnicos y universalidad

El protocolo calculado existente sigue siendo tropical, geocentrico y Placidus.
No se reinterpreta como otro sistema de casas. Los datos provienen del snapshot
de la carta actual, calculado con Swiss Ephemeris y normalizado a UTC.

- Se verifican las doce cuspides, su orden, los diez planetas y el eje
  Ascendente-Descendente. Los datos cruciales faltantes o inconsistentes detienen
  la generacion; no hay posiciones por defecto.
- Casas, ocupantes, signo de cuspide, regentes y proximidad a cuspides se
  derivan de longitudes, sin adelantar planetas a la casa siguiente.
- Regencias tradicionales prioritarias y modernas complementarias. Se reconoce
  la autodisposicion. Domicilio, detrimento, exaltacion y caida clasicos se
  distinguen de atribuciones modernas y de condiciones no calculadas.
- Aspectos mayores: conjuncion, sextil, cuadratura, trigono y oposicion; orbe
  maximo de 6 grados, coherente con la matriz visual. El calculo se comparte
  mediante [AspectMatrixBuilder](app/Services/AspectMatrixBuilder.php) y conserva
  el orbe exacto, ademas de su representacion en grados/minutos/segundos.
- La oposicion geometrica de los angulos no se presenta como oposicion entre
  planetas. Compartir casa no implica conjuncion.
- Fuente y precision de la hora viajan al prompt. Casas y angulos se declaran
  condicionales cuando la precision es aproximada o desconocida.
- Aplicacion/separacion, velocidades, retrogradacion, combustion y dignidades
  menores no se inventan: no estan calculadas en este modelo.

La lectura es simbolica, no cientifica, diagnostica ni predictiva. Se prohiben
biografias inventadas, jerarquias porcentuales, excepciones psicologicas por signo
y valoraciones automaticas de aspectos como buenos o malos.

## Generacion, auditoria y edicion

La cola `reports` ejecuta cinco llamadas estructuradas, una por bloque, y una
sexta llamada de auditoria conjunta, seguidas de validacion, montaje y PDF.
Cada bloque recibe los anteriores del **mismo trabajo**, nunca informes
historicos. Si falta ese contexto, se detiene la generacion.

La auditoria recibe los cinco bloques y devuelve reemplazos completos solo
donde hacen falta correcciones. Se validan todos los reemplazos antes de
guardarlos en una transaccion. El trabajo no se completa sin esa auditoria.
Los controles locales verifican estructura, campos no vacios, titulos excluidos,
limite tecnico, parrafos literalmente duplicados y afirmaciones reconocibles
de signo, casa y aspecto. La auditoria de IA aborda duplicaciones conceptuales,
soporte, matices exclusivos y relleno; **no sustituye una revision humana** de
toda la coherencia semantica ni garantiza detectar cada afirmacion erronea.
El detector textual separa titulos y parrafos; una negacion no se propaga a
afirmaciones posteriores. Reconoce prefijos no asertivos inmediatos, como
«no exige Luna en Acuario» o «como si Jupiter en Piscis, casa VII».
Los incisos sobre un planeta no atraviesan parentesis ni otros nombres de
planetas para atribuirle una casa. Son reglas conservadoras, no un analizador
completo de todas las formulaciones del lenguaje natural.

Los rechazos guardan motivo y borrador en el trabajo actual. El siguiente
intento recibe ambos para corregirlo; al aceptar el bloque se retiran. No se
publican borradores rechazados ni se silencian errores de salida truncada.
La preparacion termina en el 10% y el Sol se acepta en el 20%; el progreso
incluye la auditoria como etapa independiente.

Siempre se regeneran los cinco bloques juntos. Las rutas antiguas que solicitan
una puerta individual se convierten en regeneracion completa. Cambiar datos de
nacimiento marca el informe como desactualizado y bloquea su descarga.

El editor conserva conceptos H3, caracteristicas H4 y parrafos. Valida tambien
el bloque V, contradicciones y duplicaciones entre bloques antes de guardar.
Conserva enfasis en linea, elimina atributos HTML e invalida el PDF. Guardar o
recrear el PDF no vuelve a interpretar.

**Version de modelo: 105. Version editorial PDF: 18.** Los textos y trabajos de
versiones anteriores no se mezclan con este modelo: requieren regeneracion.
`reports:refresh-pdfs` solo recrea PDF de contenidos completos del modelo actual.
El nombre de descarga sigue siendo `Informe I - Nombre de la carta natal.pdf`.

## Configuracion y despliegue

Mantener `.env`, `APP_KEY`, cuentas y credenciales fuera del repositorio.
La configuracion existente apunta al MySQL del NAS: **no es una base de
pruebas**. Este cambio no borra personas, cartas ni datos de produccion y no
necesita una migracion de esquema.

La IA necesita `AI_ENABLED=true`, clave, modelo compatible con JSON schema,
URL y tarifas en [config/ai.php](config/ai.php). Los valores predeterminados de
salida son `AI_CHAPTER_MAX_COMPLETION_TOKENS=10000` y
`AI_AUDIT_MAX_COMPLETION_TOKENS=16000`; revisar valores antiguos mas bajos y
la capacidad real del modelo antes de generar. Los limites son tecnicos, no
objetivos de texto. `AI_AUDIT_TIMEOUT=300` reserva tiempo para la revision
conjunta dentro del timeout de 360 segundos del worker. La auditoria y el mayor desarrollo pueden aumentar coste
y duracion respecto al modelo anterior. Una salida truncada produce un error,
no un informe incompleto presentado como valido.

Para activar en otro entorno, desplegar la imagen actualizada, limpiar
configuracion/vistas y recrear **web y worker**. No basta con cambiar la web:
un worker persistente mantiene el codigo cargado. Los informes anteriores deben
generarse de nuevo; no basta con refrescar su PDF.

## Pruebas

Desde `app`, con las dependencias existentes:

```powershell
php vendor\bin\phpunit
php vendor\bin\pint --dirty --format agent
node --test tests\Feature\*.test.mjs tests\Feature\*.test.cjs
```

PHPUnit usa SQLite en memoria, HTTP simulado y almacenamiento aislado. No llama
al proveedor ni escribe personas en MySQL. Si SQLite esta instalado pero
deshabilitado en PHP de Windows, activarlo solo para el proceso:

```powershell
$phpDir = Split-Path (Get-Command php).Source
php -d "extension=$phpDir\ext\php_pdo_sqlite.dll" vendor\bin\phpunit
```

La cobertura del Informe I incluye hechos/cuspides/regencias/dignidades,
orbes exactos, autodisposicion, incertidumbre de hora, esquema abierto, ausencia
de introducciones/cierres, contexto secuencial, auditoria transaccional,
reintentos, edicion y PDF real sin paginas vacias. Tambien se conservan las
pruebas de registro/calculo, autenticacion, costes y componentes.
