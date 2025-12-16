# Airygen SEO

Um [plugin SEO para WordPress](https://www.airygen.com/pt) modular que combina controles SEO na página, dados estruturados, SEO técnico, fluxos de trabalho de links internos e saída pronta para IA, mantendo toda a experiência de edição dentro do WordPress.

## Requisitos

- WordPress 6.3+
- PHP 8.1+

## Funcionalidades

### SEO na página
- Título, descrição, URL canônica e diretivas robots personalizados por post/página
- Verificação de largura em pixels e calculadora de pontuação SEO
- Análise de frase-chave foco

### Dados estruturados
- Schema JSON-LD para artigos, site, organização, autores e breadcrumbs
- Schema de produtos WooCommerce

### Social & Meta
- Metadados Open Graph (Facebook) e cartões Twitter/X
- Assinaturas RSS e verificação de site (Google, Bing, Yandex)

### SEO técnico
- Geração e gerenciamento de sitemap XML
- Controle de robots.txt e diretivas robots meta
- Gerenciamento de redirecionamentos (301 / 302 / 410) e registro de erros 404
- Monitoramento de links quebrados e indexação instantânea (Google Indexing API)
- Links alternativos Hreflang

### Conteúdo & UX
- Breadcrumbs (HTML + Schema)
- Bloco de sumário
- Bloco de posts relacionados
- SEO de imagens — injeção automática de `alt`/`title` em tempo de execução com templates personalizáveis
- Gerenciador de snippets de código (injeção em head / body / footer)

### Links internos
- Contagem e rastreamento de distribuição de links
- Sugestões de links internos
- Visualização de clusters de tópicos

### Extensões
- SEO local, SEO de autor, SEO de taxonomia
- SEO para WooCommerce
- Integrações de notificações

### Utilitários de IA
- `llms.txt` — índice do site legível por agentes de IA
- Markdown for Agents — exportação de conteúdo como Markdown para LLMs

## Desenvolvimento

### Pré-requisitos

- Docker
- pnpm 9+

### Iniciar o ambiente de desenvolvimento

```bash
make dev
```

Inicia os contêineres Docker, instala o WordPress, ativa o plugin e inicia os servidores de desenvolvimento frontend. Acesse `http://localhost:9000` (admin: `admin` / `admin`).

> **Banco de dados limpo:** Por padrão, o contêiner MySQL importa dados de `.docker/wp/schema/backup.sql`. Para começar com uma instalação limpa do WordPress, comente essa linha em `docker-compose.yml` antes de executar `make up`:
> ```yaml
> # - ./.docker/wp/schema/backup.sql:/docker-entrypoint-initdb.d/backup.sql:ro
> ```

### Comandos principais

| Comando | Descrição |
|---|---|
| `make up` / `make down` | Iniciar / parar contêineres Docker |
| `make wp.init-dev-site` | Instalar WordPress + ativar plugin + WooCommerce |
| `make dev.admin` | Servidor de desenvolvimento SPA admin |
| `make dev.block-editor` | Servidor de desenvolvimento do editor de blocos |
| `make dev.classic-editor` | Servidor de desenvolvimento do editor clássico |
| `make tests` | Executar testes de integração PHPUnit |
| `make phpcs` | PHP CodeSniffer |
| `make phpstan` | Análise estática PHP |
| `make lint` | ESLint |
| `make lint.types` | Verificação de tipos TypeScript |
| `make i18n.check` | Regenerar POT e sincronizar arquivos PO |
| `make i18n.build` | Gerar arquivos de tradução MO e JSON |

### Stack tecnológico

- **PHP** 8.1+ — autoload PSR-4, namespace `Airygen\`, layout modular orientado a funcionalidades
- **React + TypeScript** 18 / 5 — três pontos de entrada: SPA admin, editor de blocos, editor clássico
- **Build** — `@wordpress/scripts` (webpack), pnpm workspaces
- **CSS** — Tailwind CSS 3, Sass
- **Testes** — PHPUnit 8, `wp-phpunit`, ambiente WordPress baseado em Docker

## Arquitetura

O código-fonte fica em `src/Modules/<Feature>`, com cada módulo dividido nas camadas `Admin/`, `Public/`, `Runtime/` e `Domain/`. A infraestrutura compartilhada está em `src/Admin`, `src/Public` e `src/Support`. Consulte `guidelines/structure.md` para o guia completo de estrutura.

## Versão paga

Um plugin pago separado com recursos avançados de análise de IA está disponível em **[airygen.com](https://www.airygen.com/pt)**. Requer que este plugin gratuito esteja instalado e ativo.

## Licença

GPLv3 ou posterior

---

Airygen SEO © TerryL.in / Airygen Team
