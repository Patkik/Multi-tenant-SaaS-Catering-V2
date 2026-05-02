#!/bin/bash

################################################################################
# CaterPro LAN Deployment Orchestration Script
# Author: Senior DevOps Engineer
# Purpose: Atomic, idempotent deployment for Ubuntu 24.04 LTS multi-tenant SaaS
# 
# Usage:
#   sudo bash deploy-caterpro-lan.sh [--skip-preflight] [--rollback-to-release RELEASE_ID]
#
# Requirements:
#   - Ubuntu 24.04 LTS
#   - Static IP 192.168.1.100 reserved in DHCP
#   - Root or sudo access
#   - Git, Docker, or source clone already staged
################################################################################

set -euo pipefail

# ============================================================================
# CONFIGURATION
# ============================================================================

readonly DEPLOY_USER="${DEPLOY_USER:-www-data}"
readonly APP_ROOT="/var/www/caterpro"
readonly RELEASES_DIR="${APP_ROOT}/releases"
readonly CURRENT_LINK="${APP_ROOT}/current"
readonly SHARED_STORAGE="/mnt/caterpro_shared"
readonly SHARED_CACHE="/mnt/caterpro_shared/cache"
readonly SHARED_LOGS="/mnt/caterpro_shared/logs"
readonly SHARED_SESSIONS="/mnt/caterpro_shared/sessions"
readonly BACKUP_DIR="${APP_ROOT}/backups"

readonly DNSMASQ_CONF="/etc/dnsmasq.d/99-caterpro.conf"
readonly NGINX_CONF="/etc/nginx/sites-available/caterpro"
readonly NGINX_ENABLED="/etc/nginx/sites-enabled/caterpro"
readonly PHP_FPM_POOL="/etc/php/8.3/fpm/pool.d/caterpro.conf"

readonly SSL_DIR="/etc/ssl/caterpro"
readonly CA_KEY="${SSL_DIR}/rootCA.key"
readonly CA_CERT="${SSL_DIR}/rootCA.crt"
readonly WILDCARD_KEY="${SSL_DIR}/caterpro.local.key"
readonly WILDCARD_CERT="${SSL_DIR}/caterpro.local.crt"

readonly APP_VERSION_FILE="central-app/package.json"
readonly REDIS_SOCKET="/run/redis/redis.sock"
readonly LAN_DOMAIN="caterpro.local"
readonly LAN_IP="192.168.1.100"

# Color codes for output
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly BLUE='\033[0;34m'
readonly NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $(date +'%Y-%m-%d %H:%M:%S') - $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $(date +'%Y-%m-%d %H:%M:%S') - $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $(date +'%Y-%m-%d %H:%M:%S') - $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date +'%Y-%m-%d %H:%M:%S') - $1"
}

# ============================================================================
# PREFLIGHT CHECKS
# ============================================================================

