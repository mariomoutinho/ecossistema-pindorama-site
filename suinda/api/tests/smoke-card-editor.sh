#!/usr/bin/env bash
# ============================================================================
# Smoke test do editor de cards estilo Anki: frente/verso HTML, sanitização,
# upload de mídia com metadados (url/mime/width/height/size/usageType), e a
# renderização para o estudante (frontHtml/backHtml). Gating reaproveitado.
#   bash suinda/api/tests/smoke-card-editor.sh
# ============================================================================
set -u
API_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${SUINDA_TEST_PORT:-8362}"
BASE="http://127.0.0.1:${PORT}"
DB="$(mktemp -u)_ce.sqlite"
IMGDIR="$(mktemp -d)"
export SUINDA_DB_DRIVER=sqlite SUINDA_SQLITE_PATH="$DB" SUINDA_ENEM_IMAGE_DIR="$IMGDIR" SUINDA_ENEM_IMAGE_URL="/suinda/assets/enem/questions"

pass=0; fail=0
check() { if printf '%s' "$3" | grep -q -- "$2"; then echo "  ok  - $1"; pass=$((pass+1)); else echo "  FAIL- $1 (esperado '$2', veio '$3')"; fail=$((fail+1)); fi; }
jget() { python3 -c "import sys,json;d=json.load(sys.stdin);print($1)" 2>/dev/null; }
tok() { curl -s -X POST "$BASE/auth/login" -H 'Content-Type: application/json' -d "$1" | jget "d.get('token','')"; }

php "$API_DIR/tools/seed.php" >/dev/null 2>&1
php "$API_DIR/tools/import-enem.php" --enroll-demo >/dev/null 2>&1
php -r '$im=imagecreatetruecolor(120,40);imagepng($im,"/tmp/_ce_valid.png");'

php -S "127.0.0.1:${PORT}" "$API_DIR/router.php" >/dev/null 2>&1 &
SVR=$!
trap 'kill "$SVR" 2>/dev/null; rm -f "$DB" /tmp/_ce_valid.png; rm -rf "$IMGDIR"' EXIT
sleep 1.2

AT=$(tok '{"email":"admin@suinda.com","password":"admin123"}')
ST=$(tok '{"email":"aluno@suinda.com","password":"123456"}')
A="Authorization: Bearer $AT"

QID=$(curl -s "$BASE/admin/questions?discipline=quimica&limit=200" -H "$A" | jget "[q['id'] for q in d['questions'] if q['number']==92][0]")

# 1. upload de mídia com usageType=front retorna metadados completos
UP=$(curl -s -X POST "$BASE/admin/questions/$QID/images" -F "file=@/tmp/_ce_valid.png" -F "usageType=front" -H "$A")
check "upload front: retorna url" "/suinda/assets/enem/questions/" "$(printf '%s' "$UP" | jget "d['url']")"
check "upload front: mimeType image/png" "image/png" "$(printf '%s' "$UP" | jget "d['mimeType']")"
check "upload front: width 120" "120" "$(printf '%s' "$UP" | jget "d['width']")"
check "upload front: usageType=front" "front" "$(printf '%s' "$UP" | jget "d['usageType']")"
check "upload front NÃO vira principal" "False" "$(printf '%s' "$UP" | jget "d['isPrimary']")"
IMGURL=$(printf '%s' "$UP" | jget "d['url']")

# 2. salvar frente/verso HTML com sanitização (script removido; img interna mantida)
FRONT="<p>Enunciado <strong>rico</strong>.</p><script>alert(1)</script><img src=\"$IMGURL\"><img src=\"https://evil.example/x.png\">"
BACK="<p><strong>Resposta correta:</strong> C) Microfilamentos.</p><p onclick=\"x()\">Comentário sanitizado.</p>"
PUT=$(curl -s -X PUT "$BASE/admin/questions/$QID" -H "$A" -H 'Content-Type: application/json' \
  -d "$(python3 -c "import json,sys;print(json.dumps({'frontHtml':'''$FRONT''','backHtml':'''$BACK''','correctAlternative':'C','status':'revisada'}))")")
