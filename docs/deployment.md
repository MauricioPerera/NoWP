# Deployment Guide

Complete guide for deploying the WordPress Alternative Framework to production environments.

## System Requirements

### Minimum Requirements

- **PHP**: 8.1 or higher
- **MySQL**: 5.7 or higher (or MariaDB 10.2+)
- **Web Server**: Apache 2.4+ with mod_rewrite
- **Memory**: 256MB RAM minimum
- **Disk Space**: 100MB minimum
- **PHP Extensions**:
  - PDO
  - pdo_mysql
  - mbstring
  - json
  - openssl
  - fileinfo
  - gd or imagick (for image processing)

### Recommended Requirements

- **PHP**: 8.2+
- **MySQL**: 8.0+
- **Memory**: 512MB RAM
- **Disk Space**: 1GB+
- **Additional Extensions**:
  - apcu (for caching)
  - opcache (for performance)
  - zip (for backups)

## Shared Hosting Deployment

### Step 1: Prepare Files

1. **Download or clone the repository**:
```bash
git clone https://github.com/your-repo/framework.git
cd framework
```

2. **Install dependencies**:
```bash
composer install --no-dev --optimize-autoloader
```

3. **Create production .env file**:
```bash
cp .env.example .env
```

Edit `.env` with production values:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_secure_password

JWT_SECRET=your-very-long-random-secret-key-here
JWT_EXPIRATION=3600

CACHE_DRIVER=apcu
```

### Step 2: Upload Files

Upload all files to your hosting account:
- Use FTP/SFTP or hosting file manager
- Upload to public_html or equivalent directory
- Ensure `.htaccess` file is uploaded

### Step 3: Configure Web Root

Point your domain to the `public/` directory:

**Option A: Using .htaccess (if you can't change document root)**

Create `.htaccess` in root directory:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

**Option B: Change document root (recommended)**

In cPanel or hosting panel, set document root to `/public`

### Step 4: Set Permissions

```bash
chmod 755 public
chmod 755 storage
chmod 755 storage/cache
chmod 755 storage/logs
chmod 755 public/uploads
```

### Step 5: Run Installer

Navigate to `https://your-domain.com/install` and follow the installation wizard.

### Step 6: Secure Installation

After installation:

1. **Delete installer** (optional):
```bash
rm -rf src/Install
```

2. **Protect sensitive files**:

Add to `.htaccess`:
```apache
<FilesMatch "^\.env$">
    Order allow,deny
    Deny from all
</FilesMatch>

<FilesMatch "^composer\.(json|lock)$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

3. **Enable HTTPS**:
- Install SSL certificate (Let's Encrypt recommended)
- Force HTTPS in `.htaccess`:

```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

## VPS/Dedicated Server Deployment

### Prerequisites

- Ubuntu 20.04+ or similar Linux distribution
- Root or sudo access
- Domain pointing to server IP

### Step 1: Install Dependencies

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.2
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-mbstring \
    php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-apcu php8.2-opcache

# Install MySQL
sudo apt install -y mysql-server

# Install Apache
sudo apt install -y apache2
sudo a2enmod rewrite
sudo a2enmod php8.2
```

### Step 2: Configure MySQL

```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Create database and user
sudo mysql -u root -p
```

```sql
CREATE DATABASE framework_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'framework_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON framework_db.* TO 'framework_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Step 3: Deploy Application

```bash
# Create application directory
sudo mkdir -p /var/www/framework
cd /var/www/framework

# Clone repository
sudo git clone https://github.com/your-repo/framework.git .

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install dependencies
composer install --no-dev --optimize-autoloader

# Set up environment
cp .env.example .env
nano .env  # Edit with your configuration

# Set permissions
sudo chown -R www-data:www-data /var/www/framework
sudo chmod -R 755 /var/www/framework
sudo chmod -R 775 /var/www/framework/storage
sudo chmod -R 775 /var/www/framework/public/uploads
```

### Step 4: Configure Apache

Create virtual host:
```bash
sudo nano /etc/apache2/sites-available/framework.conf
```

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    ServerAlias www.your-domain.com
    
    DocumentRoot /var/www/framework/public
    
    <Directory /var/www/framework/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/framework-error.log
    CustomLog ${APACHE_LOG_DIR}/framework-access.log combined
</VirtualHost>
```

Enable site:
```bash
sudo a2ensite framework.conf
sudo systemctl reload apache2
```

### Step 5: Install SSL Certificate

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-apache

# Obtain certificate
sudo certbot --apache -d your-domain.com -d www.your-domain.com
```

