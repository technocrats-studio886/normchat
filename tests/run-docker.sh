#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
# Database ini khusus tes; jangan pernah menunjuk ke container postgres produksi.
TEST_DEPS="${NORMCHAT_TEST_VENDOR:-$PWD/vendor}"
docker run --rm --network normchat_default \
  -v "$PWD:/app" -v "$TEST_DEPS:/app/vendor:ro" -w /app \
  -e APP_ENV=testing -e APP_DEBUG=true -e APP_URL=http://localhost \
  -e MARKETING_URL=http://localhost -e NORMCHAT_APP_URL=http://localhost \
  -e APP_CONFIG_CACHE=/tmp/nc-config.php -e APP_ROUTES_CACHE=/tmp/nc-routes.php \
  -e APP_PACKAGES_CACHE=/tmp/nc-packages.php -e APP_SERVICES_CACHE=/tmp/nc-services.php \
  -e VIEW_COMPILED_PATH=/tmp/nc-views -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
  -e DB_CONNECTION=pgsql -e DB_HOST=normchat-promo-test-pg -e DB_DATABASE=normchat_promo_test \
  -e DB_PORT=5432 -e DB_USERNAME=normchat_test -e DB_PASSWORD=local-test-only -e DB_URL= \
  -e CACHE_STORE=array -e SESSION_DRIVER=array -e QUEUE_CONNECTION=sync -e BROADCAST_CONNECTION=null \
  -e MAIL_MAILER=array -e FILESYSTEM_DISK=local -e NORMCHAT_STORAGE=local \
  -e NORMCHAT_STORAGE_ROOT=/tmp/nc-storage -e OPENAI_API_KEY=sk-offline-tests \
  -e OPENAI_ADMIN_KEY= -e OPENAI_PROJECT_ID= -e MIDTRANS_SERVER_KEY=offline-tests \
  -e NORMCHAT_VAPID_PUBLIC_KEY= -e NORMCHAT_VAPID_PRIVATE_KEY= \
  normchat-app sh -c 'mkdir -p /tmp/nc-storage /tmp/nc-views; exec php -d memory_limit=2G vendor/bin/phpunit --no-progress "$@"' sh "$@"
