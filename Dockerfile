FROM php:8.4.15-cli

WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    php -r "unlink('composer-setup.php');" && \
    composer --version

# Copy composer files and install dependencies
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader

# Copy application code
COPY app.php ./
COPY src/ ./src/

# Create non-root user
RUN useradd -m -u 1000 worker && chown -R worker:worker /app
USER worker

# Environment variables for Redis configuration
ENV REDIS_HOST=localhost \
    REDIS_PORT=6379

LABEL maintainer="wrspada02" \
      description="PHP worker to consume redis queue items"

CMD ["php", "./app.php"]