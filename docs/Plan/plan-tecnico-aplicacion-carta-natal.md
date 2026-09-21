# Plan técnico - Aplicación de Carta Natal

## 1. Objetivo del proyecto

Crear una aplicación web privada en PHP para alojarla en un Synology y generar cartas natales personalizadas.

La primera versión debe permitir:

- Registrar personas.
- Introducir sus datos de nacimiento.
- Resolver automáticamente la ubicación y la zona horaria histórica.
- Calcular la carta natal con Swiss Ephemeris.
- Obtener Sol, Luna, Ascendente y Descendente.
- Calcular signos, grados, casas y planetas regentes.
- Generar la interpretación de Carta Natal Fase 1 mediante plantillas.
- Guardar el historial de personas, cálculos e interpretaciones.
- Mostrar la carta en pantalla.
- Generar un PDF descargable.

La inteligencia artificial será opcional y posterior. Las plantillas y las reglas deben ser la fuente principal y controlable del contenido.

## 2. Protocolo astrológico fijado

Estas son las reglas que debe respetar el motor de cálculo:

| Configuración | Decisión |
|---|---|
| Sistema zodiacal | Tropical |
| Referencia | Geocéntrica |
| Sistema de casas | Placidus |
| Regencias | Tradicional y moderna |
| Peso de regencias | Ambos regentes con el mismo peso interpretativo |
| Nodo lunar | Nodo Verdadero |
| Lilith | Luna Negra Media |
| Orbes | Tabla configurable con valores iniciales estándar |
| Revolución Solar | Lugar de residencia actual configurado |

La Revolución Solar debe quedar prevista en el diseño, pero no bloquea el MVP de Carta Natal Fase 1.

## 3. Datos de entrada por pantalla

### 3.1. Datos obligatorios

- Nombre o alias de la persona.
- Fecha de nacimiento.
- Hora local de nacimiento.
- Ciudad de nacimiento.
- País de nacimiento.

### 3.2. Datos recomendados

- Nombre completo.
- Fuente de la hora: documento oficial, familia, estimación u otra.
- Precisión de la hora: exacta, aproximada o desconocida.
- Notas privadas.
- Etiquetas.

### 3.3. Datos calculados automáticamente

A partir de la ciudad y la fecha, la aplicación debe resolver:

- Latitud.
- Longitud.
- Zona horaria histórica.
- Aplicación del horario de verano cuando corresponda.
- Fecha y hora convertidas a UTC.

La aplicación debe mostrar un resumen antes de calcular:

- Fecha y hora introducidas.
- Zona horaria detectada.
- Hora UTC resultante.
- Ciudad, país y coordenadas.
- Zodiaco tropical.
- Casas Placidus.

El usuario debe poder confirmar estos datos. No se deben pedir normalmente al usuario las coordenadas ni la hora UTC.

### 3.4. Datos de residencia

Para el perfil de la persona se guardará también la residencia actual:

- Ciudad.
- País.
- Latitud.
- Longitud.
- Zona horaria.

Estos datos se utilizarán en el futuro para calcular la Revolución Solar.

## 4. Resultado astrológico mínimo

Cada carta debe guardar un snapshot inmutable del cálculo realizado para que una interpretación histórica no cambie si se modifica la configuración posteriormente.

### 4.1. Elementos de la Fase 1

- Sol.
- Luna.
- Ascendente.
- Descendente.

### 4.2. Información de cada elemento

- Signo.
- Grados, minutos y segundos.
- Longitud eclíptica decimal.
- Casa.
- Si se encuentra en primeros, medios o últimos grados.
- Cercanía a cúspide o cambio de signo, cuando aplique.
- Regente tradicional.
- Regente moderno, si existe.
- Signo del regente tradicional y moderno.
- Casa de cada regente.
- Movimiento directo o retrógrado, si aplica.

### 4.3. Elementos preparados para fases posteriores

- Planetas personales y sociales.
- Nodos lunares.
- Lilith Media.
- Aspectos.
- Orbes.
- Dignidades.
- Revolución Solar.

