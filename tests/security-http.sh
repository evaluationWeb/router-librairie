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
PAYLOAD="$(mktemp)"
php -S "127.0.0.1:$PORT" "$ROOT/tests/Fixtures/security-http-server.php" >"$LOG" 2>&1 &
SERVER_PID=$!
cleanup() {
  kill "$SERVER_PID" 2>/dev/null || true
  wait "$SERVER_PID" 2>/dev/null || true
  rm -f "$LOG" "$BODY" "$PAYLOAD"
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

# Keep encoded values literal: curl --path-as-is does not normalize dot segments.
# The router must neither mistake encoded prefixes for protected canonical paths
# nor allow the dynamic parameter to bypass grants.
check 404 GET '/%61dmin/42'
check 404 GET '/%2561dmin/42'
check 404 GET '/admin//42'
check 403 GET '/admin/%2F'
check 403 GET '/admin/%252F'
check 405 OPTIONS /admin/42
check 403 POST '/submit?role=ROLE_ADMIN'

# Structured and moderately large request bodies must not grant access.
actual="$(curl --silent --show-error --max-time 5 -o "$BODY" -w '%{http_code}' \
  -H 'Content-Type: application/json' --data-binary '{"role":["ROLE_ADMIN"],"csrf_token":{"unexpected":true}}' "$BASE/submit")"
[[ "$actual" == "403" ]] || { echo "Structured POST must be forbidden, got $actual" >&2; exit 1; }
php -r 'echo str_repeat("x", 65536);' >"$PAYLOAD"
actual="$(curl --silent --show-error --max-time 5 -o "$BODY" -w '%{http_code}' \
  -H 'Content-Type: application/octet-stream' --data-binary "@$PAYLOAD" "$BASE/submit")"
[[ "$actual" == "403" ]] || { echo "Large POST must be forbidden, got $actual" >&2; exit 1; }

# Real multipart upload: even a suspicious client-supplied filename is only
# metadata. The trusted fixture generates its own destination outside the webroot.
printf '%s' 'security-upload-payload' >"$PAYLOAD"
actual="$(curl --silent --show-error --max-time 5 -o "$BODY" -w '%{http_code}' \
  -F "cover=@$PAYLOAD;filename=../../payload.php;type=application/x-php" "$BASE/upload-check")"
[[ "$actual" == "200" ]] || { echo "Multipart upload request returned $actual" >&2; cat "$LOG" >&2; exit 1; }
php -r '
  $v = json_decode(file_get_contents($argv[1]), true, 32, JSON_THROW_ON_ERROR);
  if (($v["present"] ?? null) !== true
      || ($v["validBeforeMove"] ?? null) !== true
      || ($v["validAfterMove"] ?? null) !== false
      || ($v["contentPreserved"] ?? null) !== true
      || ($v["secondMoveRejected"] ?? null) !== true) {
      fwrite(STDERR, "Unexpected upload result: " . json_encode($v) . PHP_EOL);
      exit(1);
  }
' "$BODY"
printf 'PASS multipart upload metadata and move boundary\n'
actual="$(curl --silent --show-error --max-time 5 -o "$BODY" -w '%{http_code}' \
  -X POST "$BASE/upload-check")"
[[ "$actual" == "200" ]] || { echo "Missing-file request returned $actual" >&2; exit 1; }
php -r '$v=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR); if ($v !== ["present"=>false]) exit(1);' "$BODY"

echo 'HTTP security edge-case suite: OK'
