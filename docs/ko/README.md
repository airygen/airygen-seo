# Airygen SEO

온페이지 SEO 제어, 구조화 데이터, 기술적 SEO, 내부 링크 워크플로우, AI 출력을 하나로 통합한 모듈식 [WordPress SEO 플러그인](https://www.airygen.com/ko). 모든 편집 경험은 WordPress 안에서 완결됩니다.

## 시스템 요구 사항

- WordPress 6.3+
- PHP 8.1+

## 기능

### 온페이지 SEO
- 게시물/페이지별 제목, 설명, canonical URL, robots 지시어 커스터마이징
- 픽셀 너비 확인 및 SEO 점수 계산기
- 포커스 키프레이즈 분석

### 구조화 데이터
- 게시물, 웹사이트, 조직, 저자, 브레드크럼의 JSON-LD Schema
- WooCommerce 상품 Schema

### 소셜 & 메타
- Open Graph(Facebook) 및 Twitter/X 카드 메타데이터
- RSS 피드 서명 및 사이트 인증(Google, Bing, Yandex)

### 기술적 SEO
- XML 사이트맵 생성 및 관리
- robots.txt 및 robots meta 지시어 제어
- 리디렉션 관리(301 / 302 / 410) 및 404 로그
- 깨진 링크 모니터링 및 즉시 색인(Google Indexing API)
- Hreflang 대체 언어 링크

### 콘텐츠 & UX
- 브레드크럼(HTML + Schema)
- 목차 블록
- 관련 게시물 블록
- 이미지 SEO — 런타임 시 `alt`/`title` 속성 자동 보완(커스텀 템플릿 지원)
- 코드 스니펫 관리(head / body / footer 삽입)

### 내부 링크
- 링크 수 카운트 및 분포 추적
- 내부 링크 제안
- 토픽 클러스터 시각화

### 확장 모듈
- 로컬 SEO, 저자 SEO, 택소노미 SEO
- WooCommerce SEO
- 알림 통합

### AI 유틸리티
- `llms.txt` — AI 에이전트용 사이트 인덱스
- Markdown for Agents — LLM용 Markdown으로 콘텐츠 내보내기

## 개발

### 필수 환경

- Docker
- pnpm 9+

### 개발 환경 시작

```bash
make dev
```

Docker 컨테이너를 시작하고, WordPress를 설치하고, 플러그인을 활성화한 뒤 프론트엔드 개발 서버를 실행합니다. `http://localhost:9000`(관리자: `admin` / `admin`)에 접속하세요.

> **새 데이터베이스:** 기본적으로 MySQL 컨테이너는 `.docker/wp/schema/backup.sql`에서 데이터를 가져옵니다. 새로운 WordPress 설치로 시작하려면 `make up` 실행 전에 `docker-compose.yml`의 해당 줄을 주석 처리하세요:
> ```yaml
> # - ./.docker/wp/schema/backup.sql:/docker-entrypoint-initdb.d/backup.sql:ro
> ```

### 주요 명령어

| 명령어 | 설명 |
|---|---|
| `make up` / `make down` | Docker 컨테이너 시작 / 중지 |
| `make wp.init-dev-site` | WordPress 설치 + 플러그인 활성화 + WooCommerce |
| `make dev.admin` | 관리자 SPA 개발 서버 |
| `make dev.block-editor` | 블록 에디터 개발 서버 |
| `make dev.classic-editor` | 클래식 에디터 개발 서버 |
| `make tests` | PHPUnit 통합 테스트 실행 |
| `make phpcs` | PHP CodeSniffer |
| `make phpstan` | PHP 정적 분석 |
| `make lint` | ESLint |
| `make lint.types` | TypeScript 타입 검사 |
| `make i18n.check` | POT 재생성 및 PO 파일 동기화 |
| `make i18n.build` | MO 및 JSON 번역 파일 생성 |

### 기술 스택

- **PHP** 8.1+ — PSR-4 자동 로딩, 네임스페이스 `Airygen\`, 기능 우선 모듈 레이아웃
- **React + TypeScript** 18 / 5 — 3개 엔트리포인트: 관리자 SPA, 블록 에디터, 클래식 에디터
- **빌드** — `@wordpress/scripts`(webpack), pnpm workspaces
- **CSS** — Tailwind CSS 3, Sass
- **테스트** — PHPUnit 8, `wp-phpunit`, Docker 기반 WordPress 환경

## 아키텍처

소스 코드는 `src/Modules/<Feature>` 아래에 위치하며, 각 모듈은 `Admin/`, `Public/`, `Runtime/`, `Domain/` 레이어로 분리됩니다. 공유 인프라는 `src/Admin`, `src/Public`, `src/Support`에 있습니다. 전체 레이아웃 가이드는 `guidelines/structure.md`를 참조하세요.

## 유료 버전

고급 AI 분석 기능이 포함된 별도의 유료 플러그인을 **[airygen.com](https://www.airygen.com/ko)**에서 제공합니다. 이 무료 플러그인이 설치 및 활성화되어 있어야 사용할 수 있습니다.

## 라이선스

GPLv3 또는 이후 버전

---

Airygen SEO © TerryL.in / Airygen Team