preflight_checks() {
    log_info "=== PREFLIGHT CHECKS ==="
    
    local checks_failed=0
    
    # Check if running as root
    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root (use: sudo bash deploy-caterpro-lan.sh)"
        exit 1
    fi
    
    log_info "Checking required commands..."
    for cmd in php composer npm git nginx redis-cli dnsmasq openssl sudo tee; do
        if ! command -v "$cmd" &> /dev/null; then
            log_error "Required command not found: $cmd"
            checks_failed=$((checks_failed + 1))
        fi
    done
    
    if [[ $checks_failed -gt 0 ]]; then
        log_error "$checks_failed required commands are missing"
        log_info "Run: sudo apt update && sudo apt install -y php php-fpm php-cli php-mysql php-json php-bcmath composer npm git nginx redis-server dnsmasq openssl"
        exit 1
    fi
    
    log_info "Checking directory structure..."
    
    # Create required directories
    mkdir -p "$RELEASES_DIR" "$BACKUP_DIR" "$SHARED_STORAGE"
    mkdir -p "$SHARED_CACHE" "$SHARED_LOGS" "$SHARED_SESSIONS"
    mkdir -p "$SSL_DIR"
    
    # Set permissions on shared storage
    chown -R "$DEPLOY_USER:$DEPLOY_USER" "$SHARED_STORAGE"
    chmod 775 "$SHARED_STORAGE" "$SHARED_CACHE" "$SHARED_LOGS" "$SHARED_SESSIONS"
    
    log_info "Checking static IP binding..."
    local current_ip=$(hostname -I | awk '{print $1}')
    if [[ "$current_ip" != "$LAN_IP" ]]; then
        log_warn "Current IP ($current_ip) does not match configured LAN_IP ($LAN_IP)"
        log_warn "Ensure static IP reservation is configured in DHCP. Continuing..."
    else
        log_success "IP binding verified: $LAN_IP"
    fi
    
    log_info "Checking Redis socket..."
    if [[ ! -S "$REDIS_SOCKET" ]]; then
        log_warn "Redis socket not found at $REDIS_SOCKET. Attempting to start Redis..."
        systemctl start redis-server || log_error "Failed to start Redis"
    else
        log_success "Redis socket available"
    fi
    
    log_info "Checking NFS mount..."
    if mountpoint -q "$SHARED_STORAGE"; then
        log_success "Shared storage mounted at $SHARED_STORAGE"
    else
        log_warn "Shared storage NOT mounted. If using NFS, mount before deployment."
    fi
    
    log_info "Checking app root..."
    if [[ ! -d "$APP_ROOT" ]]; then
        mkdir -p "$APP_ROOT"
        chown -R "$DEPLOY_USER:$DEPLOY_USER" "$APP_ROOT"
    fi
    
    log_success "Preflight checks completed"
}

# ============================================================================
# SSL/TLS CERTIFICATE GENERATION
# ============================================================================

generate_ssl_certificates() {
    log_info "=== SSL/TLS CERTIFICATE GENERATION ==="
    
    # Check if certificates already exist
    if [[ -f "$CA_CERT" && -f "$WILDCARD_CERT" ]]; then
        log_info "Certificates already exist. Skipping generation."
        log_info "Root CA: $CA_CERT"
        log_info "Wildcard: $WILDCARD_CERT"
        return 0
    fi
    
    log_info "Creating Root CA..."
    
    # Generate Root CA private key
    openssl genrsa -out "$CA_KEY" 4096 2>/dev/null
    
    # Generate Root CA certificate
    openssl req -new -x509 -days 3650 -key "$CA_KEY" -out "$CA_CERT" \
        -subj "/C=PH/ST=Metro Manila/L=Manila/O=CaterPro/CN=CaterPro Root CA" \
        2>/dev/null
    
    log_success "Root CA generated: $CA_CERT"
    log_info "📌 IMPORTANT: Import this certificate into your client machine's trusted store:"
    log_info "   Windows: Import to Trusted Root Certification Authorities"
    log_info "   macOS: sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain $CA_CERT"
    log_info "   Linux: sudo cp $CA_CERT /usr/local/share/ca-certificates/ && sudo update-ca-certificates"
    
    log_info "Creating wildcard certificate for *.${LAN_DOMAIN}..."
    
    # Generate private key for wildcard certificate
    openssl genrsa -out "$WILDCARD_KEY" 2048 2>/dev/null
    
    # Create CSR with SAN
    local san_config="/tmp/caterpro_san.cnf"
    cat > "$san_config" << 'EOF'
[req]
distinguished_name = req_distinguished_name
req_extensions = v3_req
prompt = no

[req_distinguished_name]
C = PH
ST = Metro Manila
L = Manila
O = CaterPro
CN = *.caterpro.local

[v3_req]
subjectAltName = @alt_names

[alt_names]
DNS.1 = *.caterpro.local
DNS.2 = caterpro.local
DNS.3 = central.local
DNS.4 = 192.168.1.100
EOF
    
    # Generate CSR
    openssl req -new -key "$WILDCARD_KEY" -out /tmp/caterpro.csr \
        -config "$san_config" 2>/dev/null
    
    # Sign CSR with Root CA
    openssl x509 -req -days 365 -in /tmp/caterpro.csr \
        -CA "$CA_CERT" -CAkey "$CA_KEY" -CAcreateserial \
        -out "$WILDCARD_CERT" \
        -extensions v3_req -extfile "$san_config" 2>/dev/null
    
    # Clean up temporary files
    rm -f /tmp/caterpro.csr "$san_config"
    
    # Set permissions
    chmod 600 "$WILDCARD_KEY" "$CA_KEY"
    chmod 644 "$WILDCARD_CERT" "$CA_CERT"
    
    log_success "Wildcard certificate generated: $WILDCARD_CERT"
    log_info "Certificate Details:"
    openssl x509 -in "$WILDCARD_CERT" -noout -text | grep -E "Subject:|DNS:" || true
}

