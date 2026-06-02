#!/usr/bin/env bash
# ============================================================================
# Smoke test do curso ENEM (SQLite efêmero): importação, gating por matrícula,
# filtros, resposta A–E, questão anulada e explicação.
#   bash suinda/api/tests/smoke-enem.sh
# ============================================================================
set -u
API_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${SUINDA_TEST_PORT:-8311}"
BASE="http://127.0.0.1:${PORT}"
DB="$(mktemp -u)_enem_test.sqlite"
export SUINDA_DB_DRIVER=sqlite SUINDA_SQLITE_PATH="$DB"

pass=0; fail=0
check() { if printf '%s' "$3" | grep -q -- "$2"; then echo "  ok  - $1"; pass=$((pass+1)); else echo "  FAIL- $1 (esperado '$2', veio '$3')"; fail=$((fail+1)); fi; }
jget() { python3 -c "import sys,json;d=json.load(sys.stdin);print($1)" 2>/dev/null; }
tok() { curl -s -X POST "$BASE/auth/login" -H 'Content-Type: application/json' -d "$1" | jget "d.get('token','')"; }

echo "→ Semeando + importando lote-piloto…"
php "$API_DIR/tools/seed.php" >/dev/null 2>&1
php "$API_DIR/tools/import-enem.php" --enroll-demo >/dev/null 2>&1

echo "→ Subindo servidor em $BASE"
php -S "127.0.0.1:${PORT}" "$API_DIR/router.php" >/dev/null 2>&1 &
SVR=$!
trap 'kill "$SVR" 2>/dev/null; rm -f "$DB"' EXIT
sleep 1.2

ST=$(tok '{"email":"aluno@suinda.com","password":"123456"}')
AUTH="Authorization: Bearer $ST"

echo "→ Testes"
OV=$(curl -s "$BASE/enem/overview" -H "$AUTH")
check "overview tem conteúdo" "true" "$(printf '%s' "$OV" | jget "str(d['hasContent']).lower()")"
check "89 questões ativas" "89" "$(printf '%s' "$OV" | jget "d['totals']['questions']")"
check "1 questão anulada (arquivo)" "1" "$(printf '%s' "$OV" | jget "d['totals']['annulled']")"

TX=$(curl -s "$BASE/enem/taxonomy" -H "$AUTH")
check "taxonomia: 30 competências" "30" "$(printf '%s' "$TX" | jget "len(d['competencies'])")"
check "taxonomia: 60 habilidades" "60" "$(printf '%s' "$TX" | jget "len(d['skills'])")"

check "filtro disciplina=fisica retorna questões" "[1-9]" "$(curl -s "$BASE/enem/questions?discipline=fisica" -H "$AUTH" | jget "d['count']")"
check "filtro habilidade=CN-H21" "[1-9]" "$(curl -s "$BASE/enem/questions?skill=CN-H21" -H "$AUTH" | jget "d['count']")"
check "status=anulada retorna a Q102" "102" "$(curl -s "$BASE/enem/questions?status=anulada" -H "$AUTH" | jget "[q['number'] for q in d['questions']]")"

Q92=$(curl -s "$BASE/enem/questions?discipline=quimica&limit=200" -H "$AUTH" | jget "[q['id'] for q in d['questions'] if q['number']==92][0]")
Q102=$(curl -s "$BASE/enem/questions?status=anulada" -H "$AUTH" | jget "[q['id'] for q in d['questions'] if q['number']==102][0]")
Q150=$(curl -s "$BASE/enem/questions?discipline=matematica&limit=200" -H "$AUTH" | jget "[q['id'] for q in d['questions'] if q['number']==150][0]")

SHOW=$(curl -s "$BASE/enem/questions/$Q92" -H "$AUTH")
check "frente da questão não vaza is_correct" "True" "$(printf '%s' "$SHOW" | jget "not any('isCorrect' in a for a in d['question']['alternatives'])")"
check "frente marca imagem pendente" "True" "$(printf '%s' "$SHOW" | jget "d['question']['imagePending']")"

check "responder Q92=A é correto" "True" "$(curl -s -X POST "$BASE/enem/questions/$Q92/answer" -H "$AUTH" -H 'Content-Type: application/json' -d '{"selected":"A"}' | jget "d['isCorrect']")"
check "responder Q92=B é incorreto" "False" "$(curl -s -X POST "$BASE/enem/questions/$Q92/answer" -H "$AUTH" -H 'Content-Type: application/json' -d '{"selected":"B"}' | jget "d['isCorrect']")"
ANN=$(curl -s -X POST "$BASE/enem/questions/$Q102/answer" -H "$AUTH" -H 'Content-Type: application/json' -d '{"selected":"C"}')
check "Q102 anulada: annulled=true" "True" "$(printf '%s' "$ANN" | jget "d['annulled']")"
check "Q102 anulada: isCorrect=null" "None" "$(printf '%s' "$ANN" | jget "d['isCorrect']")"
check "Q150 retorna explicação revisada" "revisada" "$(curl -s -X POST "$BASE/enem/questions/$Q150/answer" -H "$AUTH" -H 'Content-Type: application/json' -d '{"selected":"C"}' | jget "d['explanationStatus']")"
check "filtro erradas inclui Q92" "True" "$(curl -s "$BASE/enem/questions?filter=erradas" -H "$AUTH" | jget "92 in [q['number'] for q in d['questions']]")"

# Gating: estudante sem matrícula
AT=$(tok '{"email":"admin@suinda.com","password":"admin123"}')
curl -s -o /dev/null -X POST "$BASE/admin/users" -H "Authorization: Bearer $AT" -H 'Content-Type: application/json' -d '{"name":"Sem","email":"sem.enem@suinda.com","password":"senha123"}'
UT=$(tok '{"email":"sem.enem@suinda.com","password":"senha123"}')
check "não-matriculado não vê questões (0)" "0" "$(curl -s "$BASE/enem/questions" -H "Authorization: Bearer $UT" | jget "d['count']")"
check "não-matriculado recebe 403 na questão" "403" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/enem/questions/$Q92" -H "Authorization: Bearer $UT")"
check "sem token recebe 401" "401" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/enem/overview")"

echo
echo "Resultado ENEM: $pass passou, $fail falhou"
[ "$fail" -eq 0 ]
