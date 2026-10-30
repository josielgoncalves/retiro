FROM node:22-alpine AS base
WORKDIR /app

# ---------------------------------------------------------------------------
FROM base AS dev
COPY . .
RUN npm install
EXPOSE 9000
CMD ["npx", "quasar", "dev", "-p", "9000", "-H", "0.0.0.0"]

# ---------------------------------------------------------------------------
FROM base AS build
COPY . .
RUN npm install
RUN npx quasar build

# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS prod
COPY --from=build /app/dist/spa /usr/share/nginx/html