## 5. Arquitectura recomendada

### 5.1. Aplicación

- PHP 8.2 o la versión compatible con el Synology.
- Laravel como framework principal.
- Blade para las vistas.
- MariaDB para persistencia.
- Composer para dependencias.
- PHPUnit o Pest para pruebas.
- Git para control de versiones.

La versión exacta de PHP debe comprobarse en el modelo y DSM del Synology antes de fijar Laravel.

### 5.2. Contenedores en desarrollo y Synology

Se recomienda Docker Compose en desarrollo y Container Manager en Synology:

- Contenedor de aplicación PHP/Laravel.
- Contenedor MariaDB.
- Volumen persistente para la base de datos.
- Volumen para efemérides.
- Volumen para plantillas versionadas.
- Volumen para PDFs generados.

Según la herramienta elegida para PDF, puede ser necesario un contenedor adicional para Chromium o un servicio de renderizado HTML.

### 5.3. Módulos de la aplicación

1. Autenticación y sesión.
2. Personas.
3. Lugares y zonas horarias.
4. Cartas natales.
5. Cálculo astronómico.
6. Configuración astrológica.
7. Catálogo de contenidos.
8. Plantillas e interpretaciones.
9. Generación PDF.
10. Copias de seguridad y mantenimiento.
11. Revolución Solar, como módulo posterior.

### 5.4. Servicios principales

- `BirthDataNormalizer`: valida y normaliza los datos de nacimiento.
- `TimezoneResolver`: resuelve la zona horaria histórica.
- `AstrologyCalculator`: encapsula Swiss Ephemeris.
- `ChartSnapshotBuilder`: construye el resultado inmutable de la carta.
- `RegencyResolver`: obtiene regentes tradicional y moderno.
- `InterpretationRenderer`: combina datos y plantillas.
- `PdfExporter`: genera el documento final.

El cálculo astronómico debe estar aislado mediante una interfaz para poder cambiar la extensión PHP, una librería o un binario sin reescribir el resto de la aplicación.

## 6. Modelo de datos inicial

### `people`

- Identificador.
- Nombre o alias.
- Nombre completo opcional.
- Notas privadas.
- Fecha de alta y modificación.

### `birth_data`

- Persona.
- Fecha local de nacimiento.
- Hora local de nacimiento.
- Lugar seleccionado.
- Fuente de la hora.
- Nivel de precisión.
- Zona horaria aplicada.
- Offset aplicado.
- Fecha y hora UTC calculadas.
- Latitud y longitud utilizadas.

### `places`

- Ciudad.
- País.
- Latitud.
- Longitud.
- Identificador de zona horaria.
- Fuente de los datos.

### `charts`

- Persona.
- Datos de nacimiento utilizados.
- Fecha de cálculo.
- Configuración astrológica versionada.
- Versión del motor.
- Snapshot JSON del cálculo.
- Estado del cálculo.

### `interpretations`

- Carta.
- Fase.
- Versión de plantilla.
- Texto generado.
- Fecha de generación.
- Indicador de uso de IA, si se incorpora.

### `templates`

- Nombre.
- Tipo de contenido.
- Versión.
- Estado: borrador, publicada o archivada.
- Contenido.
- Fecha de modificación.

### `settings`

- Sistema zodiacal.
- Sistema de casas.
- Regencias.
- Nodo.
- Lilith.
- Tabla de orbes.
- Configuración de PDF.

## 7. Plantillas de Carta Natal Fase 1

La plantilla debe ser general y reutilizable para Sol, Luna, Ascendente y Descendente.

Cada puerta tendrá estas secciones:

1. Función de la puerta.
2. Posición concreta.
3. Signo.
4. Casa.
5. Regente tradicional y moderno.
6. Integración puerta + signo + casa + regentes.
7. Expresión armónica.
8. Expresión desarmónica por exceso.
9. Expresión desarmónica por defecto.
10. Integración final.
11. Preguntas de autoobservación.
12. Frase recordatoria.

