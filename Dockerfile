# ===== Dockerfile: Hardened wrapper on top of official InvoiceNinja image =====
# هدف هذا الملف: استخدام الصورة الرسمية كأساس، ثم تطبيق تحسينات أمنية عملية
# (patch OS packages, non-root runtime, healthcheck, labels) حتى يعمل CI (SBOM/Trivy) ويعطينا نتيجة مفيدة.

# ---------- 1) Base image (مثبت بالإصدار لتكرارية البناء) ----------
FROM invoiceninja/invoiceninja:5.12.28

# ---------- 1.1) Ensure we run as root for system updates (some base images may already use non-root) ----------
USER root

# ---------- 1.2) OS-level patches (Alpine or Debian compatible) ----------
# - نقوم بترقية الحزم بأمان حسب مدير الحزم في الصورة: apk (Alpine) أو apt-get (Debian/Ubuntu).
# - نستخدم أوامر مرنة مع fallbacks حتى لا يفشل البناء على صور غير متوقعة.
RUN set -eux; \
  if command -v apk >/dev/null 2>&1; then \
    # Alpine: نحدّث قاعدة الحزم ثم نرفع الحزم المثبّتة؛ هذا يقلّل ثغرات المكتبات الشائعة.
    apk update; \
    apk upgrade --no-cache || true; \
    # حاول ترقية الحزم المعروفة (إن كانت متوفرة في repo)
    apk add --no-cache --upgrade libxml2 sqlite-libs || true; \
  elif command -v apt-get >/dev/null 2>&1; then \
    # Debian/Ubuntu: نحدّث القوائم ونرفع الحزم (non-interactive)
    export DEBIAN_FRONTEND=noninteractive; \
    apt-get update; \
    apt-get -y upgrade --no-install-recommends || true; \
    # نثبّت/نرتقي الحزم المعنية إن لزم (أسماء الحزم قد تختلف قليلاً)
    apt-get install -y --no-install-recommends libxml2 sqlite3 || true; \
    rm -rf /var/lib/apt/lists/*; \
  else \
    echo "No apk/apt-get found: skipping OS patch step"; \
  fi

# ---------- 2) Metadata labels (احترافية، مفيدة للـ registry وSBOM) ----------
LABEL org.opencontainers.image.title="InvoiceNinja (hardened wrapper)"
LABEL org.opencontainers.image.description="Security-focused wrapper around the official Invoice Ninja image — applies OS patches, runs as non-root, adds healthcheck and proper writable permissions for Laravel."
LABEL org.opencontainers.image.source="https://github.com/${GITHUB_REPOSITORY}"

# ---------- 3) Create non-root user/group in a portable way (Alpine & Debian compatible) ----------
# - نستخدم UID/GID ثابتين لتسهيل التعامل مع volumes وCI.
RUN set -eux; \
  if command -v addgroup >/dev/null 2>&1 && command -v adduser >/dev/null 2>&1; then \
    # likely Alpine (busybox adduser/addgroup)
    addgroup -g 10001 -S app || true; \
    adduser -u 10001 -S -D -H -G app app || true; \
  else \
    # fallback for Debian/Ubuntu (groupadd/useradd)
    groupadd -g 10001 app 2>/dev/null || true; \
    useradd -u 10001 -g app -s /usr/sbin/nologin -M app 2>/dev/null || true; \
  fi

# ---------- 4) Ensure Laravel writable dirs are owned by the runtime user ----------
# - نغطي المسارات الشائعة في صور Laravel/InvoiceNinja. الأمر لن يفشل إن لم تكن هذه المجلدات موجودة.
RUN set -eux; \
  for d in /var/www/html/storage /var/www/html/bootstrap/cache /app/storage /app/bootstrap/cache; do \
    if [ -d "$d" ]; then chown -R 10001:10001 "$d"; fi; \
  done

# ---------- 5) Switch to the non-root runtime user ----------
USER app
WORKDIR /var/www/html

# ---------- 6) Safe default environment for production (يمكن تجاوزه وقت التشغيل) ----------
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

# ---------- 7) Lightweight healthcheck (يتحقق من وجود عملية php-fpm) ----------
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
  CMD pgrep -x php-fpm >/dev/null || exit 1

# ---------- ملاحظة نهائية ----------
# لا نغيّر ENTRYPOINT/CMD الافتراضي للصورة الرسمية — نرث سلوك التشغيل والإقلاع كما هو.
# هذا يحافظ على التوافق مع تكوين المشروع الأصلي، بينما نضيف صرامة أمنية بسيطة.

