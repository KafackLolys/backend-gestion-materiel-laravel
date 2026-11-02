FROM dunglas/frankenphp:1-php8.3-alpine

# Configuration recommandée pour la production
ENV FRANKENPHP_CONFIG="worker ./public/index.php"

# Installation des extensions PHP requises (dont pdo_pgsql pour Supabase)
RUN install-php-extensions \
    pcntl \
    pdo_pgsql \
    intl \
    zip \
    opcache

COPY . /app

# Définition du répertoire de travail
WORKDIR /app

# Vercel injecte dynamiquement le port via la variable PORT
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--listen", ":$PORT"]
