# Reinicio local — ASTROLOGIA SARANA VEDA

## Estado y alcance

Se ha ejecutado la activacion **en el codigo local** del nuevo Informe I y el
borrado local confirmado. No se ha desplegado este cambio ni se ha limpiado
produccion. **El reinicio total del sistema no esta completado.**

No se ha generado ningun informe personal nuevo. Las verificaciones emplearon
datos sinteticos, SQLite aislado y respuestas de IA simuladas.

## Recursos realmente eliminados del directorio de trabajo

- Dossier Word/PDF anterior, plantillas antiguas y documentos de continuidad
  general, Sol/Luna/ejes que estaban en `docs`. Tambien se retiro el antiguo
  documento tecnico; el protocolo conservado queda expresado en codigo.
- Cinco documentos antiguos de analisis y plan.
- Un archivo temporal de bloqueo de Word y el PDF historico de `output`.
- Tres PDF locales de cartas anteriores.
- Directorio de auditoria: 48 archivos, incluidas sus dos bases SQLite,
  serializaciones, capturas, documentos y borradores.
- Once trazas de respuesta de IA.
- Cookies locales, dos sesiones, registros y caches generados anteriores.
- Seis scripts de auditoria/comprobacion del sistema retirado y su permiso
  automatico de ejecucion en la configuracion del editor.
- Diecisiete clases del generador extenso: catalogos de instrucciones y textos
  fijos, prompts, pipeline de 22 etapas, regentes, esquemas y renderizadores
  historicos. No hay una opcion de fallback que los vuelva a usar.
- Pruebas dependientes de ese esquema: sustituidas por pruebas del nuevo modelo,
  manteniendo las verificaciones de calculo/registro, acceso y componentes.

Las eliminaciones de archivos versionados estan en el diff local. Todavia se
pueden recuperar del historial Git: este trabajo **no reescribio el historial**.
No se crearon copias de seguridad de los datos borrados.

## Elementos conservados y activos

- Nombre e identidad editorial de Sarana Veda: tipografias, tamanos, colores,
  beige, jerarquia de titulos, encabezados/pies y A4 en la vista original.
- Motor Swiss Ephemeris, normalizacion UTC y datos de configuracion necesarios.
- Cuentas de acceso, claves, credenciales y archivos de entorno, sin borrarlos ni
  publicarlos.
- Word de referencia nuevo, conservado intacto como excepcion autorizada.
  SHA256: `F9737D340B299055E15570A47438A0A4D8CD0F9831D402C09E77B7AA2CEFD35B`.

El Word original sigue conteniendo datos personales. **No se carga en ejecucion,
no es una base de clientes y sus posiciones no alimentan otros informes.**
El modelo definitivo activo es generico y no contiene esa identidad.
Se ha creado ademas la
[plantilla Word generica](plantilla_Mapa_Interior_Generica.docx), con campos
variables y sin datos personales ni posiciones de ejemplo. Conserva los estilos
del Word de referencia, pero no sus propiedades personales ni su contenido.
El paquete DOCX y sus XML se han validado.

### Unico modelo de generacion

- [InteriorMapMaster](../app/app/Services/InteriorMapMaster.php):
  prompt maestro aprobado, campos de cada capitulo, opuestos y validacion.
- [PhaseOneReportService](../app/app/Services/PhaseOneReportService.php):
  modelo generico con nombre/nacimiento actuales y posiciones actuales.
- [PhaseOneAiGenerationService](../app/app/Services/PhaseOneAiGenerationService.php):
  cuatro capitulos nuevos mas una integracion desde las puertas del mismo
  trabajo. No consulta interpretaciones historicas para redactar.
- [Vista editorial](../app/resources/views/reports/phase-one/template.blade.php):
  portada, introduccion unica, cuatro puertas e integracion.
- [README operativo](../app/README.md): campos, estructura, requisitos y uso.

Modelo 100, PDF 15. Se validan datos y eje, signos/casas, opuestos, estructura,
secciones no vacias, limites de palabras y parrafos duplicados. Las afirmaciones
explicitas de posicion reconocibles se contrastan con los hechos calculados;
esto no sustituye una revision humana de toda la coherencia semantica.
El total aprobado es 4000–5500 palabras. No se reutilizan la paginacion ni la
pagina nueve artificial del dossier retirado.

Los trabajos antiguos quedan rechazados por version. Cada nuevo trabajo queda
vinculado a una huella de sus datos actuales: no puede reintentarse si la carta
cambio. La edicion de datos conserva el aviso de regeneracion. Un PDF
desactualizado no se descarga ni se valida reutilizando los textos anteriores.
El nombre de descarga es `Informe I - Nombre de la carta natal.pdf`.

