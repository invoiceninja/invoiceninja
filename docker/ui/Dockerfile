FROM alpine/git:2.49.1 AS source

ARG UI_REF

RUN git init /src \
    && git -C /src remote add origin https://github.com/invoiceninja/ui.git \
    && git -C /src fetch --depth 1 origin "${UI_REF}" \
    && git -C /src checkout --detach FETCH_HEAD \
    && sed -i 's#<Router>#<Router basename="/control">#' /src/src/index.tsx \
    && grep -Fqx "    window.location.href = '/';" /src/src/pages/authentication/Logout.tsx \
    && sed -i "s#^    window\.location\.href = '/';\$#    window.location.href = '/control/';#" /src/src/pages/authentication/Logout.tsx \
    && grep -Fqx "    window.location.href = '/control/';" /src/src/pages/authentication/Logout.tsx \
    && sed -i 's#<title>Invoice Ninja</title>#<title>File4net Billing</title>#' /src/index.html \
    && sed -i 's|content="#000000"|content="#061F4A"|' /src/index.html \
    && sed -i 's#Leading free invoice generator for freelancers and small businesses\. Invoice clients, accept payments, track expenses &amp; time billable-tasks online\.#File4net billing and account management portal.#' /src/index.html \
    && sed -i 's|<link rel="icon" href="/favicon.ico" />|<link rel="icon" href="/favicon.ico" sizes="any" />\n    <link rel="icon" href="/favicon.svg" type="image/svg+xml" />\n    <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#08A9C2" />\n    <meta name="msapplication-config" content="/browserconfig.xml" />|' /src/index.html \
    && sed -i 's#Invoice Ninja Logo#File4net logo#g' \
        /src/src/pages/authentication/components/Header.tsx \
        /src/src/components/NotFound.tsx \
    && sed -i "s#^import Logo from .*#import Logo from '../../../resources/images/file4net-logo.png';#" \
        /src/src/pages/authentication/components/Header.tsx \
    && sed -i "s#^import Logo from .*#import Logo from '../resources/images/file4net-logo.png';#" \
        /src/src/components/NotFound.tsx \
    && sed -i "s#^import DefaultLogo from .*#import DefaultLogo from '../../resources/images/file4net-logo-dark.png';#" \
        /src/src/common/constants/company-settings.ts \
    && find /src/src/resources/images -maxdepth 1 -type f -delete

COPY brand/logo-primary-400w.png /src/src/resources/images/file4net-logo.png
COPY brand/logo-primary-dark-400w.png /src/src/resources/images/file4net-logo-dark.png
COPY brand/favicon.ico /src/public/favicon.ico
COPY brand/favicon.svg /src/public/favicon.svg
COPY brand/apple-touch-icon.png /src/public/logo180.png
COPY brand/android-chrome-192x192.png /src/public/android-chrome-192x192.png
COPY brand/android-chrome-512x512.png /src/public/android-chrome-512x512.png
COPY brand/maskable-icon-512x512.png /src/public/maskable-icon-512x512.png
COPY brand/mstile-150x150.png /src/public/mstile-150x150.png
COPY brand/safari-pinned-tab.svg /src/public/safari-pinned-tab.svg
COPY brand/browserconfig.xml /src/public/browserconfig.xml
COPY brand/manifest.json /src/public/manifest.json

FROM node:22-alpine AS build

ARG VITE_API_URL
ARG TARGETARCH
ENV VITE_API_URL=${VITE_API_URL} \
    VITE_IS_HOSTED=false \
    VITE_APP_TITLE="File4net Billing" \
    VITE_ROUTER=browser

WORKDIR /src

COPY --from=source /src/ ./

RUN npm ci --ignore-scripts

RUN rollup_version="$(node -p "require('./node_modules/rollup/package.json').version")" \
    && case "${TARGETARCH}" in \
        amd64) rollup_arch=x64 ;; \
        arm64) rollup_arch=arm64 ;; \
        *) echo "Unsupported architecture: ${TARGETARCH}" >&2; exit 1 ;; \
    esac \
    && package_name="rollup-linux-${rollup_arch}-musl" \
    && package_file="$(npm pack --silent "@rollup/${package_name}@${rollup_version}")" \
    && mkdir -p "node_modules/@rollup/${package_name}" \
    && tar -xzf "${package_file}" --strip-components=1 \
        -C "node_modules/@rollup/${package_name}" \
    && rm "${package_file}" \
    && esbuild_version="$(node -p "require('./node_modules/esbuild/package.json').version")" \
    && package_name="linux-${rollup_arch}" \
    && package_file="$(npm pack --silent "@esbuild/${package_name}@${esbuild_version}")" \
    && mkdir -p "node_modules/@esbuild/${package_name}" \
    && tar -xzf "${package_file}" --strip-components=1 \
        -C "node_modules/@esbuild/${package_name}" \
    && rm "${package_file}"

RUN npm run build

FROM nginx:1.29-alpine

COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /src/dist/ /usr/share/nginx/html/

HEALTHCHECK --interval=10s --timeout=3s --retries=10 \
    CMD wget -q --spider http://127.0.0.1/healthz || exit 1
