# Integração do Suindá ao ecossistema Pindorama

Documento técnico da integração do **Suindá** (braço educacional do Coletivo
Pindorama) ao site do ecossistema. Cobre arquitetura, rotas, autenticação,
modelo de dados, deploy e pontos de evolução.

> TL;DR — O Suindá vive em `https://www.coletivopindorama.com.br/suinda/`.
> A vitrine pública é `/suinda/`. A área autenticada (`/suinda/estudar`) libera
> baralhos de repetição espaçada **somente** para os cursos em que o estudante
> está matriculado. Toda a restrição é validada no backend.

---

## 1. Arquitetura encontrada (diagnóstico)

O workspace tinha **três** bases relacionadas ao Suindá, além do site:

| Caminho | O que é | Decisão |
| --- | --- | --- |
| `~/pindorama/` | Site do ecossistema (PHP/MySQL, Apache/Hostinger). **É o único que publica** (GitHub Actions → FTP → `public_html`). | Alvo da integração. |
| `~/suinda/__remote_suinda_app/` | App real de repetição espaçada (Capacitor): `www/` (frontend JS puro) + `backend/` (API PHP/PDO, SQLite/MySQL) + `android/`. Tem git próprio (`mariomoutinho/suinda-app`). | **Fonte de verdade**, tratado como **somente leitura**. Copiado para o site. |
| `~/suinda/` (raiz) | Site estático "Organizador de Estudos" (localStorage) + cópia simples já existente em `~/pindorama/suinda/`. | Preservado e religado à nova estrutura. |
| `~/suinda-backend/`, `~/suinda/app`, `~/suinda/suinda-backend` | Esqueletos PHP MVC praticamente vazios / `schema.sql` vazios. | Não utilizados (ver §12). |

Pontos-chave do app existente que **foram preservados**: baralhos, cards,
sub-baralhos por categoria, agendamento/scheduler de revisão, progresso por card,
contadores de novos/pendentes, importação `.apkg` (Anki), histórico de sessões,
estatísticas e responsividade. Hash de senha com `password_hash` e tokens Bearer
em `auth_tokens` já existiam.

A **lacuna** fechada por esta integração: os baralhos eram **globais** (qualquer
usuário autenticado via todos). Agora há uma camada de **matrícula** que filtra
o que cada estudante pode ver/estudar — validada no backend.

---

## 2. Diretórios envolvidos (no repositório que publica)

```
pindorama/
├─ coletivopindorama.php         # home: + card do Suindá na seção "Ecossistema"
├─ partials/
│  ├─ bootstrap.php              # + $suindaUrl = 'suinda/'
│  ├─ header.php                 # + item "Suindá" no menu e no drawer
│  └─ footer.php                 # + link "Suindá" no rodapé
├─ assets/css/home.css           # + .ecosystem-card--suinda (gradiente)
├─ docs/suinda-integration.md    # este documento
└─ suinda/                       # ← todo o Suindá vive aqui
   ├─ .htaccess                  # DirectoryIndex index.php index.html
   ├─ index.php                  # vitrine pública (10 seções, "Vamos estudar")
   ├─ login/index.php            # /suinda/login
   ├─ estudar/index.php          # /suinda/estudar (área autenticada)
   ├─ inc/{header,footer}.php     # casca compartilhada das páginas PHP
   ├─ assets/css/suinda-site.css # identidade visual (vitrine, login, painel)
   ├─ organizador.html           # ferramenta legada preservada (religada)
   ├─ app/                        # cópia de __remote_suinda_app/www (app SRS)
   │  ├─ js/api.js               # base da API derivada → /suinda/api
   │  ├─ js/suinda-shell.js      # + link "Painel do Suindá"
   │  └─ pages/login.html         # vira redirecionador → /suinda/login/
   └─ api/                        # cópia de __remote_suinda_app/backend (API)
      ├─ index.php                # front controller (Apache)
      ├─ .htaccess                # roteia tudo p/ index.php + Authorization
      ├─ router.php               # front controller p/ servidor PHP embutido
      ├─ bootstrap.php            # monta config (+ config.local.php) e App
      ├─ config.php               # padrão SQLite em api/storage/
      ├─ config.local.example.php # modelo p/ MySQL (copiar → config.local.php)
      ├─ src/App.php              # núcleo: + matrículas, gating, /me/dashboard
      ├─ database/
      │  ├─ schema.mysql.sql      # tabelas-base (referência/import manual)
      │  └─ schema.suinda.sql     # tabelas educacionais (referência/import manual)
      ├─ storage/                 # SQLite + dados de runtime (gitignored, .htaccess deny)
      ├─ tools/seed.php           # popula dados de demonstração
      └─ tests/smoke.sh           # teste de fumaça da API (13 checagens)
```

