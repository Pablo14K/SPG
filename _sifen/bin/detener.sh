#!/bin/bash
# Detiene el watcher.
# Uso:  bash bin/detener.sh

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PID_FILE="$SCRIPT_DIR/../vigilar.pid"

if [ ! -f "$PID_FILE" ]; then
    echo "No hay watcher corriendo (no existe vigilar.pid)."
    exit 0
fi

PID=$(cat "$PID_FILE")
if kill "$PID" 2>/dev/null; then
    echo "Watcher detenido (PID $PID)."
    rm -f "$PID_FILE"
else
    echo "El proceso $PID ya no existía. Limpiando PID."
    rm -f "$PID_FILE"
fi
