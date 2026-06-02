# Curso Preparatório para o ENEM — Suindá

Banco de questões do ENEM dentro do Suindá, organizado pela Matriz de Referência
(áreas → disciplinas → conteúdos → competências → habilidades) e integrado à
repetição espaçada existente. Esta versão importa o **lote-piloto ENEM 2024 —
2º dia — Caderno 5 (Amarelo)** (questões 91–180) e deixa uma esteira reutilizável
para novas provas.

> Complementa `docs/suinda-integration.md` (integração do Suindá ao ecossistema).

---

## 1. Arquitetura encontrada (reaproveitada)

- **Backend** PHP/PDO em [suinda/api/src/App.php](../suinda/api/src/App.php)
  (SQLite por padrão; MySQL opcional), roteamento por `if`-chain, auth por token
  Bearer, gating de baralhos por matrícula (`allowedDeckIds`).
- **SRS** existente: `decks`, `cards` (com `card_type`, `question_html`,
  `answer_html`, `image_data`), `card_progress`, `study_*`.
- **Camada educacional** já criada: `knowledge_areas`, `courses`, `enrollments`,
  `course_decks`, `course_modules`. O curso ENEM se encaixa nela.

Cada **questão = um `card`** (`card_type = 'enem'`) em um **baralho por
disciplina** (`ENEM · Física`, `ENEM · Química`, …) vinculado ao curso via
`course_decks`. A questão existe **uma única vez** (`exam_questions`), com o card
referenciado por `exam_questions.card_id` — sem cópias.

---

## 2. Estrutura do curso

```
Curso "Preparatório para o ENEM" (courses.slug = preparatorio-enem)
 ├─ Módulo por área (course_modules): Linguagens, Matemática, Ciências da Natureza, Ciências Humanas, Redação
 ├─ Baralhos (decks, category 'ENEM') por disciplina ──< course_decks >── curso
 │    ENEM · Física (16) · ENEM · Química (11) · ENEM · Biologia (18) · ENEM · Matemática (45)
 └─ Banco de questões (exam_questions) com vínculos pedagógicos e card no SRS
```

Áreas (knowledge_areas, slug `enem-*`), disciplinas e a Matriz são semeadas pelo
importador a partir de [api/tools/enem/matriz.php](../suinda/api/tools/enem/matriz.php).

---

## 3. Tabelas e migrations

Criadas de forma **aditiva e idempotente** em `App::migrateEnem()` (SQLite e
MySQL — `enemSqliteSchema()` / `enemMysqlSchema()`), executadas no boot:

| Grupo | Tabelas |
| --- | --- |
| Taxonomia | `cognitive_axes`, `disciplines`, `contents`, `competencies`, `skills` |
| Provas | `exams`, `exam_questions`, `question_images`, `question_alternatives` |
| Vínculos | `question_contents`, `question_competencies`, `question_skills`, `question_cognitive_axes` |
| Histórico | `question_attempts` |

`exam_questions` referencia/armazena: prova, número oficial, área, disciplina,
conteúdo principal, competência/habilidade/eixo principais, alternativa correta,
`status` (`ativa`/`anulada`/`pendente_revisao`/`revisada`), `statement_text`
(transcrição), `explanation` + `explanation_status`, `confidence`
(`alta`/`media`/`baixa`), `review_needed`, `pdf_page`, `card_id`, `imported_at`.

Índices em `exam_questions(exam_id, discipline_id, card_id)` e
`question_attempts(user_id, question_id)`. Unicidade `(exam_id, number)` evita
duplicação.

---

## 4. Fluxo de importação (esteira reutilizável)

`api/tools/import-enem.php` (CLI) + classe `api/tools/enem/EnemImporter.php`:

1. roda `migrate()` (garante o schema);
2. **semeia a Matriz** (`matriz.php`) — idempotente;
3. garante **curso + módulos por área + baralhos por disciplina + course_decks**;
4. lê o **batch** normalizado (`.php` ou `.json`) e, por questão, faz upsert de
   `exam_questions`, `question_alternatives`, vínculos e o **card**;