## Verificacion realizada

- Suite PHP completa con SQLite habilitado solo para el proceso: 55 pruebas,
  313 aserciones, sin fallos, incluida la edicion manual con hechos erroneos y el
  historico de prompts/costes del nuevo capitulo de integracion.
- Suite JavaScript existente: 14 pruebas, sin fallos.
- Pruebas de cola completa: cinco llamadas simuladas, persistencia y PDF real
  Dompdf; no se llama al proveedor ni se utiliza una persona real.
- Pint y diagnosticos del editor.
- Arranque aislado: `/up` y `/charts` respondieron HTTP 200.
- Navegador: listado vacio, formulario de nacimiento sin datos de ejemplo y
  Placidus como unica opcion; vuelta al listado sin crear personas.
- El SQLite de validacion se creo fuera del repositorio y se elimina tras la
  verificacion. No es la base de produccion ni cambia la conexion configurada.
- El hash del Word se verifico sin alteraciones despues del borrado.

Docker no estaba disponible: su daemon no respondio en el pipe de Docker
Desktop. La migracion de columnas se ha probado sobre SQLite; no se ha ejecutado
todavia contra el MySQL/MariaDB del NAS. Tampoco se ha hecho una llamada de IA
real ni un calculo astronomico nuevo con `swetest` durante el reinicio.

### Evidencia y limites de la validacion

| Capa | Resultado | Evidencia |
|---|---|---|
| Infraestructura | SQLite aislado; Docker no disponible | `docker info` fallo al conectar con `dockerDesktopLinuxEngine`; Node v24.19.0 disponible. |
| Arranque | Correcto | Servidor PHP local aislado, salud y listado HTTP 200. |
| PHP | Correcto, exit code 0 | 55 pruebas / 313 aserciones, con `pdo_sqlite` habilitado solo en ese proceso. |
| JavaScript | Correcto, exit code 0 | 14 pruebas existentes. |
| Formato | Correcto, exit code 0 | Pint y `git diff --check`; sin errores en diagnosticos del editor. |
| Navegador integrado / Playwright | Flujo parcial correcto | Listado vacio → formulario vacio y Placidus → listado. Sin crear una carta ni llamar a servicios externos. |
| Generacion y PDF | Correcto en integracion | Cinco respuestas de IA simuladas, persistencia real en SQLite, PDF Dompdf real y pantallas de historial/editor. |
| Produccion / IA real / Swiss Ephemeris real | No verificado en este reinicio | Requiere el entorno desplegado y autorizacion para su limpieza y uso. |

Veredicto: **validacion local correcta; pendiente de verificacion y autorizacion
de produccion**, no una certificacion de reinicio total ni de un informe real.

## Recursos que siguen pendientes o no se pueden eliminar desde aqui

| Recurso | Estado / acceso necesario |
|---|---|
| MySQL/MariaDB del NAS | No borrado. Acceder al gestor de base de datos o SSH del NAS; confirmar el alcance antes de limpiar personas, nacimientos, lugares, cartas, interpretaciones, plantillas, historiales, trabajos, metricas, sesiones y colas. Conservar usuarios, credenciales y migraciones. |
| PDF y trazas del almacenamiento persistente del NAS | No borrados. Revisar el volumen de almacenamiento de la aplicacion en el NAS, con workers detenidos. |
| Codigo e imagen activos en produccion | No actualizados. Desplegar estos cambios, migrar y reiniciar workers despues de revisar y confirmar la operacion de produccion. |
| Git local y GitHub | Los archivos retirados permanecen en commits anteriores. Limpiar el historial exige una operacion separada coordinada con los otros clones. |
| Snapshots y copias del NAS, imagenes Docker antiguas | No inspeccionados ni borrados. Revisar Snapshot Replication/Hyper Backup y Container Manager. |
| Descargas Word/PDF, papelera y otros equipos/clones | Fuera del alcance local confirmado. Revisar y borrar manualmente donde se guardaron. |
| Proveedor de IA y conversaciones anteriores | No accesibles para borrado desde este trabajo. Gestionar retencion y datos en las plataformas correspondientes. |

No confundir la ausencia de dependencias historicas en el nuevo generador con la
eliminacion fisica de todos esos recursos. No usar el sistema de produccion como
una base vacia hasta completar y verificar su limpieza autorizada.
