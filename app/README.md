# Carta Natal Fase 1

Aplicacion privada PHP/Laravel para calcular cartas natales y generar la interpretacion de la Fase 1.

## Estado

Laravel 13 instalado y funcional en `aplicacion`. El dominio astrologico inicial (protocolo, regencias, contratos) ya esta integrado y verificado. Migraciones y modelos (`people`, `birth_data`, `places`, `charts`, `interpretations`, `templates`) creados y probados contra el MySQL real del Synology.

`AstrologyCalculator` implementado con Swiss Ephemeris (`swetest`), compilado dentro del Dockerfile y validado end-to-end en un contenedor real: calcula Sol, Luna, planetas, Nodo Verdadero, Lilith (apogeo medio), Ascendente, Descendente, MC y las 12 casas Placidus.

## Base de datos: MySQL en el Synology

La aplicacion se conecta directamente al MySQL que corre en el propio Synology (no se usa un contenedor de base de datos local).

- Host: `192.168.0.245`
- Puerto: `3306`
- Base de datos: `cartaNatalDhara`

Estos valores estan en `.env` y `.env.example`. `DB_USERNAME` y `DB_PASSWORD` son placeholders (`change-me`): sustituyelos por las credenciales reales del usuario MySQL creado en el Synology y nunca los subas al repositorio.

En el Synology, dentro del gestor de MariaDB/MySQL, verifica que:

- El usuario tiene permisos sobre `cartaNatalDhara` con host `%` o la subred de Docker.
- El servicio escucha en la interfaz LAN (no solo `127.0.0.1`).
- El firewall del Synology permite el puerto 3306 desde la red donde correra el contenedor.

### Error conocido: host no autorizado

Si al migrar aparece `Host 'x.x.x.x' is not allowed to connect to this MariaDB server`, el usuario de MySQL en el Synology esta restringido a un host concreto. Debes editar el usuario (en phpMyAdmin, Adminer o la consola SSH del NAS) para que su columna `Host` sea `%` (cualquier host) o la IP/subred exacta desde la que se conecta la aplicacion, y despues aplicar `FLUSH PRIVILEGES`.

## Protocolo astrologico

- Zodiaco tropical.
- Referencia geocentrica.
- Casas Placidus.
- Regencias tradicional y moderna con igual peso interpretativo.
- Nodo Verdadero.
- Lilith como Luna Negra Media.
- Orbes configurables en `config/astrology.php`.
- Revolucion Solar usando la residencia actual (modulo posterior).

## Requisitos verificados en este equipo

- Git 2.55
- PHP 8.3.33 con `fileinfo`, `openssl`, `mbstring`, `curl`, `pdo_mysql` habilitados
- Composer 2.10.3
- Docker 29.8.0 / Docker Compose v5.5.1

## Arranque en local sin Docker

```powershell
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

`.env` ya apunta al MySQL del Synology. Asegurate de que tu equipo puede alcanzar `192.168.0.245:3306` en la red local antes de migrar.

## Arranque con Docker en el Synology

El despliegue usa dos rutas en el Synology:

- Configuracion y compose: `/volume1/docker/configCNDhara`.
- Codigo del proyecto: `/volume1/docker/CartaNatalDhara`.

1. Copiar el proyecto completo a `/volume1/docker/CartaNatalDhara`.
2. Copiar `docker-compose.yml`, `.env` y `deploy.sh` a `/volume1/docker/configCNDhara`.
3. Completar `/volume1/docker/configCNDhara/.env` con `APP_KEY`, `DB_PASSWORD` y `AI_API_KEY` reales.
4. Ejecutar desde SSH en el Synology:
  ```sh
  cd /volume1/docker/configCNDhara
  chmod +x deploy.sh
  ./deploy.sh
  ```
5. Abrir `https://cartanataldhara.synology.me`.

El script actualiza el codigo con `git fetch --prune` y `git reset --hard @{u}`, crea las carpetas persistentes `storage` y `bootstrap-cache` en la ruta de configuracion, construye la imagen desde la carpeta `app`, genera `APP_KEY` si falta en el `.env`, la regenera si `ROTATE_APP_KEY=true`, elimina un contenedor anterior `cartaNatal-app` si existe, arranca el contenedor con Compose sin reconstruirlo, ejecuta migraciones, cachea la configuracion de Laravel y comprueba la respuesta local para mostrar logs si aparece una 500.

## Estructura del dominio astrologico

- `app/Contracts/AstrologyCalculator.php`: contrato del calculador astronomico.
- `app/Domain/Astrology/BirthData.php`: datos de nacimiento normalizados.
- `app/Domain/Astrology/ChartSnapshot.php`: snapshot inmutable del calculo.
- `app/Domain/Astrology/AstrologicalProtocol.php`: protocolo fijado (zodiaco, casas, nodo, Lilith).
- `app/Domain/Astrology/RegencyResolver.php`: regencias tradicional y moderna por signo.
- `app/Domain/Astrology/Calculators/SwissEphemerisCalculator.php`: implementacion real con `swetest`, validada en Docker.
- `app/Domain/Astrology/TimezoneResolver.php`: convierte fecha/hora local + zona horaria IANA a UTC real (usa tzdata del sistema, incluye horario de verano historico).
- `app/Domain/Astrology/BirthDataNormalizer.php`: valida y normaliza los datos brutos de nacimiento antes de persistirlos.
- `app/Services/ChartService.php`: calcula una carta a partir de un `BirthData` persistido y guarda el `ChartSnapshot` en la tabla `charts`.
- `app/Console/Commands/CalculateDemoChart.php`: comando `chart:demo` para probar el flujo completo end-to-end.
- `app/Http/Controllers/ChartController.php`: formulario web (crear persona + lugar + birth_data + calcular carta).
- `resources/views/charts/`: vistas `create`, `show` e `index` del formulario y resultado.
- `config/astrology.php`: configuracion y tabla de orbes.

