#!/usr/bin/env sh
# Chạy sau git pull (hook post-merge). Build frontend trên server khi VA_AUTO_BUILD_ON_PULL=1.
set -e

CHANGED=$(git diff-tree -r --name-only --no-commit-id ORIG_HEAD HEAD 2>/dev/null || true)
if [ -z "$CHANGED" ]; then
  exit 0
fi

is_production_pull() {
  [ "$VA_AUTO_BUILD_ON_PULL" = "1" ] || [ "$NODE_ENV" = "production" ] || [ "$CI" = "true" ]
}

echo "$CHANGED" | grep -qE '^(package\.json|package-lock\.json)$' && NEED_INSTALL=1 || NEED_INSTALL=0
echo "$CHANGED" | grep -qE '^(package\.json|package-lock\.json|resources/|vite\.config|tailwind\.config|postcss\.config)' && NEED_BUILD=1 || NEED_BUILD=0

if [ "$NEED_INSTALL" = "1" ]; then
  echo "▶ package.json / lock changed — npm install..."
  if is_production_pull; then
    npm install --omit=dev || exit 1
  else
    npm install || exit 1
  fi
fi

if [ "$NEED_BUILD" = "1" ] && is_production_pull; then
  echo "▶ Frontend thay đổi — npm run build..."
  npm run build || exit 1
  echo "✓ Build xong (public/build)."
fi

exit 0
