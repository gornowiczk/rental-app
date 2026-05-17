#!/usr/bin/env bash
set -euo pipefail

APP_BIN="bin/console"

usage() {
  cat <<EOF
Usage: $0 [baseline|hard-reset|status|validate]

  baseline   - wyczyść rejestr wersji i utwórz jedną migrację bazową bez zmiany istniejącej bazy
  hard-reset - usuń pliki migracji, zrzuć schemat i odtwórz go z nowej migracji
  status     - pokaż status migracji
  validate   - waliduj mapping i schemat
EOF
}

baseline() {
  rm -rf migrations/*
  mkdir -p migrations
  ${APP_BIN} doctrine:migrations:diff --from-empty-schema --no-interaction
  ${APP_BIN} doctrine:migrations:version --delete --all --no-interaction
  ${APP_BIN} doctrine:migrations:version --add --all --no-interaction
  ${APP_BIN} doctrine:migrations:sync-metadata-storage || true
  ${APP_BIN} doctrine:schema:validate
  echo "✅ Baseline gotowe."
}

hard_reset() {
  # UWAGA: to skasuje schemat i dane
  rm -rf migrations/*
  mkdir -p migrations
  ${APP_BIN} doctrine:migrations:diff --from-empty-schema --no-interaction
  ${APP_BIN} doctrine:migrations:version --delete --all --no-interaction
  ${APP_BIN} doctrine:schema:drop --full-database --force --no-interaction
  ${APP_BIN} doctrine:migrations:migrate --no-interaction
  ${APP_BIN} doctrine:schema:validate
  echo "🧹 Twardy reset zakończony."
}

status() {
  ${APP_BIN} doctrine:migrations:status
}

validate() {
  ${APP_BIN} doctrine:schema:validate
}

case "${1:-}" in
  baseline)    baseline ;;
  hard-reset)  hard_reset ;;
  status)      status ;;
  validate)    validate ;;
  *) usage; exit 1 ;;
esac
