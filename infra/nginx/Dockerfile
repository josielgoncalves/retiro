# Build context: repository root.
FROM node:22-alpine AS web-build
WORKDIR /app
COPY apps/web/ ./
RUN npm install
RUN npx quasar build

FROM nginx:1.27-alpine AS prod
COPY --from=web-build /app/dist/spa /usr/share/nginx/html
COPY infra/nginx/prod.conf /etc/nginx/conf.d/default.conf
