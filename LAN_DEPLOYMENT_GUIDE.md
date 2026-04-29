# CaterPro LAN Deployment Guide
## Ubuntu 24.04 LTS - Complete DevOps Orchestration

**Document Version:** 2.0  
**Date:** April 28, 2026  
**Author:** Senior DevOps Engineer  
**Tech Stack:** Laravel 13, Stancl/Tenancy v3, React 19, Nginx, Redis, MySQL 8, Dnsmasq  

## LAN-FIRST RECOMMENDATION

If the goal is for multiple people on the same network to access the system, deploy on a dedicated LAN host first. That gives you the cleanest path for wildcard DNS, HTTPS trust, shared storage, Redis, and tenant-aware Nginx routing.

Use the Docker path only if you want a faster local dev-style setup or need to validate the app before wiring the LAN services.

Recommended order:
1. Reserve the server IP and verify DNS
2. Mount shared storage and prepare the Root CA
3. Run `deploy-caterpro-lan.sh`
4. Import the Root CA on client devices
5. Add the first tenant and verify access from another LAN machine

---

## QUICK RUN COMMANDS

Follow these exact copy-paste commands for the two supported deployment flows: the LAN orchestration script, or the Docker-compose alternative.

### A. Run the LAN orchestration script (recommended for full infra changes)
```bash
# copy the script to the server and run preflight
scp deploy-caterpro-lan.sh ubuntu@192.168.1.100:~/
ssh ubuntu@192.168.1.100
sudo chmod +x ~/deploy-caterpro-lan.sh
# run preflight and full deploy (non-interactive)
sudo bash ~/deploy-caterpro-lan.sh --preflight
```

If you need to rollback to a specific release (ID shown by the script), run:
```bash
sudo bash ~/deploy-caterpro-lan.sh --rollback-to-release 20260428145230
```

### B. Docker (alternative, faster local iteration)
From the repository root on your server (contains `docker-compose.yml`):
```bash
# build images and start services in detached mode
sudo docker-compose build --pull --no-cache
sudo docker-compose up -d

# watch logs
sudo docker-compose logs -f --tail=200

# to stop
sudo docker-compose down
```

### Minimal `.env` values for the deploy script (copy into `/var/www/caterpro/current/.env` before running migrations)
```ini
APP_ENV=production
APP_URL=https://central.local
DB_CONNECTION=landlord
DB_LANDLORD_DRIVER=mysql
DB_LANDLORD_HOST=127.0.0.1
DB_LANDLORD_PORT=3306
DB_LANDLORD_DATABASE=caterpro_landlord
DB_LANDLORD_USERNAME=caterpro_user
DB_LANDLORD_PASSWORD=changeme_to_secure_password

# tenant template connection (blueprint)
DB_TENANT_DRIVER=mysql
DB_TENANT_HOST=127.0.0.1
DB_TENANT_PORT=3306
DB_TENANT_DATABASE=caterpro_tenant_template
DB_TENANT_USERNAME=caterpro_user
DB_TENANT_PASSWORD=changeme_to_secure_password

# Redis via unix socket
REDIS_CLIENT=phpredis
REDIS_HOST=unix
REDIS_SOCKET=/run/redis/redis.sock
REDIS_CACHE_DB=1
```

---

## ARCHITECTURE OVERVIEW

