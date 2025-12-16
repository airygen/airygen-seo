# Airygen SEO

オンページ SEO コントロール、構造化データ、テクニカル SEO、内部リンクワークフロー、AI 出力を一つに統合したモジュール式 [WordPress SEO プラグイン](https://www.airygen.com/ja)。編集体験はすべて WordPress 内で完結します。

## 動作要件

- WordPress 6.3+
- PHP 8.1+

## 機能

### オンページ SEO
- 投稿・ページごとにタイトル、説明、canonical URL、robots ディレクティブをカスタマイズ
- ピクセル幅チェックと SEO スコア計算
- フォーカスキーフレーズ分析

### 構造化データ
- 記事・ウェブサイト・組織・著者・パンくずの JSON-LD Schema
- WooCommerce 商品 Schema

### ソーシャル・メタ
- Open Graph（Facebook）および Twitter/X カードメタデータ
- RSS フィード署名とサイト確認（Google・Bing・Yandex）

### テクニカル SEO
- XML サイトマップの生成と管理
- robots.txt および robots meta ディレクティブの制御
- リダイレクト管理（301 / 302 / 410）と 404 ログ
- 壊れたリンクの監視とインスタントインデックス（Google Indexing API）
- Hreflang 代替言語リンク

### コンテンツ・UX
- パンくずリスト（HTML + Schema）
- 目次ブロック
- 関連記事ブロック
- 画像 SEO — 実行時に `alt`／`title` 属性を自動補完（カスタムテンプレート対応）
- コードスニペット管理（head / body / footer への挿入）

### 内部リンク
- リンク数のカウントと分布追跡
- 内部リンク提案
- トピッククラスターの可視化

### 拡張モジュール
- ローカル SEO・著者 SEO・タクソノミー SEO
- WooCommerce SEO
- 通知インテグレーション

### AI ユーティリティ
- `llms.txt` — AI エージェント向けサイトインデックス
- Markdown for Agents — コンテンツを LLM 向け Markdown としてエクスポート

## 開発

### 前提条件

- Docker
- pnpm 9+

### 開発環境の起動

```bash
make dev
```

Docker コンテナを起動し、WordPress をインストール、プラグインを有効化、フロントエンド開発サーバーを起動します。`http://localhost:9000`（管理者：`admin` / `admin`）にアクセスしてください。

> **新規データベース：** デフォルトでは MySQL コンテナが `.docker/wp/schema/backup.sql` からデータをインポートします。クリーンな WordPress インストールから始める場合は、`make up` 実行前に `docker-compose.yml` の該当行をコメントアウトしてください：
> ```yaml
> # - ./.docker/wp/schema/backup.sql:/docker-entrypoint-initdb.d/backup.sql:ro
> ```

### 主要コマンド

| コマンド | 説明 |
|---|---|
| `make up` / `make down` | Docker コンテナの起動 / 停止 |
| `make wp.init-dev-site` | WordPress インストール + プラグイン有効化 + WooCommerce |
| `make dev.admin` | 管理画面 SPA 開発サーバー |
| `make dev.block-editor` | ブロックエディター開発サーバー |
| `make dev.classic-editor` | クラシックエディター開発サーバー |
| `make tests` | PHPUnit 統合テストの実行 |
| `make phpcs` | PHP CodeSniffer |
| `make phpstan` | PHP 静的解析 |
| `make lint` | ESLint |
| `make lint.types` | TypeScript 型チェック |
| `make i18n.check` | POT の再生成と PO ファイルの同期 |
| `make i18n.build` | MO と JSON 翻訳ファイルの生成 |

### 技術スタック

- **PHP** 8.1+ — PSR-4 オートロード、名前空間 `Airygen\`、機能優先モジュールレイアウト
- **React + TypeScript** 18 / 5 — 3 つのエントリーポイント：管理画面 SPA・ブロックエディター・クラシックエディター
- **ビルド** — `@wordpress/scripts`（webpack）、pnpm workspaces
- **CSS** — Tailwind CSS 3、Sass
- **テスト** — PHPUnit 8、`wp-phpunit`、Docker ベースの WordPress 環境

## アーキテクチャ

ソースコードは `src/Modules/<Feature>` に配置され、各モジュールは `Admin/`・`Public/`・`Runtime/`・`Domain/` レイヤーに分かれています。共有インフラは `src/Admin`・`src/Public`・`src/Support` に置かれます。詳細は `guidelines/structure.md` を参照してください。

## 有料版

高度な AI 分析機能を含む別途有料プラグインを **[airygen.com](https://www.airygen.com/ja)** で提供しています。ご利用にはこの無料プラグインのインストールと有効化が必要です。

## ライセンス

GPLv3 またはそれ以降

---

Airygen SEO © TerryL.in / Airygen Team
