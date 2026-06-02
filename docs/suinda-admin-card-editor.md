# Suindá — Editor de cards estilo Anki (frente/verso, Ctrl+V)

Evolução incremental do banco de questões ENEM. Complementa
`docs/suinda-admin-study-fixes.md` e `docs/suinda-enem-course.md`. Preserva todo
o comportamento anterior (nada de card/comentário/imagem/SRS removido) e
adiciona uma experiência de edição mais produtiva.

## 1. Diagnóstico

- **Tela admin**: [suinda/admin/questoes/index.php](../suinda/admin/questoes/index.php).
  Antes: `textarea` simples para *enunciado* e *comentário/explicação* (altura
  pequena, `resize: vertical`), bloco de alternativas, e uma área de imagens com
  upload manual/definir principal/alt/excluir.
- **Campos de texto**: `textarea` puros. **Imagens**: enviadas por `<input file>`
  → `FormData` → `POST /admin/questions/{id}/images`, armazenadas em
  `suinda/assets/enem/questions/` (config `enem_image_dir`/`_url`), validadas no
  backend por conteúdo real (`getimagesize`).
- **Modelo**: `exam_questions` (enunciado em `statement_text`, explicação em
  `explanation`, `correct_alternative`, `status`, classificação),
  `question_alternatives`, `question_images`, `cards` (`question_html`/
  `answer_html` para o app genérico), `card_progress` (SRS), `question_attempts`.
- **Frente/verso do estudante** ([curso-enem/estudar/index.php](../suinda/curso-enem/estudar/index.php)):
  a "frente" era imagens (`question_images`) **ou** a transcrição
  (`statement_text`); o "verso" (após responder) era a faixa de gabarito +
  `explanation` (texto). O **comentário já funcionava como verso** — não era um
  campo isolado do fluxo do aluno.
- **Permissões**: todas as rotas `/admin/*` passam por `requireAdmin` (403 para
  estudante, 401 sem token). Mantido.

## 2. Frente e verso separados

O editor agora tem **dois cartões destacados**, com editores de conteúdo rico
(`contenteditable`):

- **Frente do cartão — enunciado da questão** (`#f_front`): enunciado,
  transcrição, textos de apoio, imagens inline, tabelas.
- **Verso do cartão — resposta e explicação** (`#f_back`): semeado com
  `Resposta correta: X) …` + `Comentário:` + explicação; aceita resolução,
  dicas e imagens complementares.

**Semente sem migração destrutiva:** ao abrir, se `front_html`/`back_html`
estiverem nulos, o editor monta o conteúdo inicial a partir do que já existe
(`statement_text` + imagens na frente; `correct_alternative` + `explanation` no
verso). O HTML só é **persistido quando o admin salva** — nada é reescrito no
banco antes disso.

**Sincronização da alternativa correta:** ao trocar o rádio da correta, a linha
`Resposta correta: X) …` do verso é atualizada **somente se ainda estiver no
formato automático**; se foi editada manualmente (ou não existe), mostra um
**aviso discreto** em vez de sobrescrever. Comentário, imagens e resolução
digitados à mão são preservados.

## 3. Colar imagem com Ctrl + V

Handler de `paste` nos dois editores:

1. inspeciona `clipboardData.items`;
2. se houver item `image/*` (`png/jpeg/webp/gif`), `getAsFile()`;
3. insere um *placeholder* "⏳ enviando imagem…" na posição do cursor;
4. envia via `FormData` a `POST /admin/questions/{id}/images` com
   `usageType=front|back`;
5. troca o placeholder pela `<img>` real (URL retornada) ao concluir;
6. em falha, remove o placeholder e mostra mensagem.

Colagem de **texto** segue o comportamento normal (o handler só intercepta
quando há imagem). **Nunca** grava `base64` no banco/HTML — sempre URL de mídia.

## 4. Upload tradicional, arrastar-soltar, anexos

- Botão **🖼 Imagem** em cada editor e **＋ Adicionar imagem** (anexo) usam um
  `<input type=file>` único, com alvo definido por `pickerTarget`/`pickerUsage`.
- **Arrastar e soltar** imagem dentro de um editor → mesmo fluxo de upload+inline.
- **Imagens anexadas à questão**: miniaturas com ampliação (zoom), definir
  principal, texto alternativo, excluir e **indicação de uso** (frente / verso /
  complementar / *não inserida no conteúdo* — detectado pela presença da URL no
  HTML). Excluir imagem em uso pede **confirmação reforçada**.

## 5. Editor visual e auto-crescimento

- `contenteditable` controlado + barra simples: **negrito, itálico, lista,
  adicionar imagem, limpar formatação, desfazer, refazer** (`document.execCommand`,
  leve, sem dependência nova).
- **Sem rolagem interna**: `.rte { min-height: 140px; height: auto;
  overflow-y: visible }`. O `contenteditable` cresce naturalmente com o conteúdo;
  a `textarea` de observação interna usa auto-expansão (`scrollHeight`). A página
  rola normalmente; a lista lateral mantém rolagem própria.
- Imagens nos editores e no estudante: `max-width: 100%; height: auto`.

## 6. Ordem da tela e ações

Ordem: identificação (prova·Q + *Visualizar como estudante*) → status/confiança/
revisão → disciplina/conteúdo/competência/habilidade → alternativas (correta) →
**Frente** → **Verso** → observação interna → imagens anexadas → ações.