```
┌─────────────────────────────────────────────────────────────┐
│                    CLIENT MACHINES (LAN)                      │
│           (Windows/macOS/Linux with imported rootCA.crt)      │
└────────────────────┬────────────────────────────────────────┘
                     │ HTTPS:443
                     │ *.caterpro.local
                     │
┌─────────────────────▼────────────────────────────────────────┐
│              DNSMASQ (Wildcard DNS)                            │
│              192.168.1.1 (router or separate)                  │
│              *.caterpro.local → 192.168.1.100                 │
└────────────────────┬────────────────────────────────────────┘
                     │
                     │ DNS Lookup
                     │
┌─────────────────────▼────────────────────────────────────────┐
│         UBUNTU 24.04 LTS SERVER (192.168.1.100)               │
│                                                                │
│  ┌──────────────────────────────────────────────────────┐    │
│  │           NGINX (Reverse Proxy + SSL/TLS)            │    │
│  │  - Port 443: HTTPS server                            │    │
│  │  - Regex capturing: (?<tenant>.+)\.caterpro\.local   │    │
│  │  - Passes TENANT_ID to PHP-FPM                       │    │
│  │  - Self-signed wildcard cert: *.caterpro.local      │    │
│  └──────────────┬───────────────────────────────────────┘    │
│                 │ Unix socket:/run/php/php8.3-fpm-caterpro.sock
│                 │                                              │
│  ┌──────────────▼───────────────────────────────────────┐    │
│  │         PHP-FPM 8.3 (Process Pool)                   │    │
│  │  - Pool: caterpro (dynamic, 5-20 processes)         │    │
│  │  - User: www-data                                    │    │
│  │  - Opcache enabled for performance                   │    │
│  └──────────────┬───────────────────────────────────────┘    │
│                 │ Laravel app execution                       │
│  ┌──────────────▼───────────────────────────────────────┐    │
│  │      LARAVEL 13 APP (Stancl/Tenancy v3)              │    │
│  │  - Current link: /var/www/caterpro/current           │    │
│  │  - Releases: /var/www/caterpro/releases/YYYYMMDDHHMM│    │
│  │  - Atomic deployment & rollback                      │    │
│  └──────────────┬───────────────────────────────────────┘    │
│                 │                                              │
│  ┌──────────────┴─────────────────┬──────────────────────┐    │
│  │                                │                      │    │
│  ▼                                ▼                      ▼    │
│ ┌──────────────┐      ┌──────────────────┐    ┌──────────┐   │
│ │   LANDLORD   │      │    TENANT DBS    │    │  REDIS   │   │
│ │   DB         │      │                  │    │          │   │
│ │              │      │ /mnt/caterpro... │    │ Cache    │   │
│ │ caterpro_    │      │ /tenants/        │    │ Session  │   │
│ │ landlord     │      │ {tenant_id}.sq..│    │ Queue    │   │
│ │              │      │                  │    │ Locks    │   │
│ │ (MySQL)      │      │ (SQLite files)   │    │          │   │
│ │              │      │ Synced NFS       │    │ Unix:    │   │
│ └──────────────┘      │                  │    │ /run/    │   │
│ localhost:3306        │ Isolated per     │    │ redis.   │   │
│                       │ tenant           │    │ sock     │   │
│                       └──────────────────┘    └──────────┘   │
│                                                                │
└─────────────────────────────────────────────────────────────┘
```

---

## CRITICAL PRE-DEPLOYMENT CHECKLIST

### 1. **Static IP Binding** ✓
```bash
# On router DHCP settings:
# Reserve 192.168.1.100 for your server MAC address

# Verify on server:
ip addr show
# Should show: inet 192.168.1.100/24

# If not, configure static IP:
# Ubuntu 24.04 uses Netplan
sudo nano /etc/netplan/00-installer-config.yaml
```

**Example Netplan Configuration:**
```yaml
network:
  version: 2
  ethernets:
    eth0:
      dhcp4: no
      addresses:
        - 192.168.1.100/24
      gateway4: 192.168.1.1
      nameservers:
        addresses: [8.8.8.8, 8.8.4.4]
```

Apply changes:
```bash
sudo netplan apply
```

### 2. **Trust Root CA Certificate** ✓
**MUST BE DONE BEFORE FIRST ACCESS**

The deployment script generates `/etc/ssl/caterpro/rootCA.crt` which must be imported on every client machine.

**Windows 10/11:**
1. Copy `rootCA.crt` from server to client
2. Right-click → "Open with" → "Certificate Manager"
3. Select "Local Computer" (requires admin)
4. Navigate to: **Trusted Root Certification Authorities → Certificates**
5. Right-click → **All Tasks → Import**
6. Select the `.crt` file
7. Finish