La función específica será diferente para cada puerta:

| Puerta | Pregunta principal |
|---|---|
| Sol | ¿Cómo desarrollo mi identidad y mi voluntad? |
| Luna | ¿Qué necesito para sentir seguridad emocional? |
| Ascendente | ¿Cómo entro en la vida y respondo inicialmente? |
| Descendente | ¿Qué aprendo mediante mis relaciones? |

La IA, si se añade, debe recibir datos y bloques aprobados por el sistema. No debe calcular posiciones, decidir casas ni inventar reglas astrológicas.

## 8. Orbes configurables

Los orbes no se fijarán dentro del código. Se guardarán en configuración para poder modificarlos sin migrar la base de datos.

Tabla inicial orientativa para revisar antes de activar aspectos:

| Aspecto | Orbe inicial |
|---|---:|
| Conjunción | 8° |
| Oposición | 8° |
| Trígono | 7° |
| Cuadratura | 7° |
| Sextil | 5° |
| Quincuncio | 3° |
| Semicuadratura | 2° |
| Sesquicuadratura | 2° |

Estos valores son una propuesta técnica inicial, no una decisión astrológica definitiva. La Fase 1 puede funcionar sin mostrar aspectos.

## 9. Pantallas del MVP

1. Inicio y listado de personas.
2. Alta y edición de persona.
3. Formulario de datos de nacimiento.
4. Confirmación de fecha, hora, lugar y zona horaria.
5. Resultado técnico de la carta.
6. Interpretación Fase 1.
7. Historial de versiones.
8. Vista previa e impresión PDF.
9. Configuración astrológica.
10. Gestión básica de plantillas.

## 10. Seguridad y privacidad

Aunque sea una aplicación privada, almacenará datos personales y de nacimiento. El MVP debe incluir:

- Login de administrador.
- Contraseñas almacenadas con hash seguro.
- Protección CSRF.
- Validación de todos los formularios.
- Consultas parametrizadas mediante ORM.
- Escape de contenido HTML.
- Sesiones seguras.
- Variables sensibles fuera del repositorio.
- HTTPS mediante proxy inverso.
- Acceso por red local o VPN.
- No exponer directamente el Synology a Internet.
- Copias de seguridad de base de datos, plantillas y efemérides.
- Procedimiento probado de restauración.

## 11. Orden técnico para comenzar

### Paso 1: cerrar el entorno Synology

Comprobar:

- Modelo del NAS.
- Versión DSM.
- Disponibilidad de Container Manager.
- Versión PHP disponible.
- Memoria y almacenamiento.
- Posibilidad de usar proxy inverso HTTPS.

### Paso 2: crear el proyecto local

- Crear repositorio Git.
- Crear proyecto Laravel.
- Crear `docker-compose.yml`.
- Levantar PHP, MariaDB y servidor web.
- Configurar `.env` sin guardar secretos.
- Añadir pruebas básicas.

### Paso 3: implementar datos de nacimiento

- Crear modelos y migraciones.
- Crear formulario de persona.
- Crear catálogo inicial de lugares.
- Validar fecha y hora.
- Resolver zona horaria histórica.
- Mostrar confirmación antes del cálculo.

### Paso 4: integrar Swiss Ephemeris

- Confirmar la opción de distribución y licencia.
- Integrar las efemérides.
- Configurar tropical, geocéntrico y Placidus.
- Calcular Sol, Luna, Ascendente y Descendente.
- Añadir Nodo Verdadero y Lilith Media al snapshot aunque todavía no se interpreten.

### Paso 5: validar el cálculo

- Seleccionar varias cartas de referencia.
- Comparar posiciones y casas.
- Documentar tolerancias.
- Probar horarios de verano.
- Probar lugares de distintos países.
- Probar una hora desconocida o aproximada sin presentarla como exacta.

### Paso 6: implementar el Sol completo

