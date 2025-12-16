# Testing Guidelines

Use this guide when running, writing, or debugging tests in Airygen SEO. Keep it aligned with the current Docker-based WordPress test environment.

## 1. Canonical test commands

- **Primary command:** `make tests`
  - This is the canonical entrypoint for the PHPUnit suite.
  - The Makefile runs `vendor/bin/phpunit --testdox` inside `docker compose exec wordpress`, which gives PHPUnit the expected WordPress filesystem layout and database network access.
- **Direct PHPUnit inside the container:** `php -d xdebug.mode=off vendor/bin/phpunit --testdox`
  - Only run PHPUnit directly after entering the WordPress container, for example via `docker compose exec wordpress bash`.

⚠️ Do **not** run PHPUnit from the host machine. The test bootstrap expects the container layout from `tests/wp-tests-config.php`, including `ABSPATH` at `/var/www/html/` and the database host `db`.

## 2. Bootstrap and database expectations

- PHPUnit boots through `tests/bootstrap.php`.
- The bootstrap loads `airygen-seo.php` during `muplugins_loaded` and then calls `AirygenTest\Support\DatabaseHelpers::ensure_custom_tables()`.
- WordPress test configuration lives in `tests/wp-tests-config.php`.
- The suite uses the `wptests_` table prefix. Running the tests can recreate or destroy tables with that prefix, so never point the test config at a shared or production database.
- If a feature adds or changes plugin-specific tables, update `tests/Support/DatabaseHelpers.php` so integration tests can create and truncate those tables consistently.

## 3. Writing new tests

- Prefer plain PHPUnit tests for pure domain logic under `src/Modules/*/Domain`.
- Use `AirygenTest\BaseTestCase` when you need WordPress factories, options, globals, post setup, or other WordPress test helpers.
- Mirror the source layout under `tests/`. Example: `src/Modules/Robots/Domain/Service/BuildRobots.php` should be covered by a test such as `tests/Modules/Robots/Domain/BuildRobotsTest.php`.
- Reuse shared helpers from `tests/Support` instead of rebuilding fixtures in each test file.
- Every new PHP test file should start with `<?php` and `declare(strict_types=1);`.
- Keep tests deterministic. Do not rely on external HTTP requests, wall-clock timing, or leftover database state from previous tests.

## 4. REST and route coverage

- Every route defined in `config/routes.php` must have coverage under `tests/Admin/Rest`.
- Extend `AirygenTest\Admin\Rest\RestRouteTestCase` for REST endpoint tests. That base class:
  - ensures and truncates plugin-specific tables
  - boots a fresh `WP_REST_Server`
  - fires `rest_api_init`
  - provides `acting_as_admin()`, `rest_get()`, `rest_post()`, and `rest_delete()` helpers
- When adding or changing an endpoint, assert:
  - the route is registered
  - permission and validation rules behave correctly
  - the response payload shape is correct
  - the expected side effects occur
- When editing `config/routes.php`, update `tests/Admin/Rest/RoutesConfigTest.php` so its expected route list stays in sync with the route DSL.

## 5. Debugging failures

- Connection errors, missing `ABSPATH`, or bootstrap failures usually mean PHPUnit is running outside Docker or before the Compose services are ready.
- Missing custom table errors usually mean the relevant schema is missing from `tests/Support/DatabaseHelpers.php` or the test skipped required setup.
- `phpunit.xml` enables `failOnWarning="true"` and `failOnRisky="true"`, so warnings and risky tests should be treated as real failures.
- If a test leaks state, make cleanup explicit in `set_up()` / `tear_down()` and reuse the existing database helpers instead of duplicating cleanup logic.

## 6. Local workflow expectations

- Keep local workflow aligned with CI: install JavaScript dependencies, install Composer dependencies, then run `make tests`.
- Run the relevant static checks for the surface you changed, such as `make phpcs`, `make phpstan`, `make lint`, or `make lint.types`.
- Update this guide whenever the test bootstrap, command entrypoints, or coverage conventions change.
