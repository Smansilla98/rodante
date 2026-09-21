#!/usr/bin/env bash
# Arranque limpio de Expo (evita ENOSPC / puertos ocupados cuando se puede).
set -euo pipefail
cd "$(dirname "$0")"

echo "=== inotify watches: $(cat /proc/sys/fs/inotify/max_user_watches) ==="
if [ "$(cat /proc/sys/fs/inotify/max_user_watches)" -lt 200000 ]; then
  echo ""
  echo "Límite de file watchers bajo (causa Error ENOSPC)."
  echo "   Ejecutá UNA vez en tu terminal (pide sudo):"
  echo ""
  echo "   sudo sysctl -w fs.inotify.max_user_watches=524288"
  echo "   sudo sysctl -w fs.inotify.max_user_instances=1024"
  echo "   echo fs.inotify.max_user_watches=524288 | sudo tee /etc/sysctl.d/99-expo-inotify.conf"
  echo "   echo fs.inotify.max_user_instances=1024 | sudo tee -a /etc/sysctl.d/99-expo-inotify.conf"
  echo ""
fi

# Polling como fallback (más CPU, menos inotify)
export CHOKIDAR_USEPOLLING="${CHOKIDAR_USEPOLLING:-1}"
export CHOKIDAR_INTERVAL="${CHOKIDAR_INTERVAL:-1000}"

# Evita ruido de DevTools en Linux (opcional)
export EXPO_NO_TELEMETRY="${EXPO_NO_TELEMETRY:-1}"

# CI vacío rompe getenv boolish de Expo — nunca exportar CI=""
if [ -z "${CI:-}" ]; then
  unset CI 2>/dev/null || true
fi

# Opcional: SEED_QA=1 ./start.sh — carga los escenarios de QA (QaScenariosSeeder) en el
# backend Laravel local antes de levantar Expo, para tener datos de prueba de todos los
# estados/flujos al testear la app. Apagado por defecto. El seeder mismo se niega a
# correr si el backend tiene APP_ENV=production, así que esto nunca toca producción
# aunque EXPO_PUBLIC_API_URL esté apuntando ahí (son cosas independientes: esto corre
# `artisan` contra el .env del backend en ../, no contra la URL que usa la app).
if [ "${SEED_QA:-0}" = "1" ] || [ "${SEED_QA:-0}" = "true" ]; then
  BACKEND_DIR="$(cd .. && pwd)"
  if [ -f "$BACKEND_DIR/artisan" ]; then
    echo "=== Cargando escenarios de QA (QaScenariosSeeder) en $BACKEND_DIR ==="
    (cd "$BACKEND_DIR" && php artisan db:seed --class=QaScenariosSeeder) || \
      echo "AVISO: no se pudieron cargar los escenarios de QA (seguimos igual con Expo)."
  else
    echo "AVISO: SEED_QA=1 pero no encontré $BACKEND_DIR/artisan — se omite."
  fi
fi

PORT="${PORT:-8089}"
echo "=== Expo en puerto ${PORT} → API: ${EXPO_PUBLIC_API_URL:-ver .env} ==="
echo "    (Usá Expo Go en el celular; Proceed anonymously si pregunta login)"
exec npx expo start -c --port "$PORT"
