# Diário de Classe

Sistema de acompanhamento pedagógico musical (alunos, aulas, progresso e financeiro).

- **Produção:** https://ddc.cpod.com.br/ (front) e https://ddc.cpod.com.br/api/* (API)
- **Stack e estrutura:** [docs/stack-proposta.md](docs/stack-proposta.md)
- **Regras de negócio e Wiki do app antigo:** [docs/wiki/index.html](docs/wiki/index.html)

## Pastas

| Pasta | Conteúdo |
|---|---|
| `public/` | Docroot do subdomínio: front compilado, `api.php` (autoroute) e `.htaccess` |
| `api/` | PHP (Slim), `.env`, migrações SQL e testes — fora do docroot |
| `web/` | Código-fonte do front (React + Vite) |
| `bin/` | Scripts de montagem do pacote de entrega |
| `docs/` | Documentação (Wiki, stack, escopo) |
| `legacy/` | App antigo (HTML/JS + planilhas), somente referência |
