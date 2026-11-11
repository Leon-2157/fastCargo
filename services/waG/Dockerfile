FROM node:24-alpine AS frontend

WORKDIR /src
COPY package.json package-lock.json tailwind.config.js ./
COPY scripts ./scripts
COPY web ./web
COPY internal/dashboard/embedded ./internal/dashboard/embedded
RUN npm ci --ignore-scripts && npm run build

FROM golang:1.27-alpine AS builder

WORKDIR /src
RUN apk add --no-cache ca-certificates git
COPY go.mod go.sum ./
RUN go mod download
COPY cmd ./cmd
COPY internal ./internal
COPY --from=frontend /src/internal/dashboard/embedded/assets ./internal/dashboard/embedded/assets
COPY knowledge_base.md ./knowledge_base.md
RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" -o /out/wa-gateway ./cmd/wa-gateway

FROM alpine:3.24

RUN apk add --no-cache ca-certificates tzdata \
    && addgroup -S -g 1000 app \
    && adduser -S -D -H -u 1000 -G app app \
    && mkdir -p /app/storage \
    && chown -R 1000:1000 /app

WORKDIR /app
COPY --from=builder --chown=1000:1000 /out/wa-gateway /app/wa-gateway
COPY --from=builder --chown=1000:1000 /src/knowledge_base.md /app/knowledge_base.md

USER 1000:1000
EXPOSE 3000
VOLUME ["/app/storage"]
ENTRYPOINT ["/app/wa-gateway"]