5. **imagens**: gera os recortes oficiais se houver `pdftoppm` + `cwebp`/`convert`
   e `--prova`; caso contrário marca como **pendente** (a transcrição serve de
   visual provisório) — sem quebrar nada;
6. aplica **comentários revisados** (`explanations-*.php`, opcional);
7. emite **manifesto + relatório JSON + CSV + lista de revisão**.

Arquivos do piloto:
- matriz: `api/tools/enem/matriz.php`
- batch: `api/tools/enem/batch-2024-d2-c5.php` (90 questões transcritas + gabarito)
- explicações: `api/tools/enem/explanations-2024-d2-c5.php` (18 revisadas)

### Padrão de nomes das imagens
`enem-2024-dia2-caderno5-amarelo-q091.webp` (base em `exam.image_basename` +
`-q%03d`). Múltiplas imagens por questão são suportadas via `question_images.position`.

### Armazenamento dos recortes
Diretório público `suinda/assets/enem/questions/` (URL `/suinda/assets/enem/questions/…`).
Os `.webp` são **gerados** e **não versionados** (ver `suinda/.gitignore`):
gere-os numa máquina com ferramentas e sincronize no deploy. Relatórios ficam em
`api/storage/enem/reports/` (fora do versionamento e do acesso web).

---

## 5. Tratamento de questões anuladas (Q102)

Quando o gabarito indica anulada: `status = 'anulada'`, `correct_alternative = NULL`,
`review_needed = 1`. No estudo: **fica fora das sessões normais** (filtro padrão
`status=ativa`), aparece num **arquivo** (`?status=anulada`), exibe **aviso** no
verso e **não conta acerto/erro** (o endpoint `/answer` não registra tentativa).
Validado com a Q102.

---

## 6. Classificação pedagógica e confiança

Cada questão recebe **área + disciplina** (alta confiança), **conteúdo principal**,
**competência** e **habilidade** quando há segurança; o restante fica
`confidence='baixa'` e `review_needed=1`. A relação é **muitos-para-muitos**
(`question_*`), com papel `principal`/`secundario`. Nada é classificado com falsa
certeza — itens ambíguos/interdisciplinares (ex.: Q101 grafeno, Q98 ZnS) são
marcados para revisão. Distribuição do piloto: confiança alta 53, média 36,
baixa 1; 36 questões marcadas para revisão.

Relatório (uma linha por questão) em JSON e CSV, mais `*-revisao.json` com os
itens que exigem revisão manual.

---

## 7. Filtros e banco de questões

Endpoint `GET /enem/questions` aceita: `discipline` (slug), `content` (id),
`competency` (code), `skill` (code), `exam` (slug), `status`
(`ativa`/`anulada`/`todas`), `filter` (`novas`, `nao_estudadas`, `vencidas`,
`erradas`), `random`, `limit`. A navegação por área/disciplina/conteúdo/
competência/habilidade/status/pendentes/erradas/não-estudadas/vencidas é coberta.
*(Favoritas ficam como próximo passo — exige tabela de favoritos.)*

A página do curso (`/suinda/curso-enem/`) monta os filtros a partir de
`GET /enem/taxonomy`.

---

## 8. Integração com a repetição espaçada

Cada questão é um card real no baralho da disciplina. No estudo dedicado
(`/suinda/curso-enem/estudar/`): a pessoa **responde A–E** → "Confirmar resposta"
(registra `question_attempts`) → vê **resultado + gabarito + explicação** → avalia
a dificuldade nos **mesmos botões do Suindá** (Errei / Difícil / Fácil / Muito
fácil), que gravam em **`card_progress`** (a tabela do SRS). Assim o app de
repetição espaçada e o painel veem o mesmo progresso. O agendamento usa uma
política SM-2-lite (dias) no cliente. Os baralhos `ENEM · …` também funcionam no
app de cards padrão.

