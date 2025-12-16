# Airygen SEO

Ein modulares [WordPress-SEO-Plugin](https://www.airygen.com/de), das On-Page-SEO-Steuerung, strukturierte Daten, technisches SEO, interne Link-Workflows und KI-fähige Ausgabe vereint — und dabei das gesamte Bearbeitungserlebnis in WordPress behält.

## Systemanforderungen

- WordPress 6.3+
- PHP 8.1+

## Funktionen

### On-Page-SEO
- Individueller Titel, Beschreibung, Canonical-URL und Robots-Direktiven pro Beitrag/Seite
- Pixel-Breiten-Prüfung und SEO-Score-Rechner
- Fokus-Keyphrase-Analyse

### Strukturierte Daten
- JSON-LD-Schema für Artikel, Website, Organisation, Autoren und Breadcrumbs
- WooCommerce-Produktschema

### Social & Meta
- Open-Graph- (Facebook) und Twitter/X-Card-Metadaten
- RSS-Feed-Signaturen und Website-Verifizierung (Google, Bing, Yandex)

### Technisches SEO
- XML-Sitemap-Generierung und -Verwaltung
- robots.txt- und Robots-Meta-Direktiven-Steuerung
- Weiterleitungsverwaltung (301 / 302 / 410) und 404-Protokollierung
- Überwachung defekter Links und sofortige Indexierung (Google Indexing API)
- Hreflang-Alternativsprachlinks

### Inhalt & UX
- Breadcrumbs (HTML + Schema)
- Inhaltsverzeichnis-Block
- Ähnliche Beiträge-Block
- Bild-SEO — automatische Ergänzung von `alt`/`title` zur Laufzeit mit anpassbaren Vorlagen
- Code-Snippet-Manager (Einbindung in head / body / footer)

### Interne Links
- Link-Zählung und Verteilungsverfolgung
- Vorschläge für interne Links
- Themencluster-Visualisierung

### Erweiterungen
- Lokales SEO, Autoren-SEO, Taxonomie-SEO
- WooCommerce SEO
- Benachrichtigungsintegrationen

### KI-Werkzeuge
- `llms.txt` — für KI-Agenten lesbare Website-Index
- Markdown for Agents — Inhaltsexport als Markdown für LLMs

## Entwicklung

### Voraussetzungen

- Docker
- pnpm 9+

### Entwicklungsumgebung starten

```bash
make dev
```

Startet Docker-Container, installiert WordPress, aktiviert das Plugin und startet die Frontend-Entwicklungsserver. Öffnen Sie `http://localhost:9000` (Admin: `admin` / `admin`).

> **Neue Datenbank:** Standardmäßig importiert der MySQL-Container Daten aus `.docker/wp/schema/backup.sql`. Für eine saubere WordPress-Installation kommentieren Sie diese Zeile in `docker-compose.yml` vor dem Ausführen von `make up` aus:
> ```yaml
> # - ./.docker/wp/schema/backup.sql:/docker-entrypoint-initdb.d/backup.sql:ro
> ```

### Wichtige Befehle

| Befehl | Beschreibung |
|---|---|
| `make up` / `make down` | Docker-Container starten / stoppen |
| `make wp.init-dev-site` | WordPress installieren + Plugin aktivieren + WooCommerce |
| `make dev.admin` | Admin-SPA-Entwicklungsserver |
| `make dev.block-editor` | Block-Editor-Entwicklungsserver |
| `make dev.classic-editor` | Klassischer-Editor-Entwicklungsserver |
| `make tests` | PHPUnit-Integrationstests ausführen |
| `make phpcs` | PHP CodeSniffer |
| `make phpstan` | PHP-Statikanalyse |
| `make lint` | ESLint |
| `make lint.types` | TypeScript-Typprüfung |
| `make i18n.check` | POT neu erstellen und PO-Dateien synchronisieren |
| `make i18n.build` | MO- und JSON-Übersetzungsdateien generieren |

### Technologie-Stack

- **PHP** 8.1+ — PSR-4-Autoloading, Namespace `Airygen\`, funktionsorientiertes Modullayout
- **React + TypeScript** 18 / 5 — drei Einstiegspunkte: Admin-SPA, Block-Editor, klassischer Editor
- **Build** — `@wordpress/scripts` (webpack), pnpm workspaces
- **CSS** — Tailwind CSS 3, Sass
- **Tests** — PHPUnit 8, `wp-phpunit`, Docker-basierte WordPress-Umgebung

## Architektur

Der Quellcode befindet sich unter `src/Modules/<Feature>`, wobei jedes Modul in die Schichten `Admin/`, `Public/`, `Runtime/` und `Domain/` unterteilt ist. Gemeinsame Infrastruktur befindet sich in `src/Admin`, `src/Public` und `src/Support`. Den vollständigen Strukturleitfaden finden Sie in `guidelines/structure.md`.

## Bezahlte Version

Ein separates bezahltes Plugin mit erweiterten KI-Analysefunktionen ist unter **[airygen.com](https://www.airygen.com/de)** verfügbar. Es erfordert, dass dieses kostenlose Plugin installiert und aktiv ist.

## Lizenz

GPLv3 oder später

---

Airygen SEO © TerryL.in / Airygen Team
