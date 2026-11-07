#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
command -v php >/dev/null || { echo "php is required" >&2; exit 1; }
command -v curl >/dev/null || { echo "curl is required" >&2; exit 1; }
test -f "$ROOT/vendor/autoload.php" || { echo "Run composer install first." >&2; exit 1; }

# Reserve a currently unused loopback port, then release it before spawning PHP.
PORT="$(php -r '$s = stream_socket_server("tcp://127.0.0.1:0", $errno, $errstr); if (!$s) exit(1); echo (int) substr(strrchr(stream_socket_get_name($s, false), ":"), 1); fclose($s);')"
BASE="http://127.0.0.1:$PORT"
LOG="$(mktemp)"
BODY="$(mktemp)"
php -S "127.0.0.1:$PORT" "$ROOT/tests/Fixtures/security-http-server.php" >"$LOG" 2>&1 &
SERVER_PID=$!
cleanup() {
  kill "$SERVER_PID" 2>/dev/null || true
  wait "$SERVER_PID" 2>/dev/null || true
  rm -f "$LOG" "$BODY"
}
trap cleanup EXIT

READY=0
for _ in $(seq 1 50); do
  if ! kill -0 "$SERVER_PID" 2>/dev/null; then
    cat "$LOG" >&2
    echo "PHP server exited unexpectedly" >&2
    exit 1
  fi
  if curl --silent --output /dev/null --max-time 1 "$BASE/health"; then
    READY=1
    break
  fi
  sleep 0.1
done
if [[ "$READY" -ne 1 ]]; then
  cat "$LOG" >&2
  echo "PHP server did not become ready" >&2
  exit 1
fi

check() {
  local expected="$1" method="$2" path="$3" actual
  actual="$(curl --silent --show-error --path-as-is --max-time 5 -o "$BODY" -w '%{http_code}' -X "$method" "$BASE$path")"
  if [[ "$actual" != "$expected" ]]; then
    printf 'FAILED: %s %s expected %s got %s (body: %s)\n' "$method" "$path" "$expected" "$actual" "$(cat "$BODY")" >&2
    cat "$LOG" >&2
    exit 1
  fi
  if [[ "$expected" == "403" ]] && grep -q 'secret-' "$BODY"; then
    echo "Protected content leaked: $method $path" >&2
    exit 1
  fi
  printf 'PASS %s %s -> %s\n' "$method" "$path" "$actual"
}

check 200 GET /health
check 403 GET /admin/42
check 403 GET '/admin/42?redirect=%2Fhealth'
check 403 POST /submit
check 405 POST /health
check 405 GET /submit
check 404 GET /administrator/42
check 404 GET /admin/42/extra

echo 'HTTP security baseline: OK'
