# Suindá — Correções do fluxo de estudo e área administrativa

Correções incrementais sobre o curso ENEM já existente. Complementa
`docs/suinda-enem-course.md` e `docs/suinda-integration.md`. Entregue em dois
incrementos pequenos e testáveis, sem refatoração ampla e preservando o SRS.

## 1. Diagnóstico

- **Frontend**: páginas PHP em `suinda/` (vitrine, login, painel, `curso-enem/`,
  `admin/`) + app SRS em `suinda/app/`. Card de estudo ENEM:
  [suinda/curso-enem/estudar/index.php](../suinda/curso-enem/estudar/index.php).
- **Backend**: `suinda/api/src/App.php` (PHP/PDO, SQLite padrão), endpoints
  `if`-chain, gating por matrícula.
- **Causa do bug "a pergunta some"**: em `renderResult()`, o card era
  **reconstruído exibindo só o número da questão** — a imagem/enunciado/
  alternativas eram descartadas. Não havia troca de componente; era a
  reconstrução do HTML sem o visual.
- **Repetição espaçada**: `schedule(prev, rating)` no cliente grava em
  `card_progress` (mesma tabela do app); o backend agenda por `due_at`.
- **Tabelas**: `exam_questions`, `question_alternatives`, `question_images`,
  `card_progress`, `question_attempts` já existiam.
- **Papéis**: `student` / `admin` (`requireAdmin` no backend).
- **Imagens/PDF**: a importação anterior **não gerou recortes** (sem
  `pdftoppm`/`cwebp` e sem o PDF acessível); usava transcrição provisória.
- **Upload**: não havia. Adicionado nesta entrega (validação no backend).

## 2. Incremento 1 — fluxo de estudo

### Causa e solução do desaparecimento
`renderResult()` agora **reexibe `questionVisual(q)`** (imagem oficial ou
transcrição) acima das alternativas marcadas — o card permanece visível e só
muda para o estado de correção.

### Estados do card
1. **Não respondida**: imagem/enunciado + alternativas A–E + **Responder**
   desabilitado até selecionar.
2. **Respondida**: mesma questão + faixa de acerto/erro + alternativas marcadas
   (correta destacada; sua resposta errada destacada) + seção expansível
   **Comentário da resposta** (sua resposta, gabarito, explicação,
   disciplina/conteúdo/competência/habilidade) + botões SRS.
3. **Avaliação**: salva progresso + próxima revisão + confirmação + avança.
4. **Fim**: resumo (respondidas, acertos, erros, anuladas, revisões, %).

### Repetição espaçada com intervalo
`schedule()` passou a usar **minutos** para intervalos curtos. Cada botão mostra
o intervalo calculado (`formatInterval`): "em 10 min", "amanhã", "em 4 dias",
"em 2 semanas", "em 1 mês", data completa quando longo. Ao escolher:
**"Próxima revisão programada para: …"**. Campos salvos em `card_progress`:
`state`, `dueAt`, `easeFactor`, `intervalDays`, `intervalMinutes`,
`repetitions`, `lapses`, `introducedAt`, `lastRating`. Cards de **"Errei"**
reaparecem na mesma sessão (re-enfileirados). Algoritmo preservado (SM-2-lite).

Imagens **amplia­veis em modal** (clique/zoom) e botão **"Ver questão completa"**
quando há múltiplas imagens.

## 3. Incremento 2 — área administrativa e imagens

### Permissões
Tudo sob `requireAdmin` no backend (não só esconder no front). Estudante recebe
**403**; sem token, **401**.

### Editor de questões — `/suinda/admin/questoes/`
Lista + **relatório de importação** (total, sem imagem, sem comentário, pendente
revisão, anuladas, sem classificação) com filtros clicáveis. Editor por questão:
status (`ativa/anulada/pendente_revisao/revisada/arquivada`), gabarito,
confiança, revisão pendente, **disciplina/conteúdo/competência/habilidade**,
enunciado, alternativas A–E (texto + correta), comentário, **observação interna**,
e **"Visualizar como estudante"** (abre o card real). Anulada zera o gabarito.