---

## 3. Rotas

### Públicas
| URL | Arquivo | Descrição |
| --- | --- | --- |
| `/suinda/` | `suinda/index.php` | Vitrine: apresentação, cursos, trilhas, dicas, "como funciona", CTA **Vamos estudar**. |
| `/suinda/login` | `suinda/login/index.php` | Login do estudante. |
| `/suinda/organizador.html` | legado | Organizador de estudos (localStorage), preservado. |

### Autenticadas (token no navegador; **dados protegidos no backend**)
| URL | Arquivo | Descrição |
| --- | --- | --- |
| `/suinda/estudar` | `suinda/estudar/index.php` | Painel: saudação, cursos matriculados, trilhas, progresso, novos/pendentes, acesso ao app. |
| `/suinda/admin` | `suinda/admin/index.php` | **Mini-CMS (somente admin)**: áreas, trilhas, cursos, módulos, vínculo curso↔baralho, criação de estudantes e matrículas. Link aparece no painel quando `role=admin`. |
| `/suinda/app/pages/*` | app SRS | Telas do app de repetição espaçada (baralhos, estudo, navegador, progresso). |

### API (`/suinda/api`)
Front controller com remoção automática do prefixo `/suinda/api` (ver §8).

---

## 4. Fluxo de autenticação

Padrão SPA com token Bearer (reaproveita o que o app já fazia):

1. `/suinda/login` envia `POST /suinda/api/auth/login` (e-mail + senha).
2. Backend valida com `password_verify`, cria token aleatório (`auth_tokens`) e
   devolve `{ token, user }`.
3. O login guarda no `localStorage`: `suinda_api_token` e `suinda_current_user`
   (mesmas chaves do app) e redireciona para `/suinda/estudar`.
4. Páginas privadas e o app enviam `Authorization: Bearer <token>` em cada
   requisição. Sem token → `/suinda/api` responde **401**; o front redireciona
   ao login.
5. **Logout**: limpa o `localStorage` e volta ao login.

> O gate das páginas é client-side (UX). A segurança real está nos endpoints:
> nenhum baralho/card é entregue sem token válido **e** matrícula correspondente.

Comportamento do botão **“Vamos estudar”**: sem sessão → `/suinda/login`; com
sessão → `/suinda/estudar`. Estudante autenticado **sem matrícula** entra no
painel e vê uma mensagem amigável (estado vazio), sem nenhum baralho.

---

## 5. Estrutura de matrículas e vínculo curso ↔ baralho

```
knowledge_areas ──< learning_paths ──< learning_path_courses >── courses
                                                                   │
                          course_modules >─────────────────────────┤
                                                                   │
        users ──< enrollments >── courses ──< course_decks >── decks ──< cards
                                                                   │
                                       card_progress (user_id, card_id) ─┘
```

- Uma **trilha** (`learning_paths`) agrupa vários **cursos** em ordem
  (`learning_path_courses.position`).
- Um **curso** pode liberar um ou mais **baralhos** (`course_decks`), opcionalmente
  associados a um **módulo** (`course_modules`).
- O estudante só enxerga baralhos vinculados a cursos com **matrícula ativa**
  (`enrollments.status = 'active'`).
- **Baralhos pessoais**: a coluna `decks.owner_id` preserva a criação de baralhos
  pelo próprio estudante (visíveis só para ele). Baralhos institucionais têm
  `owner_id` nulo e dependem da matrícula.

Regra de acesso (backend): `allowedDeckIds()` =
`decks de cursos com matrícula ativa` ∪ `decks com owner_id = usuário`.
Admin (`role = 'admin'`) enxerga tudo.

---

## 6. Migrations criadas

Criadas de forma **aditiva e idempotente** (`CREATE TABLE IF NOT EXISTS` +
`addColumnIfMissing`) em `App::migrate()` (SQLite) e `App::migrateMysql()`
(MySQL), executadas automaticamente no boot — **não apagam dados existentes**:

- `knowledge_areas`, `learning_paths`, `courses`, `learning_path_courses`,
  `course_modules`, `enrollments`, `course_decks`.
- Nova coluna `decks.owner_id` (nullable).
- Índices/chaves: PKs, `UNIQUE(slug)`, `UNIQUE(user_id, course_id)`,
  `UNIQUE(course_id, deck_id)`, FKs com `ON DELETE CASCADE/SET NULL`.

Referência SQL para import manual em MySQL: `api/database/schema.suinda.sql`
(as tabelas-base seguem em `api/database/schema.mysql.sql`). Lembrando que
arquivos `*.sql` **não** são publicados pelo deploy (ver §9), por isso o app
também cria tudo via código.

