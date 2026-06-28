#!/usr/bin/env bash
# test_api.sh — smoke-test the E-Shop API end to end.
# Usage:  bash test_api.sh  [BASE_URL]
# Default BASE_URL: http://localhost:8080/api
# Works in Git Bash / WSL / macOS / Linux. No jq required.

set -u
BASE="${1:-http://localhost:8080/api}"
pass=0; fail=0
say(){ printf '\n\033[1m%s\033[0m\n' "$1"; }
ok(){ printf '  \033[32mPASS\033[0m %s\n' "$1"; pass=$((pass+1)); }
no(){ printf '  \033[31mFAIL\033[0m %s\n' "$1"; fail=$((fail+1)); }

# extract a JSON string value without jq:  _get '"token"' <<<"$json"
field(){ grep -o "\"$1\":\"[^\"]*\"" | head -1 | sed "s/\"$1\":\"//;s/\"$//"; }

say "1) GET $BASE/products"
PRODUCTS=$(curl -s "$BASE/products")
echo "$PRODUCTS" | grep -q '"products"' && ok "products list returned" || no "no products field: $PRODUCTS"
PID=$(echo "$PRODUCTS" | grep -o '"id":[0-9]*' | head -1 | grep -o '[0-9]*')
echo "  first product id: ${PID:-<none — add a product in admin first>}"

say "2) POST $BASE/auth/login  (seeded admin)"
LOGIN=$(curl -s -X POST "$BASE/auth/login" -H "Content-Type: application/json" \
  -d '{"email_or_username":"admin@example.com","password":"admin123"}')
TOKEN=$(echo "$LOGIN" | field token)
if [ -n "$TOKEN" ]; then ok "got token (${TOKEN:0:18}...)"; else no "no token: $LOGIN"; fi

say "3) GET $BASE/me  (with token)"
ME=$(curl -s "$BASE/me" -H "Authorization: Bearer $TOKEN")
echo "$ME" | grep -q '"user"' && ok "me returned user" || no "me failed: $ME"

say "4) GET $BASE/me  (NO token -> expect 401)"
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/me")
[ "$CODE" = "401" ] && ok "unauthenticated correctly rejected (401)" || no "expected 401, got $CODE"

if [ -n "${PID:-}" ] && [ -n "$TOKEN" ]; then
  say "5) POST $BASE/orders  (product $PID x2)"
  ORDER=$(curl -s -X POST "$BASE/orders" -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
    -d "{\"shipping_address\":\"12 Test St, Hyderabad\",\"items\":[{\"product_id\":$PID,\"quantity\":2}]}")
  echo "$ORDER" | grep -q '"order_id"' && ok "order created: $ORDER" || no "order failed: $ORDER"
else
  say "5) SKIPPED order test (need at least one product + a token)"
fi

printf '\n\033[1mResult: %d passed, %d failed\033[0m\n' "$pass" "$fail"
[ "$fail" -eq 0 ]
