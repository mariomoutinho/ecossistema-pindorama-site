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
