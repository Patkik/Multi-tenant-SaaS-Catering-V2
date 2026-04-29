FROM node:18-alpine AS builder
WORKDIR /build

# Copy frontend (assumes frontend lives in central-app/)
COPY central-app/package*.json ./central-app/
RUN cd central-app && npm ci --legacy-peer-deps

# Copy sources and build
COPY central-app ./central-app
RUN cd central-app && npm run build

# Final stage: expose build output via volume
FROM alpine:3.18
RUN mkdir -p /dist
WORKDIR /dist
COPY --from=builder /build/central-app/dist ./

CMD ["/bin/sh", "-c", "ls -la /dist && sleep infinity"]
