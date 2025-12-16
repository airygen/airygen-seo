# Airygen SEO

Un [plugin SEO per WordPress](https://www.airygen.com/it) modulare che combina controlli SEO on-page, dati strutturati, SEO tecnico, flussi di lavoro per i link interni e output pronto per l'IA — mantenendo tutta l'esperienza di modifica all'interno di WordPress.

## Requisiti di sistema

- WordPress 6.3+
- PHP 8.1+

## Funzionalità

### SEO on-page
- Titolo, descrizione, URL canonico e direttive robots personalizzati per post/pagina
- Controllo larghezza in pixel e calcolatore del punteggio SEO
- Analisi della frase chiave focus

### Dati strutturati
- Schema JSON-LD per articoli, sito web, organizzazione, autori e breadcrumb
- Schema prodotti WooCommerce

### Social & Meta
- Metadati Open Graph (Facebook) e schede Twitter/X
- Firme RSS e verifica sito (Google, Bing, Yandex)

### SEO tecnico
- Generazione e gestione della sitemap XML
- Controllo di robots.txt e delle direttive robots meta
- Gestione dei reindirizzamenti (301 / 302 / 410) e registro degli errori 404
- Monitoraggio dei link interrotti e indicizzazione istantanea (Google Indexing API)
- Link alternativi Hreflang

### Contenuto & UX
- Breadcrumb (HTML + Schema)
- Blocco indice dei contenuti
- Blocco post correlati
- SEO immagini — iniezione automatica di `alt`/`title` a runtime con template personalizzabili
- Gestore di snippet di codice (iniezione in head / body / footer)

### Link interni
- Conteggio e monitoraggio della distribuzione dei link
- Suggerimenti per i link interni
- Visualizzazione dei cluster tematici

### Estensioni
- SEO locale, SEO autore, SEO tassonomia
- SEO per WooCommerce
- Integrazioni di notifiche

### Utilità IA
- `llms.txt` — indice del sito leggibile dagli agenti IA
- Markdown for Agents — esportazione del contenuto come Markdown per LLM

## Sviluppo

### Prerequisiti

- Docker
- pnpm 9+

### Avviare l'ambiente di sviluppo

```bash
make dev
```

Avvia i container Docker, installa WordPress, attiva il plugin e lancia i server di sviluppo frontend. Visita `http://localhost:9000` (admin: `admin` / `admin`).

> **Database pulito:** Per impostazione predefinita, il container MySQL importa i dati da `.docker/wp/schema/backup.sql`. Per iniziare con un'installazione WordPress pulita, commenta quella riga in `docker-compose.yml` prima di eseguire `make up`:
> ```yaml
> # - ./.docker/wp/schema/backup.sql:/docker-entrypoint-initdb.d/backup.sql:ro
> ```

### Comandi principali

| Comando | Descrizione |
|---|---|
| `make up` / `make down` | Avviare / fermare i container Docker |
| `make wp.init-dev-site` | Installare WordPress + attivare plugin + WooCommerce |
| `make dev.admin` | Server di sviluppo SPA admin |
| `make dev.block-editor` | Server di sviluppo dell'editor a blocchi |
| `make dev.classic-editor` | Server di sviluppo dell'editor classico |
| `make tests` | Eseguire i test di integrazione PHPUnit |
| `make phpcs` | PHP CodeSniffer |
| `make phpstan` | Analisi statica PHP |
| `make lint` | ESLint |
| `make lint.types` | Controllo tipi TypeScript |
| `make i18n.check` | Rigenerare POT e sincronizzare i file PO |
| `make i18n.build` | Generare i file di traduzione MO e JSON |

### Stack tecnologico

- **PHP** 8.1+ — autoload PSR-4, namespace `Airygen\`, layout modulare orientato alle funzionalità
- **React + TypeScript** 18 / 5 — tre punti di ingresso: SPA admin, editor a blocchi, editor classico
- **Build** — `@wordpress/scripts` (webpack), pnpm workspaces
- **CSS** — Tailwind CSS 3, Sass
- **Testing** — PHPUnit 8, `wp-phpunit`, ambiente WordPress basato su Docker

## Architettura

Il codice sorgente si trova in `src/Modules/<Feature>`, con ogni modulo suddiviso nei livelli `Admin/`, `Public/`, `Runtime/` e `Domain/`. L'infrastruttura condivisa si trova in `src/Admin`, `src/Public` e `src/Support`. Consulta `guidelines/structure.md` per la guida completa alla struttura.

## Versione a pagamento

È disponibile un plugin a pagamento separato con funzionalità avanzate di analisi IA su **[airygen.com](https://www.airygen.com/it)**. Richiede che questo plugin gratuito sia installato e attivo.

## Licenza

GPLv3 o successiva

---

Airygen SEO © TerryL.in / Airygen Team
