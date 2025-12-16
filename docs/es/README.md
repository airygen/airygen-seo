# Airygen SEO

Un [plugin SEO para WordPress](https://www.airygen.com/es) modular que combina controles SEO en página, datos estructurados, SEO técnico, flujos de trabajo de enlaces internos y salida lista para IA, manteniendo toda la experiencia de edición dentro de WordPress.

## Requisitos

- WordPress 6.3+
- PHP 8.1+

## Características

### SEO en página
- Título, descripción, URL canónica y directivas robots personalizadas por entrada/página
- Comprobación de ancho en píxeles y calculadora de puntuación SEO
- Análisis de frase clave objetivo

### Datos estructurados
- Schema JSON-LD para artículos, sitio web, organización, autores y migas de pan
- Schema de productos WooCommerce

### Social y Meta
- Metadatos Open Graph (Facebook) y tarjetas Twitter/X
- Firmas de RSS y verificación de sitio (Google, Bing, Yandex)

### SEO técnico
- Generación y gestión de sitemap XML
- Control de robots.txt y directivas robots meta
- Gestión de redirecciones (301 / 302 / 410) y registro de errores 404
- Monitoreo de enlaces rotos e indexación instantánea (Google Indexing API)
- Enlaces alternativos Hreflang

### Contenido y UX
- Migas de pan (HTML + Schema)
- Bloque de tabla de contenidos
- Bloque de entradas relacionadas
- SEO de imágenes — inyección automática de `alt`/`title` en tiempo de ejecución con plantillas personalizables
- Gestor de fragmentos de código (inyección en head / body / footer)

### Enlaces internos
- Conteo y seguimiento de distribución de enlaces
- Sugerencias de enlaces internos
- Visualización de clústeres de temas

### Extensiones
- SEO local, SEO de autor, SEO de taxonomías
- SEO para WooCommerce
- Integraciones de notificaciones

### Utilidades de IA
- `llms.txt` — índice del sitio legible por agentes de IA
- Markdown for Agents — exportación de contenido como Markdown para LLMs

## Desarrollo

### Requisitos previos

- Docker
- pnpm 9+

### Iniciar el entorno de desarrollo

```bash
make dev
```

Inicia los contenedores Docker, instala WordPress, activa el plugin y arranca los servidores de desarrollo frontend. Visita `http://localhost:9000` (admin: `admin` / `admin`).

> **Base de datos limpia:** Por defecto, el contenedor MySQL importa datos desde `.docker/wp/schema/backup.sql`. Para comenzar con una instalación limpia de WordPress, comenta esa línea en `docker-compose.yml` antes de ejecutar `make up`:
> ```yaml
> # - ./.docker/wp/schema/backup.sql:/docker-entrypoint-initdb.d/backup.sql:ro
> ```

### Comandos principales

| Comando | Descripción |
|---|---|
| `make up` / `make down` | Iniciar / detener contenedores Docker |
| `make wp.init-dev-site` | Instalar WordPress + activar plugin + WooCommerce |
| `make dev.admin` | Servidor de desarrollo SPA de administración |
| `make dev.block-editor` | Servidor de desarrollo del editor de bloques |
| `make dev.classic-editor` | Servidor de desarrollo del editor clásico |
| `make tests` | Ejecutar pruebas de integración PHPUnit |
| `make phpcs` | PHP CodeSniffer |
| `make phpstan` | Análisis estático de PHP |
| `make lint` | ESLint |
| `make lint.types` | Comprobación de tipos TypeScript |
| `make i18n.check` | Regenerar POT y sincronizar archivos PO |
| `make i18n.build` | Generar archivos de traducción MO y JSON |

### Stack tecnológico

- **PHP** 8.1+ — autocarga PSR-4, espacio de nombres `Airygen\`, diseño modular orientado a funcionalidades
- **React + TypeScript** 18 / 5 — tres puntos de entrada: SPA de administración, editor de bloques, editor clásico
- **Build** — `@wordpress/scripts` (webpack), pnpm workspaces
- **CSS** — Tailwind CSS 3, Sass
- **Testing** — PHPUnit 8, `wp-phpunit`, entorno WordPress basado en Docker

## Arquitectura

El código fuente reside en `src/Modules/<Feature>`, con cada módulo dividido en capas `Admin/`, `Public/`, `Runtime/` y `Domain/`. La infraestructura compartida se encuentra en `src/Admin`, `src/Public` y `src/Support`. Consulta `guidelines/structure.md` para la guía completa de estructura.

## Versión de pago

Existe un plugin de pago independiente con funciones avanzadas de análisis de IA en **[airygen.com](https://www.airygen.com/es)**. Requiere tener este plugin gratuito instalado y activo.

## Licencia

GPLv3 o posterior

---

Airygen SEO © TerryL.in / Airygen Team
