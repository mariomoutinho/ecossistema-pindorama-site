#!/usr/bin/env bash
# ============================================================================
# Smoke test da API educacional do Suinda (SQLite efemero).
# Valida: login, gating por matricula, dashboard, bloqueio 403, acesso admin
# e gravacao de progresso. Nao depende de servico externo.
#
#   bash suinda/api/tests/smoke.sh
# ============================================================================
set -u
API_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${SUINDA_TEST_PORT:-8099}"
BASE="http://127.0.0.1:${PORT}"
DB="$(mktemp -u)_suinda_test.sqlite"

export SUINDA_DB_DRIVER=sqlite
export SUINDA_SQLITE_PATH="$DB"

pass=0; fail=0
check() { # check "label" "expected_substring" "actual"
  if printf '%s' "$3" | grep -q -- "$2"; then
    echo "  ok  - $1"; pass=$((pass+1))
  else
    echo "  FAIL- $1"; echo "        esperado conter: $2"; echo "        recebido: $3"; fail=$((fail+1))
  fi
}
json() { # json "<body>" "<py expression on data>"
  printf '%s' "$1" | python3 -c "import sys,json;d=json.load(sys.stdin);print($2)" 2>/dev/null
}

echo "→ Semeando banco de teste ($DB)"
php "$API_DIR/tools/seed.php" >/dev/null

echo "→ Subindo servidor em $BASE"
( cd "$API_DIR" && php -S "127.0.0.1:${PORT}" router.php >/dev/null 2>&1 ) &
SVR=$!
trap 'kill $SVR 2>/dev/null; rm -f "$DB"' EXIT
sleep 1.2

echo "→ Testes"
# 1. login invalido
R=$(curl -s -o /dev/null -w '%{http_code}' -X POST "$BASE/auth/login" -H 'Content-Type: application/json' -d '{"email":"aluno@suinda.com","password":"errado"}')
check "login invalido retorna 401" "401" "$R"

# 2. login valido (aluno)
LOGIN=$(curl -s -X POST "$BASE/auth/login" -H 'Content-Type: application/json' -d '{"email":"aluno@suinda.com","password":"123456"}')
TOKEN=$(json "$LOGIN" "d['token']")
check "login valido devolve token" "." "$TOKEN"

# 3. /decks como aluno: ve apenas o baralho liberado (Biologia)
DECKS=$(curl -s "$BASE/decks" -H "Authorization: Bearer $TOKEN")
NDECKS=$(json "$DECKS" "len(d['decks'])")
check "aluno enxerga exatamente 1 baralho liberado" "1" "$NDECKS"
check "baralho liberado e o de Biologia" "Biologia" "$DECKS"
check "aluno NAO ve baralho de Historia na lista" "1" "$NDECKS"

# 4. acesso direto a baralho nao liberado (Historia=2) e bloqueado no backend
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/decks/2/cards" -H "Authorization: Bearer $TOKEN")
check "GET /decks/2/cards (nao liberado) retorna 403" "403" "$CODE"

# 5. dashboard do aluno
DASH=$(curl -s "$BASE/me/dashboard" -H "Authorization: Bearer $TOKEN")
check "dashboard tem 1 curso matriculado" "1" "$(json "$DASH" "d['totals']['courses']")"
check "dashboard marca hasContent=true" "True" "$(json "$DASH" "d['hasContent']")"
check "dashboard traz o nome do estudante" "Aluno" "$DASH"

# 6. sem token -> 401
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/decks")
check "GET /decks sem token retorna 401" "401" "$CODE"

# 7. gravar progresso de um card do baralho liberado (card 1)
CODE=$(curl -s -o /dev/null -w '%{http_code}' -X PUT "$BASE/cards/1/progress" -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -d '{"state":"review","dueAt":"2030-01-01T00:00:00+00:00","easeFactor":2.5,"intervalDays":3,"repetitions":1,"lapses":0}')
check "PUT progresso de card liberado retorna 200" "200" "$CODE"

# 8. gravar progresso de card NAO liberado (card 4 = Historia) -> 403
CODE=$(curl -s -o /dev/null -w '%{http_code}' -X PUT "$BASE/cards/4/progress" -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -d '{"state":"review"}')
check "PUT progresso de card NAO liberado retorna 403" "403" "$CODE"

# 9. admin enxerga todos os baralhos
ALOGIN=$(curl -s -X POST "$BASE/auth/login" -H 'Content-Type: application/json' -d '{"email":"admin@suinda.com","password":"admin123"}')
ATOKEN=$(json "$ALOGIN" "d['token']")
ADECKS=$(curl -s "$BASE/decks" -H "Authorization: Bearer $ATOKEN")
NA=$(json "$ADECKS" "len(d['decks'])")
check "admin enxerga os 3 baralhos institucionais" "3" "$NA"

echo
echo "Resultado: $pass passou, $fail falhou"
[ "$fail" -eq 0 ]