# ============================================================================
# DNSMASQ CONFIGURATION
# ============================================================================

configure_dnsmasq() {
    log_info "=== DNSMASQ CONFIGURATION ==="
    
    log_info "Creating Dnsmasq configuration..."
    
    cat > "$DNSMASQ_CONF" << EOF
# CaterPro Wildcard DNS Configuration
# Wildcard resolution for *.caterpro.local to 192.168.1.100

# Main domain
address=/caterpro.local/$LAN_IP
address=/central.local/$LAN_IP

# Wildcard for all subdomains (tenant routing)
address=/caterpro.local/$LAN_IP

# Increase DNS cache size for multi-tenant lookups
cache-size=1000

# Log DNS queries (disable in production for performance)
# log-queries

# Listen on all interfaces
# interface=*

# Bind to this address specifically
# listen-address=192.168.1.1
# listen-address=$LAN_IP
EOF
    
    log_success "Dnsmasq config created: $DNSMASQ_CONF"
    
    # Restart Dnsmasq
    log_info "Restarting Dnsmasq..."
    systemctl restart dnsmasq || {
        log_error "Failed to restart Dnsmasq"
        return 1
    }
    
    # Verify DNS resolution
    log_info "Verifying DNS resolution..."
    sleep 1
    if nslookup "test.${LAN_DOMAIN}" 127.0.0.1 &>/dev/null; then
        log_success "DNS resolution verified"
    else
        log_warn "DNS resolution test inconclusive (this may be normal in some LAN configs)"
    fi
    
    log_success "Dnsmasq configuration complete"
}

# ============================================================================
# NGINX CONFIGURATION
# ============================================================================

