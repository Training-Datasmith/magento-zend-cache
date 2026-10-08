#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."
export PHP_INI_SCAN_DIR=
php -d error_reporting=-1 vendor/bin/phpunit -c phpunit.xml.dist
php -d error_reporting=-1 vendor/bin/phpunit -c phpunit.xml.dist
php -d error_reporting=-1 tests/tools/run-seeded-order.php
php -d error_reporting=-1 tests/tools/run-seeded-order.php