- Crear catálogo de funciones.
- Crear contenidos de signos.
- Crear contenidos de casas.
- Crear regencias tradicional y moderna.
- Crear estados armónico, exceso y defecto.
- Renderizar una lectura completa del Sol.

### Paso 7: ampliar a las otras tres puertas

Reutilizar la plantilla y adaptar únicamente:

- Pregunta central.
- Función interpretativa.
- Contenido específico.

### Paso 8: historial y versionado

- Guardar el snapshot del cálculo.
- Asociar cada interpretación a una versión de plantilla.
- Permitir regenerar sin destruir versiones anteriores.
- Mostrar diferencias básicas entre versiones.

### Paso 9: web y PDF

- Diseñar la lectura para pantalla.
- Crear una plantilla de impresión.
- Generar PDF con caracteres españoles y saltos de página correctos.
- Incluir versión del cálculo y de la plantilla.

### Paso 10: desplegar en Synology

- Crear imágenes o contenedores de producción.
- Configurar volúmenes.
- Configurar dominio o acceso interno.
- Activar HTTPS.
- Restaurar una copia de prueba.
- Ejecutar el flujo completo desde el formulario hasta el PDF.

## 12. Estimación de tiempo

Estimación para una persona con dedicación parcial y apoyo continuo durante el desarrollo:

| Bloque | Tiempo |
|---|---:|
| Requisitos y decisiones | 1-2 días |
| Entorno PHP, Laravel y Docker | 2-4 días |
| Personas y datos de nacimiento | 3-5 días |
| Lugares y zonas horarias | 2-4 días |
| Swiss Ephemeris y cálculo | 4-8 días |
| Validación de cartas | 3-5 días |
| Plantilla del Sol | 4-7 días |
| Luna, Ascendente y Descendente | 3-6 días |
| Historial y versionado | 2-4 días |
| Web y PDF | 3-6 días |
| Seguridad, backups y Synology | 3-6 días |

### Estimación global

- MVP funcional sin IA: **4-7 semanas a tiempo parcial**.
- MVP pulido con editor de plantillas y versionado: **6-9 semanas a tiempo parcial**.
- IA, Revolución Solar y más técnicas astrológicas: añadir aproximadamente **2-4 semanas**.

La mayor incertidumbre no está en el CRUD PHP, sino en la integración astronómica, la conversión histórica de zonas horarias, la validación de resultados y la calidad de los textos de las plantillas.

## 13. Criterios de terminado del MVP

El MVP se considerará funcional cuando:

- Se pueda crear una persona.
- Se pueda introducir fecha, hora y lugar.
- La aplicación muestre la conversión horaria antes de calcular.
- Se calcule una carta con tropical, geocéntrico y Placidus.
- Se obtengan Sol, Luna, Ascendente y Descendente.
- Se guarde el resultado como snapshot.
- Se genere la interpretación de Fase 1.
- Se pueda consultar el historial.
- Se pueda generar un PDF.
- Se pueda recuperar una copia de seguridad.
- El flujo funcione desplegado en el Synology.

## 14. Decisiones que quedan pendientes

- Modelo exacto de Synology y versión DSM.
- Versión PHP disponible o elegida para Docker.
- Fuente y formato del catálogo de ciudades.
- Librería o binario concreto para usar Swiss Ephemeris en PHP.
- Licencia aplicable a la distribución elegida.
- Tabla definitiva de orbes.
- Lista exacta de planetas para fases posteriores.
- Diseño visual del PDF.
- Si la residencia actual se guarda como una única ubicación o con historial por año.
- Proveedor de IA, límites de uso y política de privacidad si se activa.

## 15. Primera tarea recomendada

Antes de escribir funcionalidades, crear el esqueleto local del proyecto y comprobar el entorno real del Synology. La primera entrega técnica debería ser una pantalla que reciba fecha, hora y ciudad, confirme la conversión a UTC y muestre un resultado de prueba.

Después se integrará Swiss Ephemeris y se validará una carta real. No conviene empezar por la generación de textos: primero hay que garantizar que los datos astronómicos y horarios son correctos.
