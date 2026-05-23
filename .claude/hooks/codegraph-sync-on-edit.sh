#!/usr/bin/env bash
# ============================================================
# DATEI: .claude/hooks/codegraph-sync-on-edit.sh
# ZWECK: PostToolUse-Hook fuer Claude Code. Re-syncen der
#        CodeGraph-Knowledge-Graph-DB nach jedem Edit/Write,
#        damit kuenftige codegraph_* MCP-Calls den frischen
#        Stand zeigen.
#
# DESIGN:
#   - Nur bei .php-Dateien INSIDE diesem Plugin
#   - Sync laeuft IM HINTERGRUND (detached) -> Claude wartet nicht
#   - Stdout/Stderr in .claude/sync.log -> debugbar, gitignored
#   - Idempotent: codegraph sync ist no-op wenn nichts geaendert
#
# REGISTRIERUNG: siehe ~/WEB/Clients/drperi/.claude/settings.json
# ============================================================

set -uo pipefail

PLUGIN_DIR="/Users/macos/WEB/Clients/drperi/wp-content/plugins/werbeauf-customs"
LOG="$PLUGIN_DIR/.claude/sync.log"

# Tool-Event-JSON von Claude Code via stdin lesen
INPUT="$(cat 2>/dev/null || true)"

# file_path aus dem JSON extrahieren (Edit + Write benutzen beide das Feld)
FILE_PATH="$(printf '%s' "$INPUT" | /usr/bin/env python3 -c '
import json, sys
try:
    d = json.load(sys.stdin)
    print(d.get("tool_input", {}).get("file_path", ""))
except Exception:
    print("")
' 2>/dev/null || echo "")"

# Bedingungen: muss .php-Datei in diesem Plugin sein
if [[ -z "$FILE_PATH" ]]; then
    exit 0
fi
if [[ "$FILE_PATH" != "$PLUGIN_DIR"/* ]]; then
    exit 0
fi
if [[ "$FILE_PATH" != *.php ]]; then
    exit 0
fi

# Debounce: nicht mehr als einmal pro 30 Sekunden syncen.
# Wenn die Mitarbeiterin in 1 Minute 20 Files editiert, soll nur 2x gesynct werden.
LAST_SYNC_MARKER="$PLUGIN_DIR/.claude/.last-sync"
NOW=$(date +%s)
LAST=$(cat "$LAST_SYNC_MARKER" 2>/dev/null || echo 0)
if (( NOW - LAST < 30 )); then
    exit 0
fi
echo "$NOW" > "$LAST_SYNC_MARKER"

# Header in den Log schreiben (mit Timestamp + welche Datei den Trigger ausgeloest hat)
{
    echo ""
    echo "=== $(date '+%Y-%m-%d %H:%M:%S')  trigger=$FILE_PATH ==="
} >> "$LOG" 2>/dev/null

# Hintergrund-Sync. Detached via nohup + & + disown.
# env -i + expliziter PATH, damit der Hook auch ohne shell-Profile feuert.
(
    cd "$PLUGIN_DIR" 2>/dev/null && \
    nohup /usr/bin/env -i \
        PATH="/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin" \
        HOME="$HOME" \
        npx -y @colbymchenry/codegraph sync \
        >> "$LOG" 2>&1
) &

disown 2>/dev/null || true

# Hook-Exit-Code 0 = "weitermachen, kein Block" fuer Claude Code
exit 0
