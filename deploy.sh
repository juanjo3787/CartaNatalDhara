#!/bin/sh
set -eu

DEPLOY_PATH="${DEPLOY_PATH:-/volume1/docker/configCNDhara}"
PROJECT_PATH="${PROJECT_PATH:-/volume1/docker/CartaNatalDhara}"
APP_PATH="$PROJECT_PATH/app"
ENV_FILE="$DEPLOY_PATH/.env"
COMPOSE_FILE="${COMPOSE_FILE:-$PROJECT_PATH/docker-compose.yml}"

if [ ! -d "$APP_PATH" ]; then
    echo "No existe $APP_PATH. Copia el proyecto completo en $PROJECT_PATH antes de desplegar."
    exit 1
fi

if [ ! -f "$COMPOSE_FILE" ]; then
    echo "No existe $COMPOSE_FILE. Actualiza el repositorio o configura COMPOSE_FILE antes de desplegar."
    exit 1
fi

if [ ! -f "$ENV_FILE" ]; then
    cp "$APP_PATH/.env.example" "$ENV_FILE"
    echo "Se ha creado $ENV_FILE. Completa APP_KEY, DB_PASSWORD y AI_API_KEY antes de volver a ejecutar este script."
    exit 1
fi

if ! git -C "$PROJECT_PATH" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "$PROJECT_PATH no es un repositorio Git valido. No se puede actualizar el proyecto automaticamente."
    exit 1
fi

if [ "${CND_DEPLOY_UPDATED:-}" != "$PROJECT_PATH" ]; then
    echo "Actualizando codigo en $PROJECT_PATH..."
    git -C "$PROJECT_PATH" fetch --prune
    git -C "$PROJECT_PATH" reset --hard "@{u}"
    export CND_DEPLOY_UPDATED="$PROJECT_PATH"
    export PROJECT_PATH DEPLOY_PATH COMPOSE_FILE
    echo "Continuando con el deploy.sh actualizado del repositorio..."
    exec sh "$PROJECT_PATH/deploy.sh" "$@"
fi
unset CND_DEPLOY_UPDATED
echo "Script de despliegue: $PROJECT_PATH/deploy.sh"
echo "Compose utilizado: $COMPOSE_FILE"

mkdir -p \
    "$DEPLOY_PATH/storage/app" \
    "$DEPLOY_PATH/storage/app/private/pdfs" \
    "$DEPLOY_PATH/storage/framework/cache" \
    "$DEPLOY_PATH/storage/framework/sessions" \
    "$DEPLOY_PATH/storage/framework/views" \
    "$DEPLOY_PATH/storage/logs" \
    "$DEPLOY_PATH/bootstrap-cache"

export ENV_FILE
export COMPOSE_ENV_FILE="$ENV_FILE"
export PROJECT_PATH DEPLOY_PATH

echo "Construyendo imagen Docker desde $APP_PATH..."
cd "$APP_PATH"
docker build -t carta-natal-dhara:latest .

APP_KEY_VALUE="$(grep -E '^APP_KEY=' "$ENV_FILE" | tail -n 1 | cut -d '=' -f 2- || true)"
ROTATE_APP_KEY_VALUE="$(grep -E '^ROTATE_APP_KEY=' "$ENV_FILE" | tail -n 1 | cut -d '=' -f 2- | tr -d '"' || true)"
if [ -z "$APP_KEY_VALUE" ] || [ "$ROTATE_APP_KEY_VALUE" = "true" ]; then
    if [ "$ROTATE_APP_KEY_VALUE" = "true" ]; then
        echo "ROTATE_APP_KEY=true. Regenerando APP_KEY de Laravel..."
    else
        echo "APP_KEY no existe en $ENV_FILE. Generando clave de Laravel..."
    fi
    GENERATED_APP_KEY="$(docker run --rm carta-natal-dhara:latest php -r 'echo "base64:".base64_encode(random_bytes(32));')"
    if grep -q -E '^APP_KEY=' "$ENV_FILE"; then
        sed -i "s#^APP_KEY=.*#APP_KEY=$GENERATED_APP_KEY#" "$ENV_FILE"
    else
        printf '\nAPP_KEY=%s\n' "$GENERATED_APP_KEY" >> "$ENV_FILE"
    fi

    if [ "$ROTATE_APP_KEY_VALUE" = "true" ]; then
        sed -i "s#^ROTATE_APP_KEY=.*#ROTATE_APP_KEY=false#" "$ENV_FILE"
        echo "ROTATE_APP_KEY se ha vuelto a dejar en false para evitar regeneraciones accidentales."
    fi
