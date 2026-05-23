# Knowledge-Graph auto-sync hook — one-time setup

This hook re-syncs the CodeGraph index automatically after every `Edit` / `Write` Claude Code makes on a `.php` file inside this plugin. Debounced to 30 seconds so a burst of edits triggers only one sync.

**Why this needs manual installation:** Claude Code's auto-mode classifier intentionally blocks agents from installing auto-execute hooks. Hooks run shell commands without your explicit approval per-call, which is a security boundary. You must opt in once at the OS shell level.

## What's already in place (committed in the plugin repo)

- `wp-content/plugins/werbeauf-customs/.claude/hooks/codegraph-sync-on-edit.sh` — the hook script
- `wp-content/plugins/werbeauf-customs/.claude/settings.example.json` — template settings.json with the hook wired up

## Two manual commands you need to run

### 1. Make the hook script executable

```bash
chmod +x /Users/macos/WEB/Clients/drperi/wp-content/plugins/werbeauf-customs/.claude/hooks/codegraph-sync-on-edit.sh
```

### 2. Install the project-level `settings.json`

Claude Code reads `.claude/settings.json` from the directory it's opened in. Your typical workflow opens Claude at `/Users/macos/WEB/Clients/drperi/` — so the settings file lives there.

```bash
mkdir -p /Users/macos/WEB/Clients/drperi/.claude
cp /Users/macos/WEB/Clients/drperi/wp-content/plugins/werbeauf-customs/.claude/settings.example.json \
   /Users/macos/WEB/Clients/drperi/.claude/settings.json
```

The example file points at the absolute path of the hook script inside the plugin. If you ever move the plugin, update the `command` field in `settings.json`.

## Verify the hook fires

Restart Claude Code (it loads settings.json on session start). Then make any small edit to a PHP file in the plugin and watch:

```bash
tail -f /Users/macos/WEB/Clients/drperi/wp-content/plugins/werbeauf-customs/.claude/sync.log
```

Within ~5 seconds you should see a `=== <timestamp> trigger=<file> ===` line and codegraph sync output.

## How to disable

Either:

- Delete `/Users/macos/WEB/Clients/drperi/.claude/settings.json`, OR
- Edit it and remove the `PostToolUse` array

Hook stops firing immediately on next session.

## Troubleshooting

- **Nothing happens after edits.** Confirm the script is executable (`ls -la <hook script>`). Confirm settings.json is at the level Claude Code is opened in (NOT global `~/.claude/`).
- **Sync runs but graph stays stale.** Check `.claude/sync.log` for errors. Possibly the npx command can't find a recent codegraph install — try `npx -y @colbymchenry/codegraph status` once manually.
- **Performance hit from the hook.** Shouldn't happen — sync runs in detached background. If it does: check the debounce (`.claude/.last-sync` file) is being written. If not, the script can't write to `.claude/` (permissions).

## What the hook does NOT do

- It does **not** sync GitNexus. Run `npx gitnexus analyze` manually after big refactors.
- It does **not** fire for non-PHP files (CSS, JS, MD edits don't trigger).
- It does **not** fire for edits outside this plugin directory.
- It does **not** block Claude Code — sync runs in detached background.
