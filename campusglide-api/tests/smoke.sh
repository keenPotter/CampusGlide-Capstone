#!/usr/bin/env bash
# Usage: BASE=http://127.0.0.1:8000 EMAIL=admin@x.com PASSWORD=secret bash tests/smoke.sh
# Kailangan: curl + jq. Tinitingnan lang ang wiring ng API (hindi gumagawa ng data).
BASE=${BASE:-http://127.0.0.1:8000}
H=(-H "Accept: application/json")

echo "1) login"
RES=$(curl -s "${H[@]}" -H "Content-Type: application/json" -X POST "$BASE/api/login" \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")
TOKEN=$(echo "$RES" | jq -r '.token // .access_token // empty')
echo "   role: $(echo "$RES" | jq -r '.role // .user.role // "?"')"
[ -z "$TOKEN" ] && { echo "   FAILED: $RES"; exit 1; }
A=(-H "Authorization: Bearer $TOKEN" "${H[@]}")

echo "2) no token -> dapat 401"
curl -s -o /dev/null -w "   %{http_code}\n" "${H[@]}" "$BASE/api/allocations"

echo "3) list -> dapat 200"
curl -s -o /dev/null -w "   %{http_code}\n" "${A[@]}" "$BASE/api/allocations?per_page=100"

echo "4) options -> dapat 200 (admin) / 403 (end user)"
OPT=$(curl -s "${A[@]}" "$BASE/api/allocation-options")
echo "$OPT" | jq '{requests: (.requests|length), vehicles: (.vehicles|length), drivers: (.drivers|length)}'

RID=$(echo "$OPT" | jq -r '.requests[0].id // empty')
if [ -n "$RID" ]; then
  OPT2=$(curl -s "${A[@]}" "$BASE/api/allocation-options?vehicle_request_id=$RID")
  VID=$(echo "$OPT2" | jq -r '.vehicles[0].id // empty'); DID=$(echo "$OPT2" | jq -r '.drivers[0].id // empty')
  if [ -n "$VID" ] && [ -n "$DID" ]; then
    echo "5) create (request $RID, vehicle $VID, driver $DID) -> dapat 201"
    curl -s -w "\n   %{http_code}\n" "${A[@]}" -H "Content-Type: application/json" -X POST "$BASE/api/allocations" \
      -d "{\"vehicle_request_id\":$RID,\"vehicle_id\":$VID,\"driver_id\":$DID,\"notes\":\"smoke test\"}" | tail -n1
    echo "6) ulitin -> dapat 422"
    curl -s -o /dev/null -w "   %{http_code}\n" "${A[@]}" -H "Content-Type: application/json" -X POST "$BASE/api/allocations" \
      -d "{\"vehicle_request_id\":$RID,\"vehicle_id\":$VID,\"driver_id\":$DID}"
  else echo "5) walang libreng vehicle/driver para sa request na ito"; fi
else echo "5) walang approved request na naghihintay — gumawa muna ng test data"; fi
