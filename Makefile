.PHONY: up build down restart reset shell shell.db \
    phpcs phpstan phpcbf tests lint lint.types \
    wp.install wp.activate-plugin wp.install-woocommerce wp.init-dev-site dev \
    wp.install-core-languages wp.install-languages \
    i18n.check i18n.build i18n.new i18n.po2json i18n.json2po \
	postdata.normalize screencast

SUPPORTED_CORE_LANGS := zh_TW zh_CN de_DE fr_FR es_ES pt_PT pt_BR it_IT ja ko_KR ru_RU ar vi id_ID ur hi_IN bn_BD

up:
	docker compose up -d

build:
	docker compose build

down:
	docker compose down

down-clear:
	docker compose down -v --remove-orphans

restart:
	docker compose down
	docker compose up -d

reset:
	docker compose down -v --remove-orphans
	docker compose up -d

shell:
	docker compose exec wordpress bash

shell.db:
	docker compose exec db mysql -u wp_user -pwp_pass wordpress

wp.install:
	docker compose exec wordpress sh -c '\
		cd /var/www/html && \
		if [ ! -f wp-config.php ]; then \
			wp config create \
				--dbname=wordpress \
				--dbuser=wp_user \
				--dbpass=wp_pass \
				--dbhost=db:3306 \
				--dbprefix=wp_ \
				--skip-check \
				--allow-root; \
		fi'
	docker compose exec wordpress sh -c '\
		cd /var/www/html && \
		if [ -f wp-config.php ]; then \
			mkdir -p wp-content/logs && \
			wp config set WP_DEBUG true --type=constant --raw --allow-root && \
			wp config set WP_DEBUG_LOG '"'"'wp-content/logs/debug.log'"'"' --type=constant --allow-root && \
			wp config set WP_DEBUG_DISPLAY false --type=constant --raw --allow-root && \
			wp config set FS_METHOD direct --type=constant --allow-root && \
			wp config set DISALLOW_FILE_MODS false --type=constant --raw --allow-root; \
		fi'
	docker compose exec wordpress sh -c '\
		if ! wp core is-installed --allow-root; then \
			wp core install \
				--url="http://localhost:8000" \
				--title="Airygen Dev" \
				--admin_user=admin \
				--admin_password=admin \
				--admin_email=admin@example.com \
				--skip-email \
				--allow-root; \
		fi'
	docker compose exec wordpress sh -c '\
		cd /var/www/html/wp-content/plugins/airygen-seo && composer install'

wp.activate-plugin:
	docker compose exec wordpress wp plugin activate airygen-seo --allow-root

wp.install-woocommerce:
	docker compose exec wordpress sh -c '\
		cd /var/www/html && \
		if ! wp plugin is-installed woocommerce --allow-root; then \
			wp plugin install woocommerce --activate --allow-root; \
		else \
			wp plugin activate woocommerce --allow-root || true; \
		fi'

wp.init-dev-site:
	make wp.install
	make wp.activate-plugin
	make wp.install-woocommerce

# Install WordPress core language packs used by this project.
wp.install-core-languages:
	docker compose exec wordpress sh -c '\
		for lang in $(SUPPORTED_CORE_LANGS); do \
			wp language core install "$$lang" --allow-root; \
		done \
	'

wp.install-languages: wp.install-core-languages

fake:
	docker compose exec wordpress sh -c '\
		curl -L -o /tmp/64-block-test-data.xml \
			https://raw.githubusercontent.com/WordPress/theme-test-data/master/64-block-test-data.xml && \
		wp plugin install wordpress-importer --activate --allow-root && \
		wp import /tmp/64-block-test-data.xml --authors=create --allow-root'

phpcs:
	php -d xdebug.mode=off vendor/bin/phpcs --parallel=`getconf _NPROCESSORS_ONLN`

phpstan:
	php -d xdebug.mode=off vendor/bin/phpstan analyse --memory-limit=-1

tests:
	docker compose exec wordpress sh -c '\
		cd /var/www/html/wp-content/plugins/airygen-seo && \
		php -d xdebug.mode=off vendor/bin/phpunit --testdox \
	'

lint:
	pnpm exec eslint "packages/**/*.ts" "packages/**/*.tsx"

# TypeScript-only check (no emit).
lint.types:
	pnpm run typecheck

lint-fix:
	pnpm exec eslint "packages/**/*.ts" "packages/**/*.tsx" --fix

logs-clear:
	docker compose exec wordpress rm -f wp-content/logs/debug.log || true

phpcbf:
	vendor/bin/phpcbf

dev: up
	sleep 10
	make wp.init-dev-site
	pnpm dev:admin

dev.admin:
	pnpm dev:admin

dev.block-editor:
	pnpm dev:block-editor

dev.classic-editor:
	pnpm dev:classic-editor