## Swiss Ephemeris (swetest)

- Compilado en el Dockerfile desde el codigo fuente oficial (https://github.com/aloistr/swisseph, licencia AGPL) usando `make swetests` (version estatica, sin dependencias en runtime).
- El binario queda en `/usr/local/bin/swetest` y las efemerides completas en `/opt/ephemeris` dentro de la imagen.
- Comando real verificado:
  ```
  swetest -edir/opt/ephemeris -b<dia>.<mes>.<anio> -ut<hora>:<minuto> -house<lon>,<lat>,P -p0123456789tA -fPl -g, -head
  ```
- Formato de salida: cada linea es `Nombre planeta, longitud_decimal`. Tambien devuelve las 12 casas, Ascendente, MC, ARMC y Vertex.
- Importante: al invocar `swetest` desde PHP/Symfony Process, usar `sh -c "..."` como wrapper; invocarlo directamente como array de argumentos puede fallar con "illegal option" segun el entorno.
- IMPORTANTE: `swetest -ut` espera la hora en UTC, no la hora local de nacimiento. `ChartService` ya convierte con `TimezoneResolver` antes de llamar al calculador; nunca pasar hora local directamente.
- Para probar manualmente dentro del contenedor:
  ```powershell
  docker run --rm carta-natal-app:dev sh -c "swetest -edir/opt/ephemeris -b15.6.1990 -ut14:30 -house-3.7,40.4,P -p0123456789tA -fPl -g, -head"
  ```
- Para probar el flujo completo (persona + lugar + birth_data + carta persistida en el MySQL del Synology):
  ```powershell
  docker run --rm -v "E:\01_Proyectos\Astrologia\CartaNatalDhara\aplicacion:/var/www/html" -w /var/www/html carta-natal-app:dev php artisan chart:demo
  ```

### Validacion de precision

En vez de depender de una API externa de terceros, se valido el motor contra un punto astronomico exacto y verificable: en el instante preciso de un equinoccio o solsticio, la longitud tropical del Sol es por definicion 0° o 270°/90°/180° exactos.

- Equinoccio de marzo 2024 (20 marzo 2024, 03:06 UTC): Sol calculado en `359.9997212°` (a ~1 segundo de arco de los 0°00'00" Aries esperados).
- Solsticio de diciembre 2024 (21 diciembre 2024, 09:20 UTC): Sol calculado en `269.9995947°` (a ~1.5 segundos de arco de los 270°00'00" Capricornio esperados).

Ambas desviaciones son coherentes con el redondeo a minutos de las horas de referencia (el Sol se mueve ~2.5 arcosegundos por minuto), no con un error del motor. Esto confirma una precision sub-2-arcosegundos en dos puntos opuestos del zodiaco.

## Modelo de datos

- `Place`: lugares con coordenadas y zona horaria.
- `Person`: personas, con residencia actual opcional para la futura Revolucion Solar.
- `BirthData`: datos de nacimiento normalizados, con fuente y precision de la hora.
- `Chart`: snapshot inmutable de cada calculo astrologico.
- `ChartTemplate` (tabla `templates`): plantillas versionadas de interpretacion, clasificadas por `door` (`sol`/`luna`/`ascendente`/`descendente`, `null` = texto compartido) y `block` (`shared_intro`, `shared_states`, `shared_conclusions` para el texto fijo en gris del dossier; `function`, `sign`, `house`, `ruler`, `integration`, `harmony`, `deficit`, `excess`, `closing` para los bloques personalizables de cada puerta).
- `Interpretation`: texto generado para una carta, ligado a una version de plantilla; hereda los mismos campos `door`/`block` para que cada fila represente un bloque concreto, y anade `rulers_used` (JSON) con los planetas explicados como regentes en ese bloque.

### Continuidad entre puertas

`App\Domain\Astrology\DoorSequence` fija el orden canonico Sol -> Luna -> Ascendente -> Descendente y la taxonomia de bloques. `App\Domain\Astrology\RulerUsageRegistry` consulta las interpretaciones ya generadas de una carta (bloques `ruler` de puertas anteriores) para saber que planetas regentes ya se explicaron, de modo que el motor de generacion pueda cambiar la pregunta funcional en vez de repetir la explicacion (p. ej. Venus regente compartido entre varias puertas).

## Proximos pasos tecnicos

1. Redactar el catalogo de contenido del Sol (bloque de referencia segun el dossier) usando `RegencyResolver` para los 8 bloques por signo/casa, y reutilizar el patron para Luna, Ascendente y Descendente.
2. Construir el motor de renderizado que combine plantillas fijas (`shared_*`) con las personalizadas y aplique `RulerUsageRegistry` al generar el bloque `ruler`.
3. Anadir generacion de PDF a partir de las `Interpretation` guardadas.
4. Anadir autenticacion basica de administrador antes de exponer la app fuera de la red local.

Ver el plan completo en `../Documentos/Plan/plan-tecnico-aplicacion-carta-natal.md`.