### Step 6: Optimize PHP

Edit `/etc/php/8.2/apache2/php.ini`:
```ini
memory_limit = 256M
upload_max_filesize = 20M
post_max_size = 20M
max_execution_time = 60

opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2

apc.enabled=1
apc.shm_size=64M
```

Restart Apache:
```bash
sudo systemctl restart apache2
```

## Docker Deployment

### Dockerfile

Create `Dockerfile`:
```dockerfile
FROM php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd opcache

# Install APCu
RUN pecl install apcu && docker-php-ext-enable apcu

# Enable Apache modules
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/public/uploads

# Configure Apache document root
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

EXPOSE 80
```

### docker-compose.yml

```yaml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "80:80"
    volumes:
      - ./storage:/var/www/html/storage
      - ./public/uploads:/var/www/html/public/uploads
    environment:
      - APP_ENV=production
      - DB_HOST=db
      - DB_DATABASE=framework
      - DB_USERNAME=framework
      - DB_PASSWORD=secret
    depends_on:
      - db

  db:
    image: mysql:8.0
    environment:
      - MYSQL_DATABASE=framework
      - MYSQL_USER=framework
      - MYSQL_PASSWORD=secret
      - MYSQL_ROOT_PASSWORD=rootsecret
    volumes:
      - db_data:/var/lib/mysql

volumes:
  db_data:
```

### Deploy with Docker

```bash
# Build and start containers
docker-compose up -d

# Run migrations
docker-compose exec app php cli/migrate.php

# View logs
docker-compose logs -f app
```

## Post-Deployment Checklist

- [ ] SSL certificate installed and working
- [ ] Database backups configured
- [ ] File backups configured
- [ ] Monitoring set up (uptime, errors)
- [ ] Security headers enabled
- [ ] Rate limiting configured
- [ ] Error logging working
- [ ] Email notifications configured
- [ ] Performance optimizations applied
- [ ] Documentation updated with production URLs

## Troubleshooting

### Issue: 500 Internal Server Error

**Check**:
1. Apache error logs: `tail -f /var/log/apache2/error.log`
2. PHP error logs: `tail -f /var/log/php/error.log`
3. Application logs: `tail -f storage/logs/error.log`
4. File permissions: Ensure storage/ and public/uploads/ are writable

### Issue: Database Connection Failed

**Check**:
1. Database credentials in `.env`
2. MySQL service running: `sudo systemctl status mysql`
3. Database exists: `mysql -u root -p -e "SHOW DATABASES;"`
4. User has permissions: `mysql -u root -p -e "SHOW GRANTS FOR 'user'@'localhost';"`

### Issue: Slow Performance

**Solutions**:
1. Enable OPcache (see optimization guide)
2. Enable APCu caching
3. Add database indexes
4. Enable Gzip compression
5. Use CDN for static assets

### Issue: File Upload Fails

**Check**:
1. PHP upload limits in `php.ini`
2. Directory permissions (775 for uploads/)
3. Disk space available
4. Apache/PHP max execution time

## Maintenance

### Regular Tasks

**Daily**:
- Monitor error logs
- Check disk space
- Verify backups completed

**Weekly**:
- Review security logs
- Update dependencies (if needed)
- Optimize database tables

**Monthly**:
- Review performance metrics
- Update SSL certificates (if needed)
- Test backup restoration

### Backup Strategy

**Automated backups**:
```bash
# Add to crontab
0 2 * * * /usr/bin/php /var/www/framework/cli/backup.php
```

**Manual backup**:
```bash
php cli/backup.php
```

**Restore from backup**:
```bash
php cli/restore.php /path/to/backup.zip
```

## Scaling

### Horizontal Scaling

For high-traffic sites:

1. **Load Balancer**: Distribute traffic across multiple servers
2. **Shared Database**: Use external MySQL server
3. **Shared Storage**: Use NFS or S3 for uploads
4. **Redis/Memcached**: External cache server
5. **CDN**: Serve static assets from CDN

### Vertical Scaling

Upgrade server resources:
- Increase RAM (512MB → 1GB → 2GB)
- Add CPU cores
- Use SSD storage
- Upgrade to dedicated server

## Support

For deployment issues:
- GitHub Issues: https://github.com/your-repo/framework/issues
- Documentation: https://docs.your-domain.com
- Community Forum: https://forum.your-domain.com
