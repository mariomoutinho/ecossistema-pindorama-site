# CLAUDE.md — Site do Ecossistema Pindorama

Repositório oficial: `mariomoutinho/ecossistema-pindorama-site` (branch `main`).

## Fluxo obrigatório de commit e push ao final das alterações

Sempre que uma tarefa alterar arquivos do projeto e for concluída com sucesso, ao final da execução você deve:

1. Revisar as alterações feitas.
2. Executar `git status` para inspecionar o estado da árvore.
3. Adicionar ao stage somente os arquivos relevantes da tarefa (`git add <arquivos>` — nunca `git add -A` cego).
4. Criar um commit com mensagem descritiva e específica ao que foi feito.
5. Executar `git push origin main`.
6. No fechamento da resposta ao usuário, informar:
   - quais arquivos principais foram alterados;
   - qual foi a mensagem do commit;
   - se o push foi concluído com sucesso.

### Quando NÃO commitar

Não faça commit prematuro quando:

- a tarefa estiver incompleta;
- houver erro pendente, teste falhando, ou build quebrado;
- a tarefa estiver aguardando confirmação do usuário sobre alguma decisão;
- o trabalho for puramente exploratório / de leitura, sem alterar arquivos;
- houver dúvida sobre se um arquivo modificado deve ou não fazer parte do commit — pergunte antes.

### Regras adicionais

- Credenciais (DB, API keys, secrets) **nunca** vão para o repositório. Use `config-db.php` (gitignored) com base em `config-db.example.php`.
- Antes de `git add`, confirme que nenhum arquivo bloqueado por `.gitignore` está sendo forçado.
- Mensagens de commit em português, no imperativo, prefixadas pelo tipo (`feat:`, `fix:`, `chore:`, `docs:`, `refactor:`, `style:`).
- Nunca use `--force`, `--no-verify` ou `reset --hard` sem pedido explícito do usuário.

## Uso obrigatório do Context7 MCP para documentação técnica

Este projeto tem o servidor MCP **Context7** configurado em `.mcp.json` (nome do servidor: `context7`). Use-o **sempre** que precisar consultar documentação de linguagem, biblioteca, framework, API, ferramenta, configuração, instalação ou exemplos de implementação atualizados — antes de escrever código que dependa de sintaxe, comportamento ou opções de configuração externas.

Isso inclui especialmente:

- **Stack do projeto:** PHP, MySQL, HTML5, CSS3, JavaScript puro.
- **Operação:** Git, GitHub Actions, deploy (Hostinger ou outros).
- **Front-end:** boas práticas de acessibilidade, performance, responsividade.
- **Qualquer dependência técnica** que venha a ser introduzida no projeto (ex.: bibliotecas JS, ferramentas PHP, frameworks de teste).

Fluxo esperado quando for implementar, corrigir, refatorar ou configurar algo que dependa de referência externa:

1. Identificar a biblioteca/ferramenta no Context7 (`mcp__context7__resolve-library-id`).
2. Buscar a documentação específica para a tarefa (`mcp__context7__get-library-docs`), focando no tópico exato.
3. Aplicar a solução com base na documentação retornada, citando brevemente a fonte no fechamento da resposta quando relevante.

Não use conhecimento desatualizado ou suposições sobre APIs quando o Context7 puder confirmar a versão atual — reduzir alucinação e código quebrado é o objetivo dessa regra.

### Configuração técnica do Context7 neste projeto

- Arquivo: `.mcp.json` (versionado, escopo do projeto).
- Comando: `npx -y @upstash/context7-mcp` (sem autenticação no tier gratuito).
- Para limites maiores: definir variável de ambiente `CONTEXT7_API_KEY` no ambiente local — **nunca commitar a chave**.
