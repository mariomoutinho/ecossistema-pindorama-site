# Área restrita de terapeutas — guia de operação

Documentação objetiva do módulo de **autenticação, pacientes, evoluções e
integração com a agenda**. Stack: PHP procedural + storage em JSON
(`data/terapeutas/*.json`, fora do Git). Nenhum dado clínico vai para o
repositório nem para logs.

## Arquitetura (resumo)

- **Storage**: camada document-store em [lib/storage.php](lib/storage.php).
  Cada "tabela" é um arquivo JSON em `data/terapeutas/`. As tabelas usadas:
  `terapeutas`, `pacientes`, `evolucoes`, `agendamentos`, `lembretes`,
  `notificacoes`, `codigos_senha`.
- **Auth**: sessão PHP + `password_hash`/`password_verify` + CSRF
  ([lib/auth.php](lib/auth.php)). Senha sempre como hash.
- **Privacidade**: `pacientes` e `evolucoes` são escopados por `terapeuta_id`
  no backend (`pac_find_do_terapeuta`, filtros por dono). A agenda permanece
  compartilhada pela equipe (comportamento já existente, preservado).

> As condições de saúde e os medicamentos são guardados como arrays embutidos
> no documento do paciente (`condicoes[]`, `medicamentos[]`), coerentes com o
> storage document-store. O schema relacional sugerido na spec
> (`patient_health_conditions`, `patient_medications`) está mapeado nessas
> estruturas.

## Arquivos criados

| Arquivo | Função |
|---|---|
| `terapeutas/lib/env.php` | Lê variáveis de ambiente (+ `.env` DEV, + `config-mail.php`) |
| `terapeutas/lib/mailer.php` | Envio de e-mail (mail/smtp/log) + template do código |
| `terapeutas/lib/account.php` | Códigos de troca de senha (gerar, validar, expirar, rate-limit) |
| `terapeutas/lib/pacientes.php` | Domínio de pacientes (catálogo, idade, busca, ownership, validação) |
| `terapeutas/conta.php` | Página **Segurança da conta** (troca de senha por código) |
| `terapeutas/pacientes.php` | Lista + busca + filtro + paginação |
| `terapeutas/paciente-form.php` | Cadastro/edição da ficha (seções A–H) |
| `terapeutas/paciente.php` | Ficha detalhada + Histórico de atendimentos (evoluções) |
| `terapeutas/api/pacientes-busca.php` | Endpoint JSON do autocomplete (agenda) |
| `terapeutas/bin/provisionar-terapeuta.php` | Provisionamento idempotente via env |
| `terapeutas/config-mail.example.php` | Template de config de e-mail (copiar p/ `config-mail.php`) |
| `terapeutas/tests/run.php` | Suíte de testes automatizados (CLI) |
| `.env.example` | Nomes das variáveis de ambiente (sem valores) |

## Arquivos alterados

- `terapeutas/bootstrap.php` — carrega as novas libs e cria as tabelas novas.
- `terapeutas/lib/auth.php` — `must_change_password` + enforcement no acesso.
- `terapeutas/login.php` — redireciona ao fluxo de troca no 1º acesso.
- `terapeutas/agenda.php` — campo **Paciente** com autocomplete + `paciente_id`.
- `terapeutas/evolucoes.php` — `status` (ativo/inativo) + herança de `paciente_id`.
- `terapeutas/index.php` — atalho **Pacientes**.
- `terapeutas/partials/header.php` — navegação **Pacientes** e **Segurança da conta**.
- `assets/css/terapeutas.css` — estilos de lista, ficha, formulário e autocomplete.
- `.gitignore` / `.github/workflows/deploy.yml` — protegem `config-mail.php`.

## Banco de dados / "migrations"

Não há migrations SQL: as tabelas JSON são criadas automaticamente em
`data/terapeutas/` na primeira requisição (ver `store_bootstrap` no bootstrap).
Não é preciso rodar nada. Os dados antigos de `agendamentos`/`evolucoes`
continuam válidos — `paciente_id` é opcional e ausente em registros antigos.

## Variáveis de ambiente

Definidas no ambiente do servidor (painel da hospedagem) **ou**, em DEV, num
arquivo `.env` na raiz (gitignored). Veja `.env.example`.

```
INITIAL_THERAPIST_NAME, INITIAL_THERAPIST_EMAIL, INITIAL_THERAPIST_PASSWORD
TERAP_MAIL_TRANSPORT (mail|smtp|log), TERAP_MAIL_FROM, TERAP_MAIL_FROM_NAME
TERAP_SMTP_HOST, TERAP_SMTP_PORT, TERAP_SMTP_USER, TERAP_SMTP_PASS,
TERAP_SMTP_SECURE (tls|ssl|none), TERAP_SMTP_EHLO
```

## Provisionar o terapeuta inicial (idempotente)

Na raiz do repositório, passando a senha **apenas** pelo ambiente do comando
(ela nunca é impressa nem versionada):

```bash
INITIAL_THERAPIST_NAME="Luiz Mario Barros Moutinho" \
INITIAL_THERAPIST_EMAIL="luizmariomoutinho1@gmail.com" \
INITIAL_THERAPIST_PASSWORD="<senha-inicial>" \
php terapeutas/bin/provisionar-terapeuta.php
```

- Idempotente: rodar de novo não duplica e não sobrescreve a senha existente.
- `--reset-password` redefine a senha de um terapeuta existente para o valor do
  ambiente e marca troca obrigatória.