**macOS:**
```bash
# Copy cert from server first
scp ubuntu@192.168.1.100:/etc/ssl/caterpro/rootCA.crt ~/Downloads/

# Import to System Keychain
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain ~/Downloads/rootCA.crt
```

**Linux:**
```bash
# Copy cert
scp ubuntu@192.168.1.100:/etc/ssl/caterpro/rootCA.crt ~/

# Install
sudo cp ~/rootCA.crt /usr/local/share/ca-certificates/
sudo update-ca-certificates

# Verify
openssl s_client -connect central.local:443 -CApath /etc/ssl/certs
```

### 3. **Shared Storage Permissions** ✓

The NFS mount at `/mnt/caterpro_shared` must have correct permissions.

**On NFS Server (if different machine):**
```bash
# Export configuration in /etc/exports
/export/caterpro 192.168.1.0/24(rw,sync,no_subtree_check,no_root_squash)

# Reload NFS
sudo exportfs -a
sudo systemctl restart nfs-server
```

**On LAN Server:**
```bash
# Mount NFS share
sudo mkdir -p /mnt/caterpro_shared
sudo mount -t nfs 192.168.1.50:/export/caterpro /mnt/caterpro_shared

# Add to fstab for persistence
echo "192.168.1.50:/export/caterpro /mnt/caterpro_shared nfs defaults 0 0" | sudo tee -a /etc/fstab

# Verify mount
mount | grep caterpro_shared
```

---

## INSTALLATION STEPS

### Step 1: Prepare Ubuntu 24.04 LTS

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y \
  php8.3 php8.3-fpm php8.3-cli php8.3-mysql php8.3-redis \
  php8.3-json php8.3-bcmath php8.3-xml php8.3-mbstring \
  php8.3-curl php8.3-zip \
  composer npm git nginx redis-server dnsmasq openssl curl

# Enable services
sudo systemctl enable nginx redis-server dnsmasq php8.3-fpm
sudo systemctl start nginx redis-server dnsmasq php8.3-fpm

# Verify versions
php -v
composer --version
npm -v
nginx -v
redis-server --version
```

### Step 2: Obtain Source Code

**Option A: Clone from GitHub**
```bash
mkdir -p /tmp/caterpro
cd /tmp/caterpro
git clone -b main https://github.com/yourusername/caterpro.git
# or
git clone -b master https://github.com/yourusername/caterpro.git
```

**Option B: Copy from Staging**
```bash
# If source is staged locally
cp -r /path/to/caterpro /tmp/caterpro
```

### Step 3: Run Deployment Script

```bash
# Copy deployment script to server
scp deploy-caterpro-lan.sh ubuntu@192.168.1.100:~/

# SSH into server
ssh ubuntu@192.168.1.100

# Make script executable
chmod +x ~/deploy-caterpro-lan.sh

# Run with sudo (required for system configuration)
sudo bash ~/deploy-caterpro-lan.sh
```

**Expected Output:**
```
[INFO] ✓ Preflight checks passed
[INFO] ✓ SSL certificates generated
[INFO] ✓ Dnsmasq configured
[INFO] ✓ Nginx configured
[INFO] ✓ PHP-FPM configured
[INFO] ✓ Redis verified
[SUCCESS] ✓ Release deployed: 20260428145230
[SUCCESS] DEPLOYMENT SUCCESSFUL
```

### Step 4: Verify Deployment

```bash
# Check current symlink
ls -la /var/www/caterpro/current

# Check application is running
curl -s https://central.local/api -H "Accept: application/json" -k | jq .

# Check shared storage
mount | grep caterpro_shared
ls -la /mnt/caterpro_shared/

# Check Redis
redis-cli -s /run/redis/redis.sock ping

# Check PHP-FPM
ps aux | grep php8.3-fpm

