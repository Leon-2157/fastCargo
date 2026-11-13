FROM golang:1.27-alpine AS builder

WORKDIR /src
RUN apk add --no-cache ca-certificates git
COPY go.mod go.sum ./
RUN go mod download
COPY cmd ./cmd
COPY internal ./internal
RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" -o /out/payg ./cmd/payg

FROM alpine:3.24

RUN apk add --no-cache ca-certificates tzdata \
    && addgroup -S -g 1000 app \
    && adduser -S -D -H -u 1000 -G app app \
    && mkdir -p /app/storage \
    && chown -R 1000:1000 /app

WORKDIR /app
COPY --from=builder --chown=1000:1000 /out/payg /app/payg

USER 1000:1000
EXPOSE 8085
VOLUME ["/app/storage"]
ENTRYPOINT ["/app/payg"]