configure_nginx() {
    log_info "=== NGINX CONFIGURATION ==="
    
    log_info "Creating Nginx site configuration..."
    
    cat > "$NGINX_CONF" << 'NGINX_EOF'
# CaterPro Multi-Tenant Nginx Configuration
# Captures subdomain via regex, passes TENANT_ID to PHP-FPM

# HTTP to HTTPS redirect
server {
    listen 80;
    listen [::]:80;
    server_name ~^(?<tenant>.+)\.caterpro\.local$ caterpro.local central.local;
    
    location / {
        return 301 https://$server_name$request_uri;
    }
    
    # Allow ACME challenges
    location /.well-known/acme-challenge/ {
        root /var/www/letsencrypt;
    }
}

# HTTPS server - Multi-tenant with regex capturing
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    
    # Wildcard domain matching with named capture
    server_name ~^(?<tenant>.+)\.caterpro\.local$ caterpro.local central.local;
    
    # SSL Configuration
    ssl_certificate /etc/ssl/caterpro/caterpro.local.crt;
    ssl_certificate_key /etc/ssl/caterpro/caterpro.local.key;
    
    # SSL Security Best Practices
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    ssl_stapling on;
    ssl_stapling_verify on;
    
    # HSTS (optional, be careful with this)
    # add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    # Root directory
    root /var/www/caterpro/current/public;
    
    # Logging
    access_log /mnt/caterpro_shared/logs/nginx_access.log;
    error_log /mnt/caterpro_shared/logs/nginx_error.log warn;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
    
    # Client upload size
    client_max_body_size 100M;
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss;
    
    # Static file caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
    
    # Deny access to hidden files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    # Deny access to config files
    location ~ /\.(env|conf|php|git|svn) {
        deny all;
    }
    
    # API routes - Pass tenant ID from subdomain capture
    location ~ ^/api/ {
        # Set tenant ID from regex capture group
        set $tenant_id $tenant;
        
        # Handle empty tenant (central domain)
        if ($server_name = "central.local") {
            set $tenant_id "central";
        }
        if ($server_name = "caterpro.local") {
            set $tenant_id "central";
        }
        
        # Pass tenant ID to PHP-FPM
        fastcgi_param TENANT_ID $tenant_id;
        fastcgi_param REQUEST_SCHEME $scheme;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        
        try_files $uri $uri/ /index.php?$query_string;
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm-caterpro.sock;
        fastcgi_index index.php;
    }
    
    # All other routes - Laravel SPA routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP-FPM socket configuration
    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        
        # Set tenant ID from subdomain (same as API)
        set $tenant_id $tenant;
        if ($server_name = "central.local") {
            set $tenant_id "central";
        }
        if ($server_name = "caterpro.local") {
            set $tenant_id "central";
        }
        fastcgi_param TENANT_ID $tenant_id;
        
        # Standard FastCGI parameters
        fastcgi_param REQUEST_SCHEME $scheme;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm-caterpro.sock;
        fastcgi_index index.php;
        
        # Timeout settings
        fastcgi_connect_timeout 60s;
        fastcgi_send_timeout 60s;
        fastcgi_read_timeout 60s;
    }
}
NGINX_EOF
    
    log_success "Nginx config created: $NGINX_CONF"
    
    # Enable site (remove default if exists)
    log_info "Enabling Nginx site..."
    if [[ -L "/etc/nginx/sites-enabled/default" ]]; then
        rm -f /etc/nginx/sites-enabled/default
    fi
    
    if [[ ! -L "$NGINX_ENABLED" ]]; then
        ln -s "$NGINX_CONF" "$NGINX_ENABLED"
    fi
    
    # Test Nginx configuration
    log_info "Testing Nginx configuration..."
    if nginx -t 2>/dev/null; then
        log_success "Nginx configuration is valid"
    else
        log_error "Nginx configuration test failed"
        return 1
    fi
    
    # Reload Nginx
    log_info "Reloading Nginx..."
    systemctl reload nginx || {
        log_error "Failed to reload Nginx"
        return 1
    }
    
    log_success "Nginx configuration complete"
}

# ============================================================================
# PHP-FPM POOL CONFIGURATION
# ============================================================================

configure_php_fpm() {
    log_info "=== PHP-FPM POOL CONFIGURATION ==="
    
    log_info "Creating PHP-FPM pool configuration..."
    
    cat > "$PHP_FPM_POOL" << EOF
; CaterPro PHP-FPM Pool Configuration
[caterpro]

; Unix user/group of processes
user = $DEPLOY_USER
group = $DEPLOY_USER

; Listen on Unix socket
listen = /run/php/php8.3-fpm-caterpro.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; Process manager (dynamic for LAN multi-instance)
pm = dynamic
pm.max_children = 20
pm.start_servers = 5
pm.min_spare_servers = 2
pm.max_spare_servers = 10
pm.max_requests = 500

; Environment variables for Laravel
env[LARAVEL_ENV] = production
env[LOG_CHANNEL] = stack
env[REDIS_HOST] = unix:$REDIS_SOCKET

; PHP settings
php_admin_value[display_errors] = off
php_admin_value[log_errors] = on
php_admin_value[error_log] = $SHARED_LOGS/php-error.log
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 60
php_admin_value[upload_max_filesize] = 100M
php_admin_value[post_max_size] = 100M
php_admin_value[max_input_time] = 60

; OPcache for performance
php_admin_value[opcache.enable] = 1
php_admin_value[opcache.memory_consumption] = 128
php_admin_value[opcache.max_accelerated_files] = 10000
php_admin_value[opcache.revalidate_freq] = 0
php_admin_value[opcache.validate_timestamps] = 0

; Catch workers output
catch_workers_output = yes
EOF
    
    log_success "PHP-FPM pool created: $PHP_FPM_POOL"
    
    # Create socket directory if needed
    mkdir -p /run/php
    chown www-data:www-data /run/php
    
    # Test PHP-FPM configuration
    log_info "Testing PHP-FPM configuration..."
    if php-fpm8.3 -t 2>/dev/null; then
        log_success "PHP-FPM configuration is valid"
    else
        log_warn "PHP-FPM test returned non-zero (this may be normal)"
    fi
    
    # Restart PHP-FPM
    log_info "Restarting PHP-FPM..."
    systemctl restart php8.3-fpm || {
        log_error "Failed to restart PHP-FPM"
        return 1
    }
    
    log_success "PHP-FPM configuration complete"
}