Endpoints: `GET /enem/overview`, `GET /enem/taxonomy`, `GET /enem/questions`,
`GET /enem/questions/{id}` (frente, **sem vazar o gabarito**),
`POST /enem/questions/{id}/answer` (registra tentativa e devolve gabarito +
explicação), e o `PUT /cards/{id}/progress` existente para a nota SRS. Todos
**exigem matrícula** no curso (gating no backend; 401/403 caso contrário).

---

## 9. Importar novas provas

```bash
php suinda/api/tools/import-enem.php \
  --ano=2025 --dia=2 --caderno=5 --cor=amarelo \
  --curso=preparatorio-enem \
  --matriz=tools/enem/matriz.php \
  --batch=tools/enem/batch-2025-d2-c5.php \
  [--prova=/caminho/prova.pdf --render] \
  [--explanations=tools/enem/explanations-2025-d2-c5.php] \
  [--enroll-demo]
```

Para uma nova prova: crie um `batch-<id>.php` (mesmo formato do piloto) e,
opcionalmente, `explanations-<id>.php`. Com `pdftoppm`+`cwebp` instalados e
`--render --prova=…`, os recortes oficiais são gerados e preenchem
`question_images` (substituindo a transcrição provisória) — **sem retrabalho**.

---

## 10. Revisão manual

- Lista priorizada: `api/storage/enem/reports/<slug>-last-revisao.json`.
- Ajuste a classificação/anulação editando o `batch-*.php` e **re-rodando** o
  importador (idempotente).
- Comentários: edite `explanations-*.php` (status `revisada`) e re-rode.
- Recorte impreciso: gere/edite o `.webp` em `assets/enem/questions/` (o nome seja
  `…-qNNN.webp`); o importador o associa na próxima execução.

---

## 11. Testar localmente

```bash
php suinda/api/tools/seed.php                       # usuários demo + base
php suinda/api/tools/import-enem.php --enroll-demo  # importa o piloto e matricula o aluno
php -S 127.0.0.1:8013 -t suinda/api suinda/api/router.php   # API
php -S 127.0.0.1:8080 -t .                          # site → http://127.0.0.1:8080/suinda/curso-enem/
bash suinda/api/tests/smoke-enem.sh                 # 19 verificações
```
Demo: `aluno@suinda.com` / `123456` (matriculado pelo `--enroll-demo`).

---

## 12. Cuidados para deploy

- Schema e dados são criados por código/CLI (nada de `*.sql` em runtime — regra
  do deploy mantém SQLite por padrão; ver `docs/suinda-integration.md`).
- **Imagens** (`assets/enem/questions/*.webp`) são geradas e **não vão pelo Git**;
  sincronize-as separadamente para o servidor quando existirem.
- Rode o importador no servidor (ou importe num banco e replique). Em produção,
  matricule estudantes via `/suinda/admin` (não use `--enroll-demo`).
- A pasta `api/storage/` precisa ser gravável.

---

## 13. Limitações desta versão

- **Sem recortes oficiais de imagem**: o ambiente não tinha `pdftoppm`/`cwebp`
  (não instaláveis sem sudo) nem o binário da prova 2024 acessível; as questões
  usam **transcrição** como visual provisório. A esteira já preenche os `.webp`
  quando as ferramentas/PDF existirem.
- **Explicações**: 18 revisadas (amostra de validação + alta confiança); 72
  `pendente` (revisão humana) — nunca inventadas.
- **Matriz**: competências das 4 áreas + habilidades completas de CN e MT
  (usadas pelo piloto). Habilidades de LC e CH = próximo passo (estrutura pronta).
- **Favoritas** e área administrativa de questões: próximos passos.

---

## 14. Próximos passos

1. Gerar os recortes oficiais (instalar `poppler-utils`+`webp`, rodar com
   `--render --prova=…`) e validar visualmente.
2. Completar habilidades de Linguagens e Ciências Humanas na matriz.
3. Revisar as 72 explicações pendentes e a classificação de baixa confiança.
4. Importar novas provas (D1, anos anteriores) reutilizando a esteira.
5. Favoritos, simulados cronometrados e relatórios por competência/habilidade.
