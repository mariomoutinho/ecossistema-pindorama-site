# Suindá

Aplicativo de estudos. Dois módulos:

- **Baralhos** (placeholder) — espaço reservado para o app de cartas/baralhos.
- **Organizador de Estudos** — cadastro de matérias, tarefas e agenda visual semanal/diária.

## Como rodar

Não há build. É HTML+CSS+JS estático. Basta abrir `index.html` no navegador, ou servir a pasta:

```bash
cd /home/mario/suinda
python3 -m http.server 8080
# então abra http://localhost:8080
```

## Estrutura

```
suinda/
├── index.html          # hub com links para Baralhos e Organizador
├── baralhos.html       # placeholder
├── organizador.html    # página principal do módulo novo
├── README.md
└── assets/
    ├── css/
    │   ├── base.css            # design system: variáveis, reset, header, botões, modais, toast
    │   └── organizador.css     # estilos do organizador (layout, agenda, tarefas, matérias)
    └── js/
        ├── storage.js          # camada de persistência (localStorage; preparada p/ trocar por backend)
        ├── categorias.js       # catálogo das 12 categorias de tarefa (cor, ícone)
        ├── estudante.js        # perfil do(a) estudante
        ├── materias.js         # CRUD de matérias
        ├── tarefas.js          # CRUD, filtros, detecção de conflito e totais
        ├── agenda.js            # utilitários de datas e semana
        └── organizador.js      # orquestrador da UI da página
```

## Persistência

Hoje tudo vive em `localStorage` na chave `suinda.organizador.v1`. A camada
`SuindaStorage` em [assets/js/storage.js](assets/js/storage.js) isola o I/O — para
plugar PHP/MySQL no futuro basta trocar `read`, `write`, `reset` por chamadas
`fetch()`. A estrutura de dados já está organizada como tabelas:

- `estudante` (nome, objetivo, cargaSemanal)
- `materias[]` (id, nome, cor, prioridade, cargaSemanal, observacoes, status)
- `tarefas[]` (id, titulo, descricao, materiaId, categoria, data, horaInicio, horaFim, duracaoMin, prioridade, status)
- `disponibilidade` (minutos por dia da semana)
- `configAgenda` (horaInicio, horaFim, slotMinutos)

Há botões de **Exportar / Importar backup** em JSON dentro da página, úteis
inclusive para migração futura.

## Como usar a página

1. Abra **Organizador** no menu superior.
2. Em "Editar perfil do(a) estudante", preencha seu nome e objetivo.
3. Cadastre suas matérias (cor, prioridade, carga horária).
4. Crie tarefas — via botão "+ Nova tarefa" ou clicando em qualquer espaço vazio
   da agenda para pré-preencher dia e horário.
5. Acompanhe o resumo da semana, alertas de excesso de carga e conflitos de horário.

Categorias com bandeira **sem matéria** (pausa, personalizada) não exigem matéria vinculada.
