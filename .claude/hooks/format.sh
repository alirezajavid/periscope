#!/usr/bin/env bash
# PostToolUse hook: format the file Claude just edited and surface lint errors.
# Exit 2 + stderr = message fed back to Claude so it fixes the problem.
set -uo pipefail

root="${CLAUDE_PROJECT_DIR:-$(pwd)}"
file="$(jq -r '.tool_input.file_path // empty' 2>/dev/null)"
[[ -z "$file" || ! -f "$file" ]] && exit 0

rel="${file#"$root"/}"

case "$rel" in
  api/*.php)
    pint="$root/api/vendor/bin/pint"
    [[ -x "$pint" ]] || exit 0
    (cd "$root/api" && "$pint" -q "$file") || { echo "pint failed on $rel" >&2; exit 2; }
    ;;
  app/*.js|app/*.vue|admin/*.js|admin/*.vue|shared/*.js|shared/*.vue)
    # shared/ is linted with app/'s toolchain
    pkg="${rel%%/*}"; [[ "$pkg" == shared ]] && pkg=app
    dir="$root/$pkg"
    [[ -x "$dir/node_modules/.bin/eslint" ]] || exit 0
    (cd "$dir" && ./node_modules/.bin/prettier --write --log-level warn "$file" >/dev/null 2>&1)
    out="$(cd "$dir" && ./node_modules/.bin/eslint --fix "$file" 2>&1)" || { echo "$out" >&2; exit 2; }
    ;;
esac
exit 0