### Gestão de imagens (fallback manual obrigatório)
Upload, **definir principal**, remover, texto alternativo, ordem. Validação no
backend: **MIME pelo conteúdo real** (`getimagesize`) — só `jpg/png/webp` —,
**limite 5 MB**, nome aleatório seguro, remoção física restrita ao diretório
público. Assim que uma imagem existe, o estudante a vê (a transcrição deixa de
ser exibida). A edição de comentário/gabarito **sincroniza** o verso do card.

### Migrations (aditivas, SQLite+MySQL)
Em `migrateEnem()` (via `addColumnIfMissing`): `question_images.is_primary`,
`alt_text`, `mime_type`, `crop_x/y/width/height`, `updated_at`. **Nenhum dado
apagado.**

### Endpoints novos (admin)
`GET /admin/questions` (lista + filtros + `summary`), `GET /admin/questions/{id}`,
`PUT /admin/questions/{id}`, `POST /admin/questions/{id}/images` (multipart),
`PUT /admin/question-images/{id}` (principal/alt/ordem),
`DELETE /admin/question-images/{id}`.

### Estudo (já existentes, ajustados)
`GET /enem/questions/{id}` (frente, sem vazar gabarito — agora usa as imagens),
`POST /enem/questions/{id}/answer` (devolve gabarito + explicação + statements),
`PUT /cards/{id}/progress` (nota SRS).

## 4. Imagens / processamento de PDF

A **estratégia principal continua sendo renderizar a página e recortar a questão**
(esteira em `tools/import-enem.php`), que preenche `question_images` quando há
`pdftoppm`+`cwebp` e o PDF. **Bloqueio atual**: o ambiente não tem essas
ferramentas (não instaláveis sem sudo) nem o binário da prova acessível —
portanto os recortes automáticos seguem **pendentes**. O **upload manual** desta
entrega é o caminho funcional imediato para colocar a imagem oficial no card.

Reprocessar quando houver ambiente:
```bash
php suinda/api/tools/import-enem.php --batch=tools/enem/batch-2024-d2-c5.php \
  --prova=/caminho/2024_PV_impresso_D2_CD5.pdf --render
```
O importador é **idempotente** e **não sobrescreve** uploads manuais (só
preenche quem está sem `.webp` no disco com o nome esperado); divergências vão
para o relatório (`api/storage/enem/reports/`).

### Dependências
Nenhuma nova em runtime (usa `gd`, já presente). Para recortes automáticos:
`poppler-utils` (`pdftoppm`) + `webp` (`cwebp`) — opcionais.

### Armazenamento de imagens
Públicas em `suinda/assets/enem/questions/` (URL `/suinda/assets/enem/questions/`).
Os arquivos são **gerados** e **não versionados** (`.gitignore`); sincronize no
deploy. Caminho configurável por `SUINDA_ENEM_IMAGE_DIR`/`_URL`.

## 5. Testes executados

- `tests/smoke-admin-questions.sh`: **16/16** (relatório, GET, upload válido/
  inválido, imagem aparece p/ estudante, edição reflete na resposta, alt/ordem,
  excluir, gating 403/401).
- Regressão: `tests/smoke.sh` **25/25**, `tests/smoke-enem.sh` **19/19**.
- `curso-enem/estudar/index.php`: PHP `-l` ok; JS validado por lexer
  (delimitadores balanceados); contrato de dados do `/answer` conferido.

## 6. Limitações e próximos passos

- **Recorte automático de PDF**: bloqueado (ferramentas/binário) — upload manual
  é o caminho atual.
- Editor não cobre ainda CRUD de cards "livres" (não-ENEM), tabelas
  `exam_imports`/`exam_import_items`, recorte por coordenadas na UI nem
  "favoritas" — são próximos passos. O modelo já suporta `crop_x/y/width/height`.
- Próximos: gerar/validar recortes oficiais; editor de cards genéricos; tela de
  conflito de reprocessamento; revisar as 72 explicações pendentes.
