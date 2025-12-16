# Airygen SEO

一款模块化的 [WordPress SEO 插件](https://www.airygen.com/zh-Hans)，整合页面 SEO 控制、结构化数据、技术 SEO、内部链接工作流程与 AI 输出，让整个编辑体验保留在 WordPress 之内。

## 系统需求

- WordPress 6.3+
- PHP 8.1+

## 功能

### 页面 SEO
- 为每篇文章／页面自定义标题、描述、canonical URL 及 robots 指令
- 像素宽度检查与 SEO 评分计算器
- 焦点关键词分析

### 结构化数据
- 文章、网站、组织、作者与面包屑的 JSON-LD Schema
- WooCommerce 商品 Schema

### 社交与 Meta
- Open Graph（Facebook）与 Twitter/X 卡片 metadata
- RSS 订阅签名及网站验证（Google、Bing、Yandex）

### 技术 SEO
- XML Sitemap 生成与管理
- robots.txt 及 robots meta 指令控制
- 重定向管理（301 / 302 / 410）与 404 记录
- 失效链接监控及即时索引（Google Indexing API）
- Hreflang 多语言替代链接

### 内容与用户体验
- 面包屑（HTML + Schema）
- 目录区块
- 相关文章区块
- 图片 SEO — 运行期自动补全 `alt`／`title`，支持自定义模板
- 代码片段管理（head / body / footer 注入）

### 内部链接
- 链接计数与分布追踪
- 内部链接建议
- 主题集群可视化

### 扩展模块
- 本地 SEO、作者 SEO、分类法 SEO
- WooCommerce SEO
- 通知集成

### AI 工具
- `llms.txt` — 供 AI 代理读取的网站索引
- Markdown for Agents — 将内容导出为 Markdown 供 LLM 使用

## 开发

### 环境需求

- Docker
- pnpm 9+

### 启动开发环境

```bash
make dev
```

启动 Docker 容器、安装 WordPress、激活插件并启动前端开发服务器。请打开 `http://localhost:9000`（管理员账密：`admin` / `admin`）。

> **全新数据库：** 默认 MySQL 容器会从 `.docker/wp/schema/backup.sql` 导入数据。若要全新安装，请在执行 `make up` 前，先将 `docker-compose.yml` 中该行注释：
> ```yaml
> # - ./.docker/wp/schema/backup.sql:/docker-entrypoint-initdb.d/backup.sql:ro
> ```

### 常用命令

| 命令 | 说明 |
|---|---|
| `make up` / `make down` | 启动 / 停止 Docker 容器 |
| `make wp.init-dev-site` | 安装 WordPress + 激活插件 + WooCommerce |
| `make dev.admin` | 管理后台 SPA 开发服务器 |
| `make dev.block-editor` | 区块编辑器开发服务器 |
| `make dev.classic-editor` | 传统编辑器开发服务器 |
| `make tests` | 运行 PHPUnit 集成测试 |
| `make phpcs` | PHP CodeSniffer |
| `make phpstan` | PHP 静态分析 |
| `make lint` | ESLint |
| `make lint.types` | TypeScript 类型检查 |
| `make i18n.check` | 重建 POT 并同步 PO 文件 |
| `make i18n.build` | 生成 MO 与 JSON 翻译资源 |

### 技术栈

- **PHP** 8.1+ — PSR-4 自动加载，命名空间 `Airygen\`，功能优先模块布局
- **React + TypeScript** 18 / 5 — 三个入口：管理后台 SPA、区块编辑器、传统编辑器
- **构建** — `@wordpress/scripts`（webpack）、pnpm workspaces
- **CSS** — Tailwind CSS 3、Sass
- **测试** — PHPUnit 8、`wp-phpunit`、Docker 化 WordPress 环境

## 架构

源码位于 `src/Modules/<Feature>`，每个模块分为 `Admin/`、`Public/`、`Runtime/` 与 `Domain/` 层。共用基础设施放在 `src/Admin`、`src/Public` 及 `src/Support`。完整布局说明请见 `guidelines/structure.md`。

## 付费版

提供独立的付费插件，内含进阶 AI 分析功能，详见 **[airygen.com](https://www.airygen.com/zh-Hans)**。需先安装并激活本免费插件才能使用。

## 许可证

GPLv3 或更新版本

---

Airygen SEO © TerryL.in / Airygen Team
