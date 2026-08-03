FROM alpine/git:2.49.1 AS source

ARG UI_REF

RUN git init /src \
    && git -C /src remote add origin https://github.com/invoiceninja/ui.git \
    && git -C /src fetch --depth 1 origin "${UI_REF}" \
    && git -C /src checkout --detach FETCH_HEAD

FROM node:22-alpine AS build

ARG VITE_API_URL
ARG TARGETARCH
ENV VITE_API_URL=${VITE_API_URL} \
    VITE_IS_HOSTED=false \
    VITE_APP_TITLE="Invoice Ninja" \
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
    CMD wget -q --spider http://127.0.0.1/ || exit 1
