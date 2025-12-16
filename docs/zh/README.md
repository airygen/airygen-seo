# Airygen SEO

一款模組化的 [WordPress SEO 外掛](https://www.airygen.com/zh)，整合頁面 SEO 控制、結構化資料、技術 SEO、內部連結工作流程與 AI 輸出，讓整個編輯體驗保留在 WordPress 之內。

## 系統需求

- WordPress 6.3+
- PHP 8.1+

## 功能

### 頁面 SEO
- 為每篇文章／頁面自訂標題、描述、canonical URL 及 robots 指令
- 像素寬度檢查與 SEO 評分計算器
- 焦點關鍵字分析

### 結構化資料
- 文章、網站、組織、作者與麵包屑的 JSON-LD Schema
- WooCommerce 商品 Schema

### 社群與 Meta
- Open Graph（Facebook）與 Twitter/X 卡片 metadata
- RSS 訂閱簽名及網站驗證（Google、Bing、Yandex）

### 技術 SEO
- XML Sitemap 生成與管理
- robots.txt 及 robots meta 指令控制
- 重新導向管理（301 / 302 / 410）與 404 記錄
- 失效連結監控及即時索引（Google Indexing API）
- Hreflang 多語言替代連結

### 內容與使用者體驗
- 麵包屑（HTML + Schema）
- 目錄區塊
- 相關文章區塊
- 圖片 SEO — 執行期自動補齊 `alt`／`title`，支援自訂範本
- 程式碼片段管理（head / body / footer 注入）

### 內部連結
- 連結計數與分佈追蹤
- 內部連結建議
- 主題群集視覺化

### 擴充模組
- 在地 SEO、作者 SEO、分類法 SEO
- WooCommerce SEO
- 通知整合

### AI 工具
- `llms.txt` — 供 AI 代理讀取的網站索引
- Markdown for Agents — 將內容匯出為 Markdown 供 LLM 使用

## 開發

### 環境需求

- Docker
- pnpm 9+

### 啟動開發環境

```bash
make dev
```

啟動 Docker 容器、安裝 WordPress、啟用外掛並啟動前端開發伺服器。請開啟 `http://localhost:9000`（管理員帳密：`admin` / `admin`）。

> **全新資料庫：** 預設 MySQL 容器會從 `.docker/wp/schema/backup.sql` 匯入資料。若要全新安裝，請在執行 `make up` 前，先將 `docker-compose.yml` 中該行註解：
> ```yaml
> # - ./.docker/wp/schema/backup.sql:/docker-entrypoint-initdb.d/backup.sql:ro
> ```

### 常用指令

| 指令 | 說明 |
|---|---|
| `make up` / `make down` | 啟動 / 停止 Docker 容器 |
| `make wp.init-dev-site` | 安裝 WordPress + 啟用外掛 + WooCommerce |
| `make dev.admin` | 管理後台 SPA 開發伺服器 |
| `make dev.block-editor` | 區塊編輯器開發伺服器 |
| `make dev.classic-editor` | 傳統編輯器開發伺服器 |
| `make tests` | 執行 PHPUnit 整合測試 |
| `make phpcs` | PHP CodeSniffer |
| `make phpstan` | PHP 靜態分析 |
| `make lint` | ESLint |
| `make lint.types` | TypeScript 型別檢查 |
| `make i18n.check` | 重建 POT 並同步 PO 檔案 |
| `make i18n.build` | 生成 MO 與 JSON 翻譯資源 |

### 技術棧

- **PHP** 8.1+ — PSR-4 自動載入，命名空間 `Airygen\`，功能優先模組佈局
- **React + TypeScript** 18 / 5 — 三個入口：管理後台 SPA、區塊編輯器、傳統編輯器
- **建置** — `@wordpress/scripts`（webpack）、pnpm workspaces
- **CSS** — Tailwind CSS 3、Sass
- **測試** — PHPUnit 8、`wp-phpunit`、Docker 化 WordPress 環境

## 架構

原始碼位於 `src/Modules/<Feature>`，每個模組分為 `Admin/`、`Public/`、`Runtime/` 與 `Domain/` 層。共用基礎設施放在 `src/Admin`、`src/Public` 及 `src/Support`。完整佈局說明請見 `guidelines/structure.md`。

## 付費版

提供獨立的付費外掛，內含進階 AI 分析功能，詳見 **[airygen.com](https://www.airygen.com/zh)**。需先安裝並啟用本免費外掛才能使用。

## 授權

GPLv3 或更新版本

---

Airygen SEO © TerryL.in / Airygen Team
