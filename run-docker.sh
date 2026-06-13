#!/usr/bin/env bash
set -euo pipefail

APP_URL="http://localhost:8080"

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker tidak ditemukan."
  echo "Jika memakai WSL, aktifkan Docker Desktop > Settings > Resources > WSL integration."
  exit 1
fi

if docker compose version >/dev/null 2>&1; then
  COMPOSE=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
  COMPOSE=(docker-compose)
else
  echo "Docker Compose tidak ditemukan."
  exit 1
fi

mkdir -p assets/img/uploads/items assets/img/uploads/logo
chmod -R 777 assets/img/uploads

echo "Menjalankan UniStock dengan Docker..."
"${COMPOSE[@]}" up -d --build

echo "Menunggu database siap..."
until "${COMPOSE[@]}" exec -T db mariadb-admin ping -h localhost -uroot -proot --silent >/dev/null 2>&1; do
  sleep 2
done

echo "Memastikan nama institusi default aman..."
"${COMPOSE[@]}" exec -T db mariadb -uunistock -punistock unistock \
  -e "UPDATE settings SET value='Universitas Esa Unggul' WHERE \`key\`='university_name';"

echo
echo "UniStock sudah berjalan."
echo "URL aplikasi : ${APP_URL}"
echo "Database     : localhost:3307"
echo
echo "Akun demo:"
echo "  superadmin / password"
echo "  admin      / password"
echo "  worker1    / password"
echo
echo "Perintah berguna:"
echo "  docker compose logs -f"
echo "  docker compose down"
echo "  docker compose down -v   # reset database dan import ulang database/unistock.sql"