---

## 7. Endpoints

### Reutilizados (sem alteração de contrato, agora com gating quando aplicável)
`POST /auth/login` · `GET /me` · `GET /decks` · `GET /decks/{id}` ·
`GET /decks/{id}/cards` · `GET /cards/{id}` · `POST /decks` ·
`POST /decks/{id}/cards` · `PUT/DELETE /cards/{id}` · `POST /import` ·
`POST /import/apkg` · `GET /study/history` · `POST /study/sessions` ·
`GET /stats/today` · `GET /stats/daily` · `GET /cards/progress` ·
`PUT /cards/{id}/progress` · `POST /sync`.

Gating aplicado a: `GET /decks` (lista só os liberados), `GET /decks/{id}`,
`GET /decks/{id}/cards`, `GET /cards/{id}`, `PUT /cards/{id}/progress`
(403 se o card não estiver liberado).

### Adicionados
| Método | Rota | Resposta |
| --- | --- | --- |
| `GET` | `/me/dashboard` | `{ user, courses[], paths[], totals, hasContent }` — base do painel. |
| `GET` | `/me/courses` | Cursos matriculados com baralhos e progresso. |
| `GET` | `/me/paths` | Trilhas com ao menos um curso liberado. |

`courses[]` traz `progress { totalCards, studiedCards, newCards, dueCards, percent }`
e `decks[] { id, title, totalCards, newCards, dueCards, studiedCards }`.

### Administrativos (`/admin/*`, exigem `role=admin`)
| Método | Rota | Ação |
| --- | --- | --- |
| `GET` | `/admin/overview` | Estado completo (áreas, cursos, trilhas, módulos, baralhos, usuários, matrículas, vínculos). |
| `POST` | `/admin/areas` · `/admin/courses` · `/admin/paths` · `/admin/modules` | Cria a entidade (slug gerado e único). |
| `POST` | `/admin/users` | Cria estudante/admin (senha com `password_hash`). |
| `POST` | `/admin/path-courses` · `/admin/course-decks` · `/admin/enrollments` | Cria vínculos (idempotente). |
| `PUT` | `/admin/courses/{id}` | Edita/inativa um curso (`active=0` esconde os baralhos dos estudantes; slug mantido). |
| `POST` | `/admin/enrollments/bulk` | Matrícula em lote: `{courseId, defaultPassword?, students:[{name,email,password?}]}`; cria os ausentes e devolve `{created, enrolled, alreadyEnrolled, errors[]}`. |
| `DELETE` | `/admin/enrollments/{id}` · `/admin/course-decks/{id}` | Remove matrícula / vínculo curso-baralho. |

Esses endpoints existem tanto na cópia do site quanto no app standalone
(`__remote_suinda_app`), mantendo paridade.

---

## 8. Montagem da API e caminhos relativos

- **Prefixo `/suinda/api`**: `App::stripBasePath()` remove o prefixo derivado de
  `SCRIPT_NAME` (ou de `config['base_path']`/`SUINDA_BASE_PATH`). Assim a mesma
  rota `'/decks'` funciona tanto montada em `/suinda/api` (Apache) quanto na raiz
  (servidor PHP embutido com `router.php`).
- **`Authorization`**: o `.htaccess` repassa o header Bearer ao PHP
  (alguns ambientes Apache/CGI não o expõem por padrão).
- **Assets do app**: `app/js/api.js` deriva a base da API de
  `location.pathname` (`…/suinda` → `…/suinda/api`), funcionando mesmo se o site
  estiver em subpasta. Override manual: `localStorage.suinda_api_base_url`.
- **CSS/JS/imagens das páginas PHP** usam caminhos **root-relative**
  (`/suinda/...`), válidos porque o site é publicado na raiz do domínio.

---

## 9. Cuidados para o deploy (Hostinger via GitHub Actions/FTP)

- O deploy publica **todo** o repositório `pindorama/` em `public_html/`.
  O Suindá entra junto, em `public_html/suinda/`.
- **Excluídos do deploy** (`.github/workflows/deploy.yml`): `**/*.sql`,
  `**/.git*`, `**/node_modules`, `.claude/**`, `config-db.php`, `docs/**`
  (adicionado), entre outros. Por isso a API **não depende** de `.sql` em runtime.
- **Nunca versionar/deployar**: `api/config.local.php` (credenciais),
  `api/storage/*` (banco SQLite e dados), uploads/imports, logs — já cobertos por
  `suinda/.gitignore`.
- **Banco padrão = SQLite** em `api/storage/suinda.sqlite` (zero configuração;
  pasta protegida por `.htaccess`). É o caminho recomendado para subir rápido.