# Check Nginx
sudo nginx -t
systemctl status nginx
```

---

## ENVIRONMENT SETUP

### Database Initialization

**1. Create Landlord Database:**
```bash
mysql -u root -p << 'EOF'
CREATE DATABASE caterpro_landlord CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'caterpro_user'@'localhost' IDENTIFIED BY 'changeme_to_secure_password';
GRANT ALL PRIVILEGES ON caterpro_landlord.* TO 'caterpro_user'@'localhost';
GRANT ALL PRIVILEGES ON caterpro_blueprint.* TO 'caterpro_user'@'localhost';
GRANT ALL PRIVILEGES ON caterpro_tenant_template.* TO 'caterpro_user'@'localhost';
FLUSH PRIVILEGES;
EOF
```

**2. Create Blueprint Database:**
```bash
mysql -u caterpro_user -p'changeme_to_secure_password' << 'EOF'
CREATE DATABASE caterpro_blueprint CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE caterpro_tenant_template CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EOF
```

**3. Run Initial Migrations:**
```bash
cd /var/www/caterpro/current

# Migrate landlord
php artisan migrate --database=landlord --force

# Migrate blueprint (stancl/tenancy uses this for tenant template)
php artisan migrate --database=tenant_template --force

# Seed initial data
php artisan db:seed --database=landlord --force
```

### Redis Cache Verification

```bash
# Connect to Redis
redis-cli -s /run/redis/redis.sock

# Inside redis-cli:
> PING
PONG

> CONFIG GET maxmemory
1) "maxmemory"
2) "0"

> CONFIG SET maxmemory 256mb
OK

> INFO memory
# Memory
used_memory:1234567
used_memory_human:1.18M
maxmemory:268435456

> EXIT
```

---

## FIRST TENANT PROVISIONING

### Create Central Admin User

```bash
cd /var/www/caterpro/current

# Create admin via Tinker
php artisan tinker

# Inside Tinker:
$user = App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@caterpro.local',
    'password' => bcrypt('changeme'),
]);

$user->givePermissionTo(App\Support\CentralPermissions::all());

exit
```

### Create First Test Tenant

```bash
php artisan tinker

# Inside Tinker:
$tenant = App\Models\Tenant::create([
    'id' => 'acme-corp',
    'name' => 'ACME Corporation',
    'tier' => 'premium',
]);

$domain = $tenant->domains()->create([
    'domain' => 'acme.caterpro.local',
]);

$tenant->database()->migrate();
$tenant->database()->seed();

exit
```

### Access Applications

**Central App (Admin Dashboard):**
```
https://central.local
Email: admin@caterpro.local
Password: changeme
```

**Tenant App (ACME Corp):**
```
https://acme.caterpro.local
```

---

## MAINTENANCE & OPERATIONS

### Monitor Deployment

```bash
# Tail application logs
tail -f /mnt/caterpro_shared/logs/laravel.log

# Tail PHP-FPM
sudo tail -f /mnt/caterpro_shared/logs/php-error.log

# Tail Nginx
sudo tail -f /mnt/caterpro_shared/logs/nginx_error.log

# Monitor Redis
watch -n 1 'redis-cli -s /run/redis/redis.sock info stats'
```

### Permission Cache Management

```bash
cd /var/www/caterpro/current

# Reset Spatie permission cache
php artisan permission:cache-reset

# Clear all Redis cache
redis-cli -s /run/redis/redis.sock FLUSHALL

# Clear specific tenant cache
php artisan tinker
tenancy()->initialize(tenant_id_here);
Cache::flush();
exit
```

### Database Backup

```bash
#!/bin/bash
# backup-caterpro.sh

BACKUP_DIR="/var/backups/caterpro"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p "$BACKUP_DIR"

# Backup landlord
mysqldump -u caterpro_user -p'changeme' caterpro_landlord | gzip > "$BACKUP_DIR/landlord_$DATE.sql.gz"