fi

cd "$DEPLOY_PATH"

echo "Verificando MySQL con la configuracion de $ENV_FILE (sin usar la cache anterior)..."
if ! docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" run --rm --no-deps -T \
    -e APP_CONFIG_CACHE=/tmp/report-deploy-preflight-config.php app php <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = Illuminate\Support\Facades\DB::connection();
$driver = $connection->getConfig('driver');
$host = $connection->getConfig('host');
$port = $connection->getConfig('port');
echo 'Conexion efectiva: driver='.$driver.' host='.json_encode($host).' port='.$port.PHP_EOL;
// In host networking, 127.0.0.1 reaches the NAS MySQL service.
try {
    $connection->select('SELECT 1');
    echo "Conexion a la base de datos correcta.\n";
} catch (Throwable $error) {
    $cause = $error;
    while ($cause->getPrevious()) {
        $cause = $cause->getPrevious();
    }
    $detail = $cause->getMessage();
    foreach (['password', 'username', 'url'] as $field) {
        $secret = $connection->getConfig($field);
        if (is_string($secret) && $secret !== '') {
            $detail = str_replace($secret, '[redacted]', $detail);
        }
    }
    fwrite(STDERR, 'Fallo MySQL: '.$detail.PHP_EOL);
    if (is_string($host) && in_array($driver, ['mysql', 'mariadb'], true)) {
        $address = str_contains($host, ':') ? '['.$host.']' : $host;
        $socket = @stream_socket_client('tcp://'.$address.':'.$port, $errno, $errstr, 5);
        if ($socket === false) {
            fwrite(STDERR, "Prueba TCP desde Docker: fallo $errno ($errstr). Revisa que MySQL este iniciado, el puerto publicado, la interfaz de escucha y el firewall del servidor.\n");
        } else {
            fclose($socket);
            fwrite(STDERR, "Prueba TCP desde Docker: puerto accesible. Revisa el error MySQL anterior (servicio, TLS, socket o autenticacion).\n");
        }
    }
    exit(1);
}
PHP
then
    echo "Despliegue detenido antes de parar servicios o modificar la base de datos. Corrige $ENV_FILE y vuelve a ejecutar."
    exit 1
fi

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" down --remove-orphans || true

if docker ps -a --format '{{.Names}}' | grep -Fxq 'cartaNatal-app'; then
    echo "Eliminando contenedor anterior cartaNatal-app..."
    docker rm -f cartaNatal-app
fi

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --no-build app
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T app chown -R www-data:www-data storage bootstrap/cache
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T app chmod -R u+rwX storage bootstrap/cache
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T app apache2ctl -t
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T -u www-data app php artisan optimize:clear
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T -u www-data app php artisan migrate --force
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T -u www-data app php artisan db:seed --force
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T -u www-data app php artisan optimize:clear
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T -u www-data app php artisan config:cache
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T -u www-data app php artisan view:cache
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T -u www-data app sh -c '
    for view in storage/framework/views/*.php; do
        [ -f "$view" ] || continue
        php -l "$view" > /dev/null || exit 1
    done
'
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --no-build report-worker

# Apache listens on 8000 directly on the NAS with network_mode: host.
# There is no Docker port mapping; APP_PORT does not change Apache's listener.
APP_PORT_VALUE=8000

if command -v curl >/dev/null 2>&1; then
    HEALTH_STATUS="$(curl --connect-timeout 5 --max-time 20 -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$APP_PORT_VALUE/up" || true)"
    HTTP_STATUS="$(curl --connect-timeout 5 --max-time 20 -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$APP_PORT_VALUE/charts" || true)"
    if [ "$HEALTH_STATUS" != "200" ] || { [ "$HTTP_STATUS" != "200" ] && [ "$HTTP_STATUS" != "302" ]; }; then
        echo "Comprobacion HTTP: /up=$HEALTH_STATUS /charts=$HTTP_STATUS"
        echo "La aplicacion responde con HTTP $HTTP_STATUS. Ultimos logs del contenedor:"
        docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" logs --tail=80 app
        echo "Ultimos errores Laravel (pueden incluir entradas anteriores a este despliegue):"
        docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T -u www-data app sh -c '
            for log in storage/logs/laravel.log "storage/logs/laravel-$(date +%F).log"; do
                if [ -f "$log" ]; then
                    echo "$log"
                    tail -n 60 "$log"
                fi
            done
        ' || true
        exit 1
    fi
fi

echo "Despliegue finalizado: ${APP_URL:-https://cartanatal.saranaveda.com}"
