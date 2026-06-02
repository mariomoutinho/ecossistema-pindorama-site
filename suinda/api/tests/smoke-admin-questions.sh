#!/usr/bin/env bash
# ============================================================================
# Smoke test do banco de questões admin: relatório, edição, upload de imagem
# (validação MIME por conteúdo), gestão de imagem e gating.
#   bash suinda/api/tests/smoke-admin-questions.sh
# ============================================================================
set -u
API_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${SUINDA_TEST_PORT:-8361}"
BASE="http://127.0.0.1:${PORT}"
DB="$(mktemp -u)_aq.sqlite"
IMGDIR="$(mktemp -d)"
export SUINDA_DB_DRIVER=sqlite SUINDA_SQLITE_PATH="$DB" SUINDA_ENEM_IMAGE_DIR="$IMGDIR" SUINDA_ENEM_IMAGE_URL="/suinda/assets/enem/questions"

pass=0; fail=0
check() { if printf '%s' "$3" | grep -q -- "$2"; then echo "  ok  - $1"; pass=$((pass+1)); else echo "  FAIL- $1 (esperado '$2', veio '$3')"; fail=$((fail+1)); fi; }
jget() { python3 -c "import sys,json;d=json.load(sys.stdin);print($1)" 2>/dev/null; }
tok() { curl -s -X POST "$BASE/auth/login" -H 'Content-Type: application/json' -d "$1" | jget "d.get('token','')"; }

php "$API_DIR/tools/seed.php" >/dev/null 2>&1
php "$API_DIR/tools/import-enem.php" --enroll-demo >/dev/null 2>&1
php -r '$im=imagecreatetruecolor(100,30);imagepng($im,"/tmp/_aq_valid.png");'
echo "nao e imagem" > /tmp/_aq_fake.png

php -S "127.0.0.1:${PORT}" "$API_DIR/router.php" >/dev/null 2>&1 &
SVR=$!
trap 'kill "$SVR" 2>/dev/null; rm -f "$DB" /tmp/_aq_valid.png /tmp/_aq_fake.png; rm -rf "$IMGDIR"' EXIT
sleep 1.2

AT=$(tok '{"email":"admin@suinda.com","password":"admin123"}')
ST=$(tok '{"email":"aluno@suinda.com","password":"123456"}')
A="Authorization: Bearer $AT"

REP=$(curl -s "$BASE/admin/questions" -H "$A")
check "relatório: 90 questões" "90" "$(printf '%s' "$REP" | jget "d['summary']['total']")"
check "relatório: 90 sem imagem" "90" "$(printf '%s' "$REP" | jget "d['summary']['semImagem']")"
check "relatório: 1 anulada" "1" "$(printf '%s' "$REP" | jget "d['summary']['anuladas']")"
check "filtro sem_comentario retorna 72" "72" "$(curl -s "$BASE/admin/questions?filter=sem_comentario&limit=500" -H "$A" | jget "d['count']")"

QID=$(curl -s "$BASE/admin/questions?discipline=quimica&limit=200" -H "$A" | jget "[q['id'] for q in d['questions'] if q['number']==92][0]")
check "GET questão: 5 alternativas" "5" "$(curl -s "$BASE/admin/questions/$QID" -H "$A" | jget "len(d['question']['alternatives'])")"

UP=$(curl -s -X POST "$BASE/admin/questions/$QID/images" -F "file=@/tmp/_aq_valid.png" -H "$A")
check "upload PNG válido cria imagem principal" "True" "$(printf '%s' "$UP" | jget "d['isPrimary']")"
IMGID=$(printf '%s' "$UP" | jget "d['id']")
check "arquivo gravado no disco" "." "$(ls "$IMGDIR" 2>/dev/null | head -1)"
check "upload de arquivo inválido é 422" "422" "$(curl -s -o /dev/null -w '%{http_code}' -X POST "$BASE/admin/questions/$QID/images" -F "file=@/tmp/_aq_fake.png" -H "$A")"
check "estudante passa a ver a imagem (imagePending=false)" "False" "$(curl -s "$BASE/enem/questions/$QID" -H "Authorization: Bearer $ST" | jget "d['question']['imagePending']")"

check "editar questão (status=revisada) 200" "200" "$(curl -s -o /dev/null -w '%{http_code}' -X PUT "$BASE/admin/questions/$QID" -H "$A" -H 'Content-Type: application/json' -d '{"explanation":"Comentário admin.","status":"revisada","reviewNeeded":false}')"
check "edição reflete no /answer do estudante" "revisada" "$(curl -s -X POST "$BASE/enem/questions/$QID/answer" -H "Authorization: Bearer $ST" -H 'Content-Type: application/json' -d '{"selected":"A"}' | jget "d['explanationStatus']")"

check "alt_text/position via PUT imagem 200" "ok" "$(curl -s -X PUT "$BASE/admin/question-images/$IMGID" -H "$A" -H 'Content-Type: application/json' -d '{"altText":"gráfico"}' | jget "'ok' if d.get('ok') else 'no'")"
check "excluir imagem 200" "ok" "$(curl -s -X DELETE "$BASE/admin/question-images/$IMGID" -H "$A" | jget "'ok' if d.get('ok') else 'no'")"
check "após excluir, volta a imagePending=true" "True" "$(curl -s "$BASE/enem/questions/$QID" -H "Authorization: Bearer $ST" | jget "d['question']['imagePending']")"

check "estudante NÃO acessa /admin/questions (403)" "403" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/admin/questions" -H "Authorization: Bearer $ST")"
check "estudante NÃO faz upload (403)" "403" "$(curl -s -o /dev/null -w '%{http_code}' -X POST "$BASE/admin/questions/$QID/images" -F "file=@/tmp/_aq_valid.png" -H "Authorization: Bearer $ST")"

echo
echo "Resultado admin-questions: $pass passou, $fail falhou"
[ "$fail" -eq 0 ]
