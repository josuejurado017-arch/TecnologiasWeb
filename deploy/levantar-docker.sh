#!/usr/bin/env bash
# Levanta TecnologiasWeb con Docker Compose en un solo paso.
# Uso en el servidor Ubuntu (o en cualquier equipo con Docker):
#   cd ~/TecnologiasWeb && ./deploy/levantar-docker.sh
#
# Reemplaza el flujo manual (VM + tunel SSH + PHP local + MySQL local):
# la app y MySQL corren juntos en contenedores, sin tunel.
set -euo pipefail

cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
  echo "No existe .env. Copia .env.example y define DB_PASSWORD y MYSQL_ROOT_PASSWORD."
  exit 1
fi

echo ">> Trayendo ultimos cambios desde el fork (origin)..."
git pull --ff-only origin "$(git rev-parse --abbrev-ref HEAD)" || echo "   (omitido: sin remoto o sin cambios)"

echo ">> Construyendo y levantando contenedores..."
docker compose up -d --build

echo ">> Esperando a que MySQL este listo..."
until docker compose exec -T db mysqladmin ping -h 127.0.0.1 --silent >/dev/null 2>&1; do
  sleep 2
done

# Crea el administrador solo si aun no existe (idempotente).
if [ "${1:-}" = "--con-admin" ]; then
  ADMIN_USER="${2:-admin}"
  ADMIN_PASS="${3:-admin123}"
  echo ">> Asegurando usuario administrador '$ADMIN_USER'..."
  docker compose exec -T web php deploy/docker/create-admin.php "$ADMIN_USER" "$ADMIN_PASS" || true
fi

echo ""
echo ">> Listo. La app responde en:"
echo "   http://localhost:${WEB_PORT:-80}/   (o http://tutorias.local/ si el DNS apunta aqui)"
echo ">> Ver estado:   docker compose ps"
echo ">> Ver logs:     docker compose logs -f web"
echo ">> Detener:      docker compose down"