- O terapeuta entra com a senha temporária e é levado a **Segurança da conta**
  para definir a senha definitiva via código por e-mail.

### Provisionar SEM terminal (Hostinger / File Manager)

Para ambientes sem SSH/CLI, use o provisionamento web (`provisionar.php`),
que fica **inerte** até existir um token:

1. No servidor, copie `terapeutas/config-provision.example.php` para
   `terapeutas/config-provision.php` (gitignored) e defina um token longo.
2. Acesse `https://SEU-DOMINIO/terapeutas/provisionar.php?token=SEU_TOKEN`.
3. Confirme nome/e-mail, digite a senha temporária e envie. A senha vira hash na
   hora — não é gravada em arquivo nem versionada.
4. **Apague** `config-provision.php` e `provisionar.php` do servidor.

O envio do código de troca de senha depende de e-mail funcionando no servidor
(`TERAP_MAIL_TRANSPORT=mail` usa o `mail()` da hospedagem; para SMTP dedicado,
use `config-mail.php`). Se o código não chegar, verifique spam ou configure SMTP.

## Configurar envio de e-mail

1. Copie `terapeutas/config-mail.example.php` para `terapeutas/config-mail.php`
   (gitignored) e ajuste, **ou** defina as variáveis `TERAP_*` no ambiente.
2. Produção simples: `TERAP_MAIL_TRANSPORT=mail` (usa `mail()`/sendmail).
3. SMTP dedicado: `TERAP_MAIL_TRANSPORT=smtp` + `TERAP_SMTP_*`.
4. DEV/testes: `TERAP_MAIL_TRANSPORT=log` — não envia; grava o e-mail em
   `data/terapeutas/_mail/*.eml` e a tela mostra o código (modo DEV).

## Executar os testes

```bash
php terapeutas/tests/run.php
```

Os testes usam um diretório de dados temporário e **não** tocam os dados reais.
Cobrem: autenticação, provisionamento idempotente, hash de senha, códigos de
troca (validade/uso único/tentativas/reenvio), CRUD e busca de pacientes,
cálculo de idade, isolamento entre terapeutas, evoluções e vínculo com
agendamentos.

## Perfis administrativos e gestão da equipe (admin/terapeuta)

A área tem dois perfis no campo `papel` do usuário: **`admin`** (Administrador)
e **`terapeuta`** (Terapeuta). A autorização é sempre no backend.

- **Fonte de verdade**: `lib/auth.php` (`auth_is_admin`, `auth_require_admin`,
  `auth_papeis`/`auth_papel_label`) e `lib/admin.php` (regras de negócio).
- **Tela**: `terapeutas/equipe.php` (rota acessível só a admins; item de menu
  **Gestão da equipe** aparece apenas para admins). Lista usuários (nome,
  e-mail, perfil, status, 1º acesso pendente, data) e permite cadastrar, editar
  dados, alterar perfil, ativar/desativar e redefinir a senha temporária.
- **Senha temporária**: todo usuário criado/redefinido recebe
  `must_change_password = true` e é obrigado a trocar a senha no 1º acesso
  (enforcement em `lib/auth.php`). A troca pode ser **direta** (senha atual +
  nova, sem e-mail — ver `account_trocar_senha_direta`) ou por **código de
  e-mail** (fluxo já existente). Ambas em `conta.php`.
- **Regras de segurança** (em `lib/admin.php`, cobertas por testes):
  - só admin cria contas e concede/remove o perfil admin;
  - um admin não desativa a própria conta enquanto autenticado;
  - o **último administrador ativo** não pode ser rebaixado nem desativado;
  - e-mail normalizado e único; senha só como hash.
- **Agenda** (`agenda.php`): admin vê e edita todos os agendamentos, atribui
  qualquer terapeuta e tem **filtro por terapeuta**; terapeuta gere só os seus
  (regras em `lib/agendamentos.php`, já existentes).
- **Auditoria mínima** (`lib/audit.php` → `data/terapeutas/auditoria.json`):
  registra usuário criado, perfil alterado, conta ativada/desativada e senha
  temporária redefinida — sem senhas, hashes ou códigos.

### Configurar o administrador inicial (Luiz) — idempotente

1. O **bootstrap** promove o e-mail configurado a `admin` **uma vez por
   ambiente** (marcador `data/terapeutas/.admin-inicial.done`), sem duplicar e
   sem mexer na senha. Default: `luizmariomoutinho1@gmail.com` / nome `Luiz`.
   Para outro e-mail, defina `INITIAL_ADMIN_EMAIL`/`INITIAL_ADMIN_NAME` no
   ambiente (ver `.env.example`).
2. Para definir a **senha inicial** de Luiz sem expor credenciais, use o
   provisionamento já existente (CLI `bin/provisionar-terapeuta.php` com
   `INITIAL_THERAPIST_*`, ou o `provisionar.php` web). A senha vira hash na hora
   — nunca é impressa nem versionada. O e-mail no seed/bootstrap **não** é
   segredo; a senha real nunca está no Git.

## Fluxo de troca de senha por código (resumo)

1. **Segurança da conta → Enviar código**: gera código de 6 dígitos, guarda
   apenas o hash, validade de 10 min, invalida códigos anteriores.
2. **Definir nova senha**: valida código (máx. 5 tentativas), troca a senha
   (hash), marca o código como usado e limpa `must_change_password`.
3. Mensagens são genéricas (não revelam se o e-mail existe). Há intervalo
   mínimo de 60s entre solicitações.
