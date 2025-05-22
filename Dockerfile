# Сборка
FROM node:18-alpine AS build
WORKDIR /app
COPY package*.json vite.config.js ./
COPY resources ./resources
RUN npm install && npm run build

# Продакшн
FROM nginx:stable-alpine
COPY --from=build /app/public/build /usr/share/nginx/html
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
