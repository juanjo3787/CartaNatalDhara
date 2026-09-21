#!/bin/sh
set -eu

DEPLOY_PATH="${DEPLOY_PATH:-/volume1/docker/configCNDhara}"
APP_PATH="$DEPLOY_PATH/app"
ENV_FILE="$APP_PATH/.env"

if [ ! -d "$APP_PATH" ]; then
    echo "No existe $APP_PATH. Copia el proyecto completo en $DEPLOY_PATH antes de desplegar."
    exit 1
fi

if [ ! -f "$ENV_FILE" ]; then
    cp "$APP_PATH/.env.example" "$ENV_FILE"
    echo "Se ha creado $ENV_FILE. Completa APP_KEY, DB_PASSWORD y AI_API_KEY antes de volver a ejecutar este script."
    exit 1
fi

mkdir -p \
    "$DEPLOY_PATH/storage/app" \
    "$DEPLOY_PATH/storage/framework/cache" \
    "$DEPLOY_PATH/storage/framework/sessions" \
    "$DEPLOY_PATH/storage/framework/views" \
    "$DEPLOY_PATH/storage/logs" \
    "$DEPLOY_PATH/bootstrap-cache"

cd "$DEPLOY_PATH"

docker compose --env-file "$ENV_FILE" build
docker compose --env-file "$ENV_FILE" up -d
docker compose --env-file "$ENV_FILE" exec -T app php artisan migrate --force
docker compose --env-file "$ENV_FILE" exec -T app php artisan optimize:clear
docker compose --env-file "$ENV_FILE" exec -T app php artisan config:cache

echo "Despliegue finalizado: ${APP_URL:-https://cartanataldhara.synology.me}"