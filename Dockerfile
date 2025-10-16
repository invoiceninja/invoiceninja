# ====== 1) استخدمي الصورة الرسمية كأساس ======
FROM invoiceninja/invoiceninja:5.12.27

# ====== 2) بيانات وصفية (اختياري لكنها احترافية) ======
LABEL org.opencontainers.image.title="InvoiceNinja (hardened wrapper)"
LABEL org.opencontainers.image.description="Security-focused wrapper around the official Invoice Ninja image to enable CI build/SBOM/scan and safer runtime defaults"
LABEL org.opencontainers.image.source="https://github.com/${GITHUB_REPOSITORY}"

# ====== 3) إنشاء مستخدم/مجموعة app بشكل متوافق مع Alpine/Debian ======
RUN set -eux; \
  if command -v addgroup >/dev/null 2>&1; then \
    # Alpine
    addgroup -g 10001 -S app || true; \
    adduser  -u 10001 -S -D -H -G app app || true; \
  else \
    # Debian/Ubuntu
    groupadd -g 10001 app || true; \
    useradd  -u 10001 -g app -s /usr/sbin/nologin -M app || true; \
  fi

# ====== 4) تأكدي من ملكية مجلدات Laravel القابلة للكتابة ======
# في لارفيل عادة storage/ و bootstrap/cache يحتاجان صلاحية كتابة.
# المسارات تختلف حسب هيكل الصورة الرسمية؛ نغطي المسارات الشائعة ونستخدم '|| true' حتى لا يفشل لو غير موجود.
RUN set -eux; \
  for d in \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache \
    /app/storage \
    /app/bootstrap/cache \
  ; do \
    if [ -d "$d" ]; then chown -R app:app "$d"; fi; \
  done

# ====== 5) التبديل إلى المستخدم غير الجذر ======
USER app

# ====== 6) متغيرات بيئة آمنة افتراضيًا للإنتاج ======
# يمكنك تعديلها لاحقًا عبر docker run -e أو في orchestrator (K8s).
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

# ====== 7) Healthcheck بسيط ======
# نتحقق أن عملية php-fpm (أو العملية الرئيسية) تعمل داخل الحاوية.
# (إن كانت الصورة الرسمية تستخدم nginx/cron بداخلها، يبقى pgrep يعطي مؤشّر حياة)
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
  CMD pgrep -x php-fpm >/dev/null || exit 1

# لا نغيّر CMD/ENTRYPOINT الخاصين بالصورة الرسمية — نرث سلوكها الافتراضي.