check "PUT frente/verso 200 (ok)" "True" "$(printf '%s' "$PUT" | jget "str(d.get('ok'))")"

SHOW=$(curl -s "$BASE/admin/questions/$QID" -H "$A")
check "frontHtml salvo mantém <strong>" "<strong>rico" "$(printf '%s' "$SHOW" | jget "d['question']['frontHtml']")"
check "sanitização remove <script>" "True" "$(printf '%s' "$SHOW" | jget "str('script' not in (d['question']['frontHtml'] or '').lower())")"
check "img interna preservada na frente" "True" "$(printf '%s' "$SHOW" | jget "str('$IMGURL' in (d['question']['frontHtml'] or ''))")"
check "img externa removida da frente" "True" "$(printf '%s' "$SHOW" | jget "str('evil.example' not in (d['question']['frontHtml'] or ''))")"
check "onclick removido do verso" "True" "$(printf '%s' "$SHOW" | jget "str('onclick' not in (d['question']['backHtml'] or '').lower())")"
check "verso mantém Resposta correta" "Resposta correta" "$(printf '%s' "$SHOW" | jget "d['question']['backHtml']")"

# 3. estudante recebe frontHtml na frente e não fica 'imagem pendente'
SHOWQ=$(curl -s "$BASE/enem/questions/$QID" -H "Authorization: Bearer $ST")
check "estudante recebe frontHtml" "Enunciado" "$(printf '%s' "$SHOWQ" | jget "d['question']['frontHtml']")"
check "estudante: imagePending=false (frente rica)" "False" "$(printf '%s' "$SHOWQ" | jget "d['question']['imagePending']")"

# 4. estudante recebe backHtml ao responder
ANS=$(curl -s -X POST "$BASE/enem/questions/$QID/answer" -H "Authorization: Bearer $ST" -H 'Content-Type: application/json' -d '{"selected":"C"}')
check "estudante recebe backHtml no /answer" "Comentário sanitizado" "$(printf '%s' "$ANS" | jget "d['backHtml']")"
check "gabarito oficial preservado (C)" "C" "$(printf '%s' "$ANS" | jget "d['correct']")"

# 5. verso vira explicação derivada: questão SEM comentário sai do filtro
QID_NC=$(curl -s "$BASE/admin/questions?filter=sem_comentario&limit=500" -H "$A" | jget "d['questions'][0]['id']")
BEFORE=$(curl -s "$BASE/admin/questions?filter=sem_comentario&limit=500" -H "$A" | jget "d['count']")
curl -s -X PUT "$BASE/admin/questions/$QID_NC" -H "$A" -H 'Content-Type: application/json' \
  -d '{"backHtml":"<p>Explicacao derivada do verso.</p>"}' >/dev/null
AFTER=$(curl -s "$BASE/admin/questions?filter=sem_comentario&limit=500" -H "$A" | jget "d['count']")
check "verso preenche comentário (count -1)" "1" "$(python3 -c "print(1 if $BEFORE-$AFTER==1 else 0)")"

# 6. troca da alternativa correta não apaga o verso (persistência do conteúdo)
PUT2=$(curl -s -X PUT "$BASE/admin/questions/$QID" -H "$A" -H 'Content-Type: application/json' -d '{"correctAlternative":"D"}')
check "PUT só do gabarito mantém verso" "Comentário sanitizado" "$(curl -s "$BASE/admin/questions/$QID" -H "$A" | jget "d['question']['backHtml']")"

# 7. usuário comum não envia mídia
check "estudante NÃO faz upload (403)" "403" "$(curl -s -o /dev/null -w '%{http_code}' -X POST "$BASE/admin/questions/$QID/images" -F "file=@/tmp/_ce_valid.png" -H "Authorization: Bearer $ST")"

echo
echo "Resultado card-editor: $pass passou, $fail falhou"
[ "$fail" -eq 0 ]
