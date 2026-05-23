#!/usr/bin/env bash
# bump-version.sh — propagate a new werbeauf-customs version everywhere.
#
# Updates:
#   1. werbeauf-customs.php "Version:" header
#   2. werbeauf-customs.php WERBEAUF_PLUGIN_VERSION constant
#   3. wp-content/plugins/werbeauf-customs/CLAUDE.md "v<old>" → "v<new>"
#   4. ~/WEB/Clients/drperi/CLAUDE.md "(v<old>)" → "(v<new>)"
#   5. Prepends a stub entry to CHANGELOG.md
#
# Usage:  ./bin/bump-version.sh 2.1.0 "Short release headline"
#
# Run from anywhere — the script resolves paths relative to itself.

set -euo pipefail

if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <new-version> [headline]" >&2
    exit 1
fi

NEW="$1"
HEADLINE="${2:-(no headline supplied)}"
TODAY="$(date +%Y-%m-%d)"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PLUGIN_FILE="$PLUGIN_DIR/werbeauf-customs.php"
PLUGIN_CLAUDE="$PLUGIN_DIR/CLAUDE.md"
PROJECT_CLAUDE="$PLUGIN_DIR/../../../CLAUDE.md"
CHANGELOG="$PLUGIN_DIR/CHANGELOG.md"

# Resolve the OLD version from the plugin header.
OLD="$(grep -E '^[[:space:]]*\*[[:space:]]*Version:' "$PLUGIN_FILE" | head -1 | sed -E 's/.*Version:[[:space:]]+//' | tr -d '[:space:]')"

if [[ -z "$OLD" ]]; then
    echo "Could not detect current version in $PLUGIN_FILE" >&2
    exit 2
fi

if [[ "$OLD" == "$NEW" ]]; then
    echo "Version is already $NEW — nothing to do." >&2
    exit 0
fi

echo "Bumping werbeauf-customs: $OLD → $NEW"

# 1 + 2: plugin file (in-place edit, two replacements).
# Escape regex metachars in OLD for safe interpolation into sed pattern.
OLD_ESC="$(printf '%s' "$OLD" | sed 's/[][\\/.*^$]/\\&/g')"
sed -i '' -E \
    -e "s/^([[:space:]]*\\*[[:space:]]*Version:[[:space:]]+)${OLD_ESC}\$/\\1${NEW}/" \
    -e "s/(WERBEAUF_PLUGIN_VERSION'[[:space:]]*,[[:space:]]*')${OLD_ESC}(')/\\1${NEW}\\2/" \
    "$PLUGIN_FILE"

# 3: plugin CLAUDE.md
sed -i '' -E "s/werbeauf-customs\` v$OLD /werbeauf-customs\` v$NEW /" "$PLUGIN_CLAUDE"

# 4: project CLAUDE.md (referenced as "(v2.0.2)")
sed -i '' -E "s/werbeauf-customs\\/\` \\(v$OLD\\)/werbeauf-customs\\/\` (v$NEW)/" "$PROJECT_CLAUDE"

# 5: prepend CHANGELOG entry. Insert after the top "---" divider.
TMP="$(mktemp)"
awk -v ver="$NEW" -v date="$TODAY" -v head="$HEADLINE" '
    BEGIN { inserted = 0 }
    /^---$/ && !inserted {
        print
        print ""
        print "## " ver " — " date
        print ""
        print "**" head "**"
        print ""
        print "- TODO: list changes"
        print ""
        print "---"
        inserted = 1
        next
    }
    { print }
' "$CHANGELOG" > "$TMP" && mv "$TMP" "$CHANGELOG"

echo "Done. Files touched:"
echo "  $PLUGIN_FILE"
echo "  $PLUGIN_CLAUDE"
echo "  $PROJECT_CLAUDE"
echo "  $CHANGELOG"
echo
echo "Next: fill in the CHANGELOG TODO list and commit."
