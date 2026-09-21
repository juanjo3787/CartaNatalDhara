#!/bin/sh
set -eu

DEPLOY_PATH="${DEPLOY_PATH:-/volume1/docker/configCNDhara}"
PROJECT_PATH="${PROJECT_PATH:-/volume1/docker/CartaNatalDhara}"
APP_PATH="$PROJECT_PATH/app"
ENV_FILE="$DEPLOY_PATH/.env"
COMPOSE_FILE="$DEPLOY_PATH/docker-compose.yml"

if [ ! -d "$APP_PATH" ]; then
    echo "No existe $APP_PATH. Copia el proyecto completo en $PROJECT_PATH antes de desplegar."
    exit 1
fi

if [ ! -f "$COMPOSE_FILE" ]; then
    echo "No existe $COMPOSE_FILE. Copia docker-compose.yml en $DEPLOY_PATH antes de desplegar."
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

echo "Actualizando codigo en $PROJECT_PATH..."
git -C "$PROJECT_PATH" fetch --prune
git -C "$PROJECT_PATH" reset --hard "@{u}"

mkdir -p \
    "$DEPLOY_PATH/storage/app" \
    "$DEPLOY_PATH/storage/framework/cache" \
    "$DEPLOY_PATH/storage/framework/sessions" \
    "$DEPLOY_PATH/storage/framework/views" \
    "$DEPLOY_PATH/storage/logs" \
    "$DEPLOY_PATH/bootstrap-cache"

export ENV_FILE
export COMPOSE_ENV_FILE="$ENV_FILE"

echo "Construyendo imagen Docker desde $APP_PATH..."
cd "$APP_PATH"
docker build -t carta-natal-dhara:latest .

cd "$DEPLOY_PATH"

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" down --remove-orphans || true

if docker ps -a --format '{{.Names}}' | grep -Fxq 'cartaNatal-app'; then
    echo "Eliminando contenedor anterior cartaNatal-app..."
    docker rm -f cartaNatal-app
fi

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --no-build
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T app php artisan migrate --force
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T app php artisan optimize:clear
docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T app php artisan config:cache

echo "Despliegue finalizado: ${APP_URL:-https://cartanataldhara.synology.me}"