# Standard i18n step 1:
# Rebuild POT from source/build JS and sync missing/updated entries into all locale PO files.
i18n.check:
	pnpm run build
	docker compose exec wordpress sh -c '\
		cd /var/www/html/wp-content/plugins/airygen-seo && \
		php -d memory_limit=1024M /usr/local/bin/wp i18n make-pot . languages/airygen-seo.pot \
			--domain=airygen-seo \
			--include=src,packages,config,resources,build \
		--exclude=node_modules,vendor \
		--allow-root && \
	php -d memory_limit=1024M /usr/local/bin/wp i18n update-po languages/airygen-seo.pot languages --allow-root \
	'
	python3 scripts/strip_po_comments.py

# Standard i18n step 2:
# After human translation is finished in PO files, build MO (PHP) and JSON (React/JS) artifacts.
i18n.build:
	@if command -v wp >/dev/null 2>&1; then \
		wp i18n make-mo languages --allow-root && \
		wp i18n make-json languages --no-purge --allow-root; \
	else \
		docker compose exec wordpress sh -c '\
			cd /var/www/html/wp-content/plugins/airygen-seo && \
			wp i18n make-mo languages --allow-root && \
			wp i18n make-json languages --no-purge --allow-root \
		'; \
	fi

# Create new locale PO skeleton files from languages/airygen-seo.pot.
i18n.new:
	./scripts/create_po_locales.sh

# Export msgid/msgstr pairs from languages/airygen-seo-*.po to languages/untranslated/*.json.
i18n.po2json:
	python3 scripts/po_to_json.py

# Write msgstr values from languages/untranslated/*.json back to languages/airygen-seo-*.po.
i18n.json2po:
	python3 scripts/json_to_po.py
	python3 scripts/strip_po_comments.py

# Normalize legacy per-post SEO meta into _airygen_post_data across all sites.
postdata.normalize:
	docker compose exec wordpress sh -c '\
		cd /var/www/html/wp-content/plugins/airygen-seo && \
		wp eval-file scripts/normalize_post_data.php --allow-root \
	'

# Record admin walkthrough videos and per-page screenshots.
# Output structure:
#   {OUT}/{YYYYmmdd}/{route-name}/videos/{locale}/admin-{page-group}-{viewport}.webm
#   {OUT}/{YYYYmmdd}/{route-name}/videos/{locale}/editor-{page-group}-{viewport}.webm
#   {OUT}/{YYYYmmdd}/{route-name}/videos/{locale}/classic-{page-group}-{viewport}.webm
#   {OUT}/{YYYYmmdd}/{route-name}/screenshots/{locale}/admin-{page-group}-{view}-{viewport}.png
#   {OUT}/{YYYYmmdd}/{route-name}/screenshots/{locale}/editor-{page-group}-{view}-{viewport}.png
#   {OUT}/{YYYYmmdd}/{route-name}/screenshots/{locale}/classic-{page-group}-{view}-{viewport}.png
#   {OUT}/{YYYYmmdd}/{route-name}/manifest/{locale}/index.json
# Execution notes:
#   A route is skipped when manifest/{locale}/index.json already exists.
#   manifest/{locale}/index.json is written only after that route finishes successfully.
#   FORCE=1 ignores existing manifest files and merges refreshed outputs back into manifest/{locale}/index.json.
# Override examples:
#   ROUTES=scripts/admin-screenshots/routes/related-posts.json OUT=/custom/path VIEWPORTS=desktop PAUSE=5000 TAB_PAUSE=3000 make screencast
#   LOCALES=en,ja,ko BASE=http://localhost:8000 make screencast
#   ROUTES=scripts/admin-screenshots/routes/topic-cluster.json LOCALES=en OUT=/custom/path make screencast
screencast:
	AIRYGEN_CAPTURE_ROUTES=$${ROUTES:-scripts/admin-screenshots/routes} \
	AIRYGEN_CAPTURE_POST_ID=$${POST_ID:-50} \
	AIRYGEN_CAPTURE_LOCALES=$${LOCALES:-} \
	AIRYGEN_CAPTURE_LOCALES_BASE=$${BASE:-http://localhost:8000} \
	AIRYGEN_CAPTURE_OUTPUT=$${OUT:-/mnt/d/e2e/videos} \
	AIRYGEN_CAPTURE_VIEWPORTS=$${VIEWPORTS:-desktop} \
	AIRYGEN_CAPTURE_FORCE=$${FORCE:-0} \
	AIRYGEN_RECORD_PAUSE_MS=$${PAUSE:-5000} \
	AIRYGEN_RECORD_TAB_PAUSE_MS=$${TAB_PAUSE:-3000} \
	AIRYGEN_RECORD_SCROLL_MS=$${SCROLL:-1800} \
	pnpm run record:admin