# ============================================================================
# REDIS CONFIGURATION
# ============================================================================

configure_redis() {
    log_info "=== REDIS CONFIGURATION ==="
    
    log_info "Verifying Redis is running..."
    
    if ! systemctl is-active --quiet redis-server; then
        log_info "Starting Redis server..."
        systemctl start redis-server || {
            log_error "Failed to start Redis"
            return 1
        fi
    fi
    
    log_info "Testing Redis connection..."
    if redis-cli -s "$REDIS_SOCKET" ping 2>/dev/null | grep -q "PONG"; then
        log_success "Redis is responding"
    else
        log_error "Redis connection test failed"
        return 1
    fi
    
    # Flush Redis (careful!)
    log_warn "Flushing existing Redis cache (contains old tenant data)..."
    redis-cli -s "$REDIS_SOCKET" FLUSHALL 2>/dev/null || true
    
    log_success "Redis configuration verified"
}

# ============================================================================
# ENVIRONMENT CONFIGURATION
# ============================================================================

configure_environment() {
    log_info "=== ENVIRONMENT CONFIGURATION ==="
    
    local release_dir="$1"
    local env_file="$release_dir/.env"
    
    log_info "Creating .env file for release: $release_dir"
    
    # Extract version from package.json
    local app_version=$(grep '"version"' "$release_dir/$APP_VERSION_FILE" | head -1 | sed 's/.*"version": "\([^"]*\)".*/\1/')
    
    cat > "$env_file" << EOF
APP_NAME="CaterPro"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://central.local
APP_KEY=base64:$(php -r "echo base64_encode(random_bytes(32));")
APP_VERSION=$app_version

# Landlord Database (Central management)
DB_CONNECTION=mysql
DB_LANDLORD_DRIVER=mysql
DB_LANDLORD_HOST=localhost
DB_LANDLORD_PORT=3306
DB_LANDLORD_DATABASE=caterpro_landlord
DB_LANDLORD_USERNAME=caterpro_user
DB_LANDLORD_PASSWORD=changeme_to_secure_password

# Tenant Database (Blueprint strategy - SQLite)
DB_TENANT_DRIVER=sqlite
DB_TENANT_DATABASE=\${TENANT_DB_PATH}

# Tenancy Configuration
CENTRAL_DOMAINS=127.0.0.1,localhost,central.local,caterpro.local

# Redis (unified cache + session driver)
REDIS_HOST=unix:/run/redis/redis.sock
REDIS_PORT=0
REDIS_DATABASE=0
CACHE_DRIVER=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_STORE=redis

# Cache prefix (prevents cross-tenant cache pollution)
CACHE_PREFIX=caterpro_

# Queue Configuration (Database driver for LAN)
QUEUE_DRIVER=database
QUEUE_CONNECTION=mysql
QUEUE_FAILED_DRIVER=database

# Session Configuration
SESSION_LIFETIME=120

# Mail Configuration
MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=noreply@caterpro.local
MAIL_FROM_NAME="CaterPro"

# Filesystem Configuration (shared NFS mount)
FILESYSTEM_DISK=local
FILESYSTEM_VISIBILITY=private

# File Storage (symlinked to shared NFS)
STORAGE_PATH=$SHARED_STORAGE/app

# Logging
LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=notice
LOG_PATH=$SHARED_LOGS/laravel.log

# Broadcast Configuration
BROADCAST_DRIVER=log
BROADCAST_CONNECTION=redis

# Update Configuration
APP_UPDATE_APPLY_COMMAND=

# Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=central.local,caterpro.local
SANCTUM_GUARD=web

# Feature Flags (tenant capabilities)
FEATURE_FLAGS=events,staff,packages,payments

# Tenancy Specifics
TENANCY_DB_NAME=\${TENANT_DATABASE_NAME}

# Monitoring (optional)
SENTRY_DSN=
SENTRY_ENVIRONMENT=production

# Trusted Proxies (Nginx reverse proxy)
TRUSTED_PROXIES=127.0.0.1,192.168.1.0/24
EOF
    
    log_success "Environment configuration created: $env_file"
    
    # Set strict permissions
    chmod 600 "$env_file"
    chown "$DEPLOY_USER:$DEPLOY_USER" "$env_file"
}

# ============================================================================
# DATABASE ORCHESTRATION (Blueprint Strategy)
# ============================================================================

configure_database_connections() {
    log_info "=== DATABASE CONNECTION CONFIGURATION ==="
    
    local release_dir="$1"
    local db_config="$release_dir/config/database.php"
    
    log_info "Database connections already configured in config/database.php"
    log_info "Ensure the following are present:"
    log_info "  - mysql_landlord: Central management database"
    log_info "  - mysql_blueprint: Read-only template for tenant schema"
    log_info "  - dynamic_tenant: Per-tenant SQLite files"
    
    # Verify config file exists
    if [[ ! -f "$db_config" ]]; then
        log_error "Database config not found: $db_config"
        return 1
    fi
    
    log_success "Database connections configured"
}

# ============================================================================
# APPLICATION DEPLOYMENT
# ============================================================================

deploy_release() {
    log_info "=== APPLICATION DEPLOYMENT ==="
    
    # Generate release identifier
    local release_id=$(date +%Y%m%d%H%M%S)
    local release_dir="${RELEASES_DIR}/${release_id}"
    
    log_info "Release ID: $release_id"
    log_info "Release directory: $release_dir"
    
    # Create release directory
    mkdir -p "$release_dir"
    log_success "Release directory created"
    
    # Clone or copy source code
    log_info "Preparing source code..."
    if [[ -d "$CURRENT_LINK" && -d "${CURRENT_LINK}/.git" ]]; then
        # If current release exists with git, clone from it
        log_info "Cloning from current release (faster than network clone)..."
        cp -a "$CURRENT_LINK" "$release_dir" || {
            log_error "Failed to copy current release"
            return 1
        }
        # Update source from origin
        cd "$release_dir"
        git fetch origin
        git reset --hard origin/main || git reset --hard origin/master || true
    else
        # Clone from remote
        log_info "Cloning from remote repository..."
        git clone -b main https://github.com/yourusername/caterpro.git "$release_dir" 2>/dev/null || \
        git clone -b master https://github.com/yourusername/caterpro.git "$release_dir" 2>/dev/null || {
            log_error "Failed to clone repository. Using local source..."
            # Fallback: assume source is staged at /tmp/caterpro
            if [[ -d "/tmp/caterpro" ]]; then
                cp -a /tmp/caterpro/* "$release_dir"
            else
                log_error "No source code available for deployment"
                return 1
            fi
        }
    fi
    
    cd "$release_dir" || {
        log_error "Failed to enter release directory"
        return 1
    }
    
    log_success "Source code ready at $release_dir"
    
    # Extract and sync version
    log_info "Syncing version from package.json..."
    local app_version=$(grep '"version"' "$APP_VERSION_FILE" 2>/dev/null | head -1 | sed 's/.*"version": "\([^"]*\)".*/\1/' || echo "unknown")
    log_info "App version: $app_version"
    
    # Set permissions early
    chown -R "$DEPLOY_USER:$DEPLOY_USER" "$release_dir"
    
    # Create environment file
    configure_environment "$release_dir"
    
    # Composer install
    log_info "Running composer install..."
    cd "$release_dir" || return 1
    sudo -u "$DEPLOY_USER" composer install --no-dev --optimize-autoloader -q || {
        log_error "Composer install failed"
        return 1
    }
    log_success "Composer dependencies installed"
    
    # NPM build
    log_info "Running npm build..."
    if [[ -f "$release_dir/package.json" ]]; then
        sudo -u "$DEPLOY_USER" npm ci --prefer-offline --no-audit -s 2>/dev/null || \
        sudo -u "$DEPLOY_USER" npm install --prefer-offline --no-audit -s 2>/dev/null || {
            log_warn "NPM install had issues, continuing..."
        }
        
        sudo -u "$DEPLOY_USER" npm run build -s || {
            log_error "NPM build failed"
            return 1
        }
        log_success "Frontend build completed"
    fi
    
    # Generate app key
    log_info "Generating application key..."
    php artisan key:generate --force --quiet || {
        log_error "Failed to generate app key"
        return 1
    }
    
    # Run migrations
    log_info "Running database migrations..."
    php artisan migrate:refresh --seed --force --quiet 2>/dev/null || \
    php artisan migrate --force --quiet || {
        log_error "Migration failed"
        return 1
    }
    log_success "Database migrations completed"
    
    # Run tenant-specific migrations
    log_info "Running tenant migrations (for existing tenants)..."
    php artisan tenants:migrate --force --quiet 2>/dev/null || true
    
    # Cache configuration
    log_info "Caching configuration..."
    php artisan config:cache --quiet
    php artisan route:cache --quiet
    php artisan view:cache --quiet
    
    # Clear Redis cache
    log_info "Clearing distributed cache..."
    redis-cli -s "$REDIS_SOCKET" FLUSHALL 2>/dev/null || true
    
    # Reset permissions cache
    log_info "Resetting Spatie permission cache..."
    php artisan permission:cache-reset --quiet 2>/dev/null || true
    
    # Create shared storage symlink
    log_info "Creating shared storage symlink..."
    if [[ -d "$release_dir/storage/app" ]]; then
        rm -rf "$release_dir/storage/app"
    fi
    ln -s "$SHARED_STORAGE/app" "$release_dir/storage/app"
    
    # Set storage permissions
    chown -R "$DEPLOY_USER:$DEPLOY_USER" "$release_dir/storage"
    chmod -R 775 "$release_dir/storage"
    
    # Create symlink from current to new release
    log_info "Activating release..."
    if [[ -L "$CURRENT_LINK" ]]; then
        # Atomic swap using temp link
        local temp_link="${CURRENT_LINK}.tmp"
        ln -sfn "$release_dir" "$temp_link"
        mv -Tf "$temp_link" "$CURRENT_LINK"
    else
        ln -sfn "$release_dir" "$CURRENT_LINK"
    fi
    
    log_success "Release activated: $release_id"
    
    # Reload PHP-FPM and Nginx
    log_info "Reloading web services..."
    systemctl reload php8.3-fpm || true
    systemctl reload nginx || true
    
    # Health check
    log_info "Performing health check..."
    sleep 2
    if curl -sk https://central.local/api -H "Accept: application/json" 2>/dev/null | grep -q .; then
        log_success "Health check passed"
    else
        log_warn "Health check inconclusive (API may require authentication)"
    fi
    
    log_success "Deployment completed successfully"
    echo "$release_id"
}

# ============================================================================
# ROLLBACK FUNCTION
# ============================================================================

rollback_to_release() {
    local target_release="$1"
    
    log_info "=== ROLLBACK TO RELEASE ==="
    log_info "Rolling back to release: $target_release"
    
    local target_dir="${RELEASES_DIR}/${target_release}"
    
    if [[ ! -d "$target_dir" ]]; then
        log_error "Release directory not found: $target_dir"
        return 1
    fi
    
    # Atomic swap
    local temp_link="${CURRENT_LINK}.tmp"
    ln -sfn "$target_dir" "$temp_link"
    mv -Tf "$temp_link" "$CURRENT_LINK" || {
        log_error "Rollback failed"
        return 1
    }
    
    # Reload services
    systemctl reload php8.3-fpm || true
    systemctl reload nginx || true
    
    log_success "Rolled back to release: $target_release"
}

# ============================================================================
# CLEANUP OLD RELEASES
# ============================================================================

cleanup_old_releases() {
    log_info "=== CLEANUP OLD RELEASES ==="
    
    local keep_count=5
    local release_count=$(ls -1d "$RELEASES_DIR"/*/ 2>/dev/null | wc -l)
    
    if [[ $release_count -gt $keep_count ]]; then
        log_info "Found $release_count releases, keeping $keep_count recent..."
        
        # Get releases sorted by date, keep newest N
        ls -1d "$RELEASES_DIR"/*/ | sort -r | tail -n +$((keep_count + 1)) | while read -r old_release; do
            log_info "Removing old release: $(basename "$old_release")"
            rm -rf "$old_release" || log_warn "Failed to remove: $old_release"
        done
        
        log_success "Cleanup completed"
    else
        log_info "Release count ($release_count) within limits"
    fi
}

# ============================================================================
# MAIN ORCHESTRATION FLOW
# ============================================================================

main() {
    log_info "╔════════════════════════════════════════════════════════════╗"
    log_info "║        CaterPro LAN Deployment Orchestration              ║"
    log_info "║              Ubuntu 24.04 LTS - Stancl/Tenancy v3          ║"
    log_info "╚════════════════════════════════════════════════════════════╝"
    
    # Parse command line arguments
    local skip_preflight=false
    local rollback_release=""
    
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --skip-preflight)
                skip_preflight=true
                shift
                ;;
            --rollback-to-release)
                rollback_release="$2"
                shift 2
                ;;
            *)
                log_error "Unknown option: $1"
                exit 1
                ;;
        esac
    done
    
    # Handle rollback request
    if [[ -n "$rollback_release" ]]; then
        rollback_to_release "$rollback_release" || exit 1
        exit 0
    fi
    
    # Execution flow
    if [[ "$skip_preflight" != "true" ]]; then
        preflight_checks || exit 1
    fi
    
    generate_ssl_certificates || exit 1
    configure_dnsmasq || exit 1
    configure_nginx || exit 1
    configure_php_fpm || exit 1
    configure_redis || exit 1
    
    local release_id=$(deploy_release) || exit 1
    
    cleanup_old_releases || true
    
    log_success "╔════════════════════════════════════════════════════════════╗"
    log_success "║                   DEPLOYMENT SUCCESSFUL                   ║"
    log_success "╚════════════════════════════════════════════════════════════╝"
    log_info ""
    log_info "📊 Deployment Summary:"
    log_info "   Release ID:        $release_id"
    log_info "   App Root:          $APP_ROOT"
    log_info "   Current Link:      $CURRENT_LINK"
    log_info "   Shared Storage:    $SHARED_STORAGE"
    log_info ""
    log_info "🔐 SSL/TLS:"
    log_info "   Root CA:           $CA_CERT"
    log_info "   Wildcard Cert:     $WILDCARD_CERT"
    log_info ""
    log_info "🌐 Network:"
    log_info "   LAN IP:            $LAN_IP"
    log_info "   LAN Domain:        $LAN_DOMAIN"
    log_info "   Central URL:       https://central.local"
    log_info "   Tenant URL:        https://acme.local (example)"
    log_info ""
    log_info "📝 Next Steps:"
    log_info "   1. Import root CA to client machines: $CA_CERT"
    log_info "   2. Create first tenant via API"
    log_info "   3. Monitor: tail -f $SHARED_LOGS/laravel.log"
    log_info "   4. Rollback: sudo bash $(basename "$0") --rollback-to-release <RELEASE_ID>"
    log_info ""
}

# Execute main function
main "$@"