# Backup tenant databases
for tenant_file in /mnt/caterpro_shared/tenants/*.sqlite; do
    if [[ -f "$tenant_file" ]]; then
        cp "$tenant_file" "$BACKUP_DIR/$(basename "$tenant_file" .sqlite)_$DATE.sqlite"
    fi
done

# Backup Laravel app
tar -czf "$BACKUP_DIR/app_$DATE.tar.gz" /var/www/caterpro/current/

echo "Backup completed: $BACKUP_DIR"
```

Schedule with cron:
```bash
# Daily at 2 AM
0 2 * * * bash /usr/local/bin/backup-caterpro.sh

# Edit crontab
sudo crontab -e
```

### Rollback Procedure

```bash
# List available releases
ls -1 /var/www/caterpro/releases/

# Rollback to specific release
sudo bash /root/deploy-caterpro-lan.sh --rollback-to-release 20260428145230

# Verify rollback
curl -s https://central.local/api -H "Accept: application/json" -k | jq .
```

---

## TROUBLESHOOTING

### Issue: DNS Not Resolving

```bash
# Check Dnsmasq
sudo systemctl status dnsmasq
sudo tail -f /var/log/syslog | grep dnsmasq

# Verify Dnsmasq config
cat /etc/dnsmasq.d/99-caterpro.conf

# Restart Dnsmasq
sudo systemctl restart dnsmasq

# Test DNS resolution
nslookup central.local 127.0.0.1
nslookup acme.caterpro.local 127.0.0.1
```

### Issue: SSL Certificate Error

```bash
# Client hasn't imported root CA
# Solution: Import /etc/ssl/caterpro/rootCA.crt into client trust store

# Verify certificate
openssl x509 -in /etc/ssl/caterpro/caterpro.local.crt -noout -text

# Test HTTPS
curl -v --cacert /etc/ssl/caterpro/rootCA.crt https://central.local
```

### Issue: Permission Denied on Shared Storage

```bash
# Check NFS mount options
mount | grep caterpro_shared

# Verify permissions
ls -la /mnt/caterpro_shared/

# Fix permissions
sudo chown -R www-data:www-data /mnt/caterpro_shared
sudo chmod -R 775 /mnt/caterpro_shared
```

### Issue: PHP-FPM Socket Not Available

```bash
# Check PHP-FPM status
sudo systemctl status php8.3-fpm

# Restart PHP-FPM
sudo systemctl restart php8.3-fpm

# Verify socket exists
ls -la /run/php/php8.3-fpm-caterpro.sock

# Check PHP-FPM pool config
cat /etc/php/8.3/fpm/pool.d/caterpro.conf

# Test PHP-FPM connectivity
echo "test" | nc -U /run/php/php8.3-fpm-caterpro.sock
```

### Issue: Redis Connection Timeout

```bash
# Check Redis is running
sudo systemctl status redis-server

# Check Redis socket
ls -la /run/redis/redis.sock

# Test Redis connection
redis-cli -s /run/redis/redis.sock ping

# Verify Redis memory
redis-cli -s /run/redis/redis.sock INFO memory

# If memory full, flush old data
redis-cli -s /run/redis/redis.sock FLUSHALL
```

---

## PERFORMANCE TUNING

### PHP-FPM Optimization

```bash
# Edit /etc/php/8.3/fpm/pool.d/caterpro.conf
sudo nano /etc/php/8.3/fpm/pool.d/caterpro.conf

# Increase process pool for high load
pm.max_children = 50          # Increase from 20
pm.start_servers = 10         # Increase from 5
pm.max_spare_servers = 20     # Increase from 10

# Increase memory limits
php_admin_value[memory_limit] = 512M

# Reload
sudo systemctl reload php8.3-fpm
```

### Redis Memory Optimization

```bash
# Check current maxmemory
redis-cli -s /run/redis/redis.sock CONFIG GET maxmemory

# Set maxmemory policy (evict LRU)
redis-cli -s /run/redis/redis.sock CONFIG SET maxmemory-policy allkeys-lru
redis-cli -s /run/redis/redis.sock CONFIG REWRITE
```

### MySQL Connection Pooling

```bash
# Edit /etc/mysql/mysql.conf.d/mysqld.cnf
max_connections = 200
connect_timeout = 10
```

---

## SECURITY HARDENING

### Firewall Configuration

```bash
# Enable UFW
sudo ufw enable

# Allow SSH
sudo ufw allow 22/tcp

# Allow HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Allow DNS (local only)
sudo ufw allow from 192.168.1.0/24 to any port 53

# Deny all other traffic
sudo ufw default deny incoming
```

### Fail2Ban Protection

```bash
# Install Fail2Ban
sudo apt install fail2ban

# Create local config
sudo nano /etc/fail2ban/jail.local

# [DEFAULT]
# bantime = 3600
# findtime = 600
# maxretry = 5

# [sshd]
# enabled = true

# [nginx-http-auth]
# enabled = true

# [nginx-limit-req]
# enabled = true

# Restart Fail2Ban
sudo systemctl restart fail2ban
```

### TLS/SSL Hardening

```bash
# Update Nginx SSL ciphers for better security
# Edit /etc/nginx/sites-available/caterpro

ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384;
ssl_protocols TLSv1.2 TLSv1.3;

# Reload Nginx
sudo nginx -t && sudo systemctl reload nginx
```

---

## OUTPUT FILES

The deployment script generates:

```
/var/www/caterpro/
├── current → releases/20260428145230  (symlink)
├── releases/
│   ├── 20260428145230/
│   │   ├── .env (generated)
│   │   ├── artisan
│   │   ├── composer.json
│   │   ├── package.json
│   │   └── ...Laravel app files
│   └── 20260427120000/  (previous releases, kept for rollback)
└── backups/  (optional, for manual backups)

/etc/ssl/caterpro/
├── rootCA.key           (private key, 0600)
├── rootCA.crt           (root certificate, import on clients)
├── caterpro.local.key   (wildcard key, 0600)
└── caterpro.local.crt   (wildcard cert)

/etc/dnsmasq.d/
└── 99-caterpro.conf     (wildcard DNS config)

/etc/nginx/sites-available/
└── caterpro             (multi-tenant regex routing)

/etc/php/8.3/fpm/pool.d/
└── caterpro.conf        (PHP-FPM pool)

/mnt/caterpro_shared/
├── app/                 (symlinked from Laravel storage/app)
├── cache/               (Redis cache prefix)
├── logs/
│   ├── laravel.log
│   ├── php-error.log
│   └── nginx_error.log
├── sessions/
└── tenants/
    ├── {tenant_id_1}.sqlite
    ├── {tenant_id_2}.sqlite
    └── ...
```

---

## NEXT STEPS

1. ✓ Static IP configured (192.168.1.100)
2. ✓ Root CA imported on all clients
3. ✓ Deployment completed
4. ✓ First tenant provisioned
5. ⚠ **Set stronger database passwords** (changeme)
6. ⚠ **Configure firewall** (UFW)
7. ⚠ **Set up automated backups** (cron)
8. ⚠ **Enable HTTPS certificate renewal** (if using real certs)
9. ⚠ **Monitor system resources** (top, htop, Redis memory)
10. ⚠ **Test disaster recovery** (rollback, restore)

---

## SUPPORT & DEBUGGING

### Enable Debug Mode (Development Only)

```bash
# Edit /var/www/caterpro/current/.env
APP_DEBUG=true
LOG_LEVEL=debug

# Reload PHP-FPM
sudo systemctl reload php8.3-fpm

# Tail logs
tail -f /mnt/caterpro_shared/logs/laravel.log
```

### Run Artisan Commands

```bash
cd /var/www/caterpro/current

# Tinker shell
php artisan tinker

# Database migrations
php artisan migrate --force

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Tenant operations
php artisan tenants:list
php artisan tenants:migrate
php artisan tenants:seed
```

---

**Deployment Complete!** 🎉

Your CaterPro multi-tenant SaaS is now running on the LAN with:
- ✅ Wildcard SSL/TLS certificates
- ✅ Multi-tenant DNS routing via Dnsmasq
- ✅ Atomic deployments with rollback
- ✅ Redis-backed distributed caching
- ✅ Shared NFS storage for tenant files
- ✅ Isolated tenant databases
- ✅ Spatie RBAC with permission cache

For questions or issues, refer to the Troubleshooting section or check deployment logs.