Ações: **Salvar alterações**, **Salvar e abrir próxima →**, **Arquivar questão**
(status=arquivada, nada apagado), **Visualizar como estudante**, **＋ Adicionar
imagem**, e um indicador **● Alterações não salvas / ✓ Tudo salvo** (com aviso de
`beforeunload` e confirmação ao trocar de questão com pendências).

## 7. Backend / endpoints

- `POST /admin/questions/{id}/images` (multipart) — **agora** aceita campo
  `usageType` (`front|back|attachment`, padrão `attachment`) e responde com
  `{ id, url, path, mimeType, width, height, size, usageType, isPrimary }`. Validação
  inalterada (conteúdo real, ≤ 5 MB, jpg/png/webp, nome aleatório seguro, escrita
  restrita ao diretório público). Imagens inline (front/back) **não** viram
  "principal" automaticamente.
- `PUT /admin/questions/{id}` — aceita `frontHtml`/`backHtml` (sanitizados),
  deriva `statement_text`/`explanation` do texto (mantém busca e o filtro "sem
  comentário"), e sincroniza `cards.question_html`/`answer_html`. **Correção:**
  PUTs parciais (sem `status`) deixaram de quebrar (era um `NOT NULL`
  violation latente).
- `PUT /admin/question-images/{id}` — passa a aceitar `usageType`.
- `GET /admin/questions/{id}` — devolve `frontHtml`, `backHtml` e, por imagem,
  `usageType`, `width`, `height`, `mimeType`.
- Estudante: `GET /enem/questions/{id}` devolve `frontHtml` (e `imagePending`
  considera a frente rica); `POST /enem/questions/{id}/answer` devolve `backHtml`.

**Formatos**: JPG, PNG, WEBP (e GIF aceito na colagem, convertido para registro
de mídia padrão; validação MIME real). **Limite**: 5 MB. **Armazenamento**:
arquivos em `suinda/assets/enem/questions/` (gerados, **não versionados**),
apenas a URL vai ao banco.

## 8. Sanitização

`sanitizeCardHtml()` no backend (aplicada em todo save de frente/verso): remove
`script/style/iframe/object/embed`, handlers `on*`, atributos `style/class/id/
data-*` e `javascript:`/`vbscript:`/`data:text/html`. Mantém apenas tags
seguras (`p,br,hr,strong,b,em,i,u,span,div,ul,ol,li,sup,sub,h3-h5,blockquote,a,
img,figure,figcaption,table…`). Em `<img>`, aceita **somente URLs internas**
(`/suinda/…`, `/assets/…`) — nada de mídia externa nem base64.

## 9. Estrutura de dados (aditiva e reversível)

`migrateEnem()` (idempotente, SQLite + MySQL) adiciona:
`exam_questions.front_html`, `exam_questions.back_html`,
`question_images.usage_type` (`front|back|attachment`, default `attachment`),
`question_images.size_bytes`. Nenhuma coluna existente é alterada; **nenhum dado
é apagado**. Reverter = ignorar/remover as colunas.

## 10. Migração dos conteúdos antigos

Sem script destrutivo: enquanto `front_html`/`back_html` são nulos, o estudante
e o admin usam o **fallback legado** (imagens + transcrição na frente; explicação
no verso). A separação só materializa quando o admin abre a questão (semente) e
salva. A alternativa correta é incluída no início do verso quando ainda não
estiver presente. Imagens existentes são preservadas; mídias não são duplicadas.

## 11. Testes executados

- **Novo**: `tests/smoke-card-editor.sh` — **19/19** (upload com metadados e
  `usageType`; save de frente/verso; sanitização remove `<script>`/`onclick`;
  `<img>` interna preservada e externa removida; estudante recebe `frontHtml`/
  `backHtml`; `imagePending=false` com frente rica; verso preenche o filtro "sem
  comentário"; PUT parcial preserva o verso; gating 403).
- **Regressão**: `smoke.sh` 25/25, `smoke-enem.sh` 19/19,
  `smoke-admin-questions.sh` 16/16. PHP `-l` limpo; JS validado por lexer
  (delimitadores balanceados).
- Checklist da spec (1–30) coberto via os smokes acima + verificação manual do
  fluxo (frente visível após responder, SRS intacto, imagens responsivas).

## 12. Limitações

- O editor rico usa `document.execCommand` (depreciado, mas estável e sem
  dependência) — fórmulas continuam dependendo do suporte já existente no
  projeto (texto/HTML); não há editor de LaTeX dedicado.
- Reordenar imagens é por `position` (sem *drag-and-drop* de reordenação na
  grade ainda).
- A prévia "antes/depois de responder" usa o botão **Visualizar como estudante**
  (abre o card real em nova aba), não um painel embutido lado a lado.
- Recorte automático de PDF segue pendente (ambiente sem `pdftoppm`/`cwebp`).

## 13. Próximos passos

- Painel de prévia embutido (estados: antes / acerto / erro) sem sair da tela.
- Reordenação visual de anexos; inserir anexo existente no cursor.
- Editor de cards genéricos (não-ENEM) reaproveitando os mesmos componentes.
- Sincronizar o espelho standalone (`/home/mario/suinda/__remote_suinda_app`) se
  ainda for desejado mantê-lo em paridade.
