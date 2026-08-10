#!/bin/sh
#
# Launches the MCP server, finding node first.
#
# Claude Code spawns this without a login shell, so a node installed by nvm is
# not on PATH: nvm puts it there from a shell profile, and there is no profile
# here. The symptom is an MCP server that reports itself disconnected with no
# further explanation, which is a long way from the cause.
#
# Pointing the configuration straight at an nvm path would work until the next
# node upgrade, since that path carries the version number. This resolves it at
# launch instead.

set -e

# Where node lands when it comes from a package manager rather than nvm.
PATH="/opt/homebrew/bin:/usr/local/bin:$PATH"

if ! command -v node >/dev/null 2>&1; then
  nvm_dir="${NVM_DIR:-$HOME/.nvm}"
  nvm_bin=""

  # Honour the version nvm's default alias points at, rather than picking the
  # newest installed one — those are not always the same, and the default is
  # what the shell would have used.
  if [ -r "$nvm_dir/alias/default" ]; then
    wanted="$(cat "$nvm_dir/alias/default")"
    nvm_bin="$(ls -d "$nvm_dir/versions/node/v${wanted#v}"*/bin 2>/dev/null | sort -V | tail -n 1)"
  fi

  if [ -z "$nvm_bin" ]; then
    nvm_bin="$(ls -d "$nvm_dir"/versions/node/*/bin 2>/dev/null | sort -V | tail -n 1)"
  fi

  [ -n "$nvm_bin" ] && PATH="$nvm_bin:$PATH"
fi

export PATH

if ! command -v node >/dev/null 2>&1; then
  echo "tsutsumbi-mcp: no node on PATH, and none found under \${NVM_DIR:-\$HOME/.nvm}." >&2
  exit 1
fi

exec node "$(cd "$(dirname "$0")" && pwd)/server.js" "$@"
