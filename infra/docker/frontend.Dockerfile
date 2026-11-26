# Estágio 1: Build do Vue 3 + TypeScript
FROM node:22-alpine AS builder

WORKDIR /app

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ .

# Garante que a URL da API fique vazia para usar o mesmo domínio/porta do Nginx
ENV VITE_API_URL=
RUN npm run build

# Estágio 2: Servidor Nginx final
FROM nginx:1.27-alpine

# Remove configuração padrão e aplica a nossa
RUN rm -f /etc/nginx/conf.d/default.conf
COPY infra/nginx/default.conf /etc/nginx/conf.d/default.conf

# Copia os arquivos compilados (dist/)
COPY --from=builder /app/dist /usr/share/nginx/html

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]