- **Para usar MySQL** no servidor: criar o banco no hPanel, importar
  `schema.mysql.sql` + `schema.suinda.sql` via phpMyAdmin e criar
  `api/config.local.php` a partir de `config.local.example.php`.
- A pasta `api/storage/` precisa ser **gravável** pelo PHP (permissão de escrita).

---

## 10. Como executar localmente

Requisitos: PHP 8+ com `pdo_sqlite` (e `zip` para importar `.apkg`).

```bash
# 1) Popular dados de demonstração (SQLite em api/storage/)
php suinda/api/tools/seed.php

# 2) Subir a API (porta 8013)
php -S 127.0.0.1:8013 -t suinda/api suinda/api/router.php

# 3) Subir o site (em outra aba), a partir da raiz do repositório
php -S 127.0.0.1:8080 -t .
# Acesse http://127.0.0.1:8080/suinda/
# Aponte o app para a API local no console do navegador:
#   localStorage.setItem('suinda_api_base_url','http://127.0.0.1:8013')
```

> Em produção isso não é necessário: `/suinda/api` é resolvido automaticamente.

### Seeds
`php suinda/api/tools/seed.php` cria (idempotente):
área **Fundamentos** → trilha **Trilha de Fundamentos** → curso **Biologia
Basica** (módulo 1) → baralho **Biologia** vinculado → **matrícula** do aluno.

Credenciais de demonstração:
- Aluno: `aluno@suinda.com` / `123456` (matriculado em Biologia Basica)
- Admin: `admin@suinda.com` / `admin123`

> **Não** suba `seed_on_boot=true` em produção, e troque/retire o usuário de
> demonstração antes de liberar contas reais.

### Primeiro administrador em produção (sem usar o usuário de demonstração)

Em vez do seed, crie um admin real com senha sua. O script é **CLI-only** (e a
pasta `tools/` é bloqueada para acesso web):

```bash
# Interativo (senha oculta, não fica no histórico):
php suinda/api/tools/create-admin.php

# Não-interativo:
SUINDA_ADMIN_NAME="Coordenação" SUINDA_ADMIN_EMAIL="voce@dominio" \
SUINDA_ADMIN_PASSWORD='senha-forte' SUINDA_DISABLE_DEMO=1 \
php suinda/api/tools/create-admin.php
```

O script roda as migrations (em SQLite cria tudo; em MySQL exige importar antes
`schema.mysql.sql` + `schema.suinda.sql`), cria/promove o admin com
`password_hash`, e — com `SUINDA_DISABLE_DEMO=1` ou confirmação interativa —
**desativa** `aluno@suinda.com`/`admin@suinda.com`. Depois, gerencie tudo em
`/suinda/admin`. Nunca passe a senha por argumento de linha de comando.

### Testes
```bash
bash suinda/api/tests/smoke.sh   # 13 verificações de login, gating e dashboard
```

---

## 11. Variáveis de ambiente

Todas opcionais (há padrões em `config.php`); podem também vir de `config.local.php`:

| Variável | Padrão | Uso |
| --- | --- | --- |
| `SUINDA_DB_DRIVER` | `sqlite` | `sqlite` ou `mysql`. |
| `SUINDA_SQLITE_PATH` | `api/storage/suinda.sqlite` | Caminho do arquivo SQLite. |
| `SUINDA_DB_HOST/PORT/NAME/USER/PASSWORD` | — | Conexão MySQL. |
| `SUINDA_BASE_PATH` | (auto) | Prefixo de montagem da API. |
| `SUINDA_SEED_ON_BOOT` | `false` | Semear no boot (apenas dev). |

---

## 12. Evolução futura (preparado, não construído)

- **Área administrativa/CMS**: já existe um **mini-CMS** em `/suinda/admin` para
  criar áreas, trilhas, cursos, módulos, vincular baralhos a cursos, criar
  estudantes e matricular. Evoluções possíveis: edição/inativação de itens,
  reordenação por drag-and-drop e busca/paginação de usuários.
- **Matrícula self-service / turmas / pré-requisitos** entre cursos da trilha.
- **Recuperação de senha** (a tela de login já tem o ponto preparado).
- **Conteúdo dos cursos**: textos, módulos e baralhos definitivos ainda serão
  produzidos — a vitrine usa textos provisórios e cards "Em breve".
- **Arte do card do Suindá** na home (hoje é um gradiente da identidade).
- **Progresso por trilha** e relatórios mais ricos.

## 13. Itens que dependem de conteúdo definitivo

- Cursos/trilhas reais (a demonstração usa Biologia + placeholders "Em breve").
- Materiais de estudo por módulo (`course_modules` já existe; falta conteúdo).
- Textos institucionais finais da vitrine.
