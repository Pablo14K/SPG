#!/bin/bash
# Arranca el watcher en segundo plano y guarda el PID.
# Uso:  bash bin/arrancar.sh

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PID_FILE="$SCRIPT_DIR/../vigilar.pid"
LOG_FILE="$SCRIPT_DIR/../logs/watcher.log"

if [ -f "$PID_FILE" ]; then
    PID=$(cat "$PID_FILE")
    if kill -0 "$PID" 2>/dev/null; then
        echo "El watcher ya está corriendo (PID $PID)."
        exit 0
    fi
fi

mkdir -p "$(dirname "$LOG_FILE")"
nohup php "$SCRIPT_DIR/vigilar.php" >> "$LOG_FILE" 2>&1 &
echo $! > "$PID_FILE"
echo "Watcher arrancado (PID $(cat "$PID_FILE")). Log: $LOG_FILE"
