# NoWP Framework - Production Deployment Guide

Complete guide for deploying NoWP Framework to production environments.

## Pre-Deployment Checklist

- [ ] PHP 8.1+ installed
- [ ] MySQL 5.7+ database created
- [ ] Domain/subdomain configured
- [ ] SSL certificate installed (recommended)
- [ ] Backup strategy planned
- [ ] Monitoring tools ready

## Deployment Options

### Option 1: Shared Hosting (Recommended for Small Sites)

Perfect for sites with moderate traffic. NoWP is optimized for shared hosting.

#### Requirements
- PHP 8.1+
- MySQL 5.7+
- 256MB RAM minimum
- 100MB disk space minimum
- Apache with mod_rewrite

#### Steps

1. **Upload Files**
   ```bash
   # Via FTP/SFTP, upload all files except:
   - .git/
   - node_modules/
   - tests/
   - .env (create on server)
   ```

2. **Install Dependencies**
   ```bash
   # SSH into your server
   cd /path/to/your/site
   composer install --no-dev --optimize-autoloader
   ```

3. **Configure Environment**
   ```bash
   cp .env.example .env
   nano .env
   ```

   Update these values:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourdomain.com
   
   DB_HOST=localhost
   DB_DATABASE=your_database
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   
   JWT_SECRET=generate-a-secure-random-string-here
   JWT_EXPIRATION=3600
   
   CACHE_DRIVER=file  # or apcu if available
   ```

4. **Set Permissions**
   ```bash
   chmod -R 755 storage/
   chmod -R 755 public/uploads/
   ```

5. **Run Migrations**
   ```bash
   php cli/migrate.php
   ```

6. **Configure Web Server**
   
   Point your domain to the `public/` directory.
   
   The `.htaccess` file is already configured for Apache.

7. **Create Admin User**
   ```bash
   # Via API or directly in database
   # See "Creating Admin User" section below
   ```

8. **Test Installation**
   - Visit `https://yourdomain.com/api/docs`
   - Verify API is working
   - Test admin panel login

### Option 2: VPS/Cloud Server

For sites with higher traffic or custom requirements.

#### Recommended Specs
- 1GB RAM minimum
- 1 CPU core minimum
- 20GB disk space
- Ubuntu 22.04 LTS or similar

#### Steps

1. **Install Dependencies**
   ```bash
   # Update system
   sudo apt update && sudo apt upgrade -y
   
   # Install PHP 8.1
   sudo apt install -y php8.1 php8.1-fpm php8.1-mysql php8.1-mbstring \
     php8.1-xml php8.1-curl php8.1-zip php8.1-gd php8.1-imagick
   
   # Install MySQL
   sudo apt install -y mysql-server
   
   # Install Composer
   curl -sS https://getcomposer.org/installer | php
   sudo mv composer.phar /usr/local/bin/composer
   
   # Install Nginx (or Apache)
   sudo apt install -y nginx
   ```

2. **Configure MySQL**
   ```bash
   sudo mysql_secure_installation
   
   # Create database
   sudo mysql -u root -p
   ```
   
   ```sql
   CREATE DATABASE nowp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'nowp'@'localhost' IDENTIFIED BY 'secure_password';
   GRANT ALL PRIVILEGES ON nowp.* TO 'nowp'@'localhost';
   FLUSH PRIVILEGES;
   EXIT;
   ```

3. **Deploy Application**
   ```bash
   # Clone or upload your code
   cd /var/www
   git clone your-repo.git nowp
   cd nowp
   
   # Install dependencies
   composer install --no-dev --optimize-autoloader
   
   # Configure environment
   cp .env.example .env
   nano .env
   ```

4. **Set Permissions**
   ```bash
   sudo chown -R www-data:www-data /var/www/nowp
   sudo chmod -R 755 /var/www/nowp/storage
   sudo chmod -R 755 /var/www/nowp/public/uploads
   ```

5. **Configure Nginx**
   ```bash
   sudo nano /etc/nginx/sites-available/nowp
   ```
   
   ```nginx
   server {
       listen 80;
       server_name yourdomain.com;
       root /var/www/nowp/public;
       
       index index.php;
       
       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }
       
       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
           fastcgi_index index.php;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
           include fastcgi_params;
       }
       
       location ~ /\.(?!well-known).* {
           deny all;
       }
   }
   ```
   
   ```bash
   sudo ln -s /etc/nginx/sites-available/nowp /etc/nginx/sites-enabled/
   sudo nginx -t
   sudo systemctl restart nginx
   ```

6. **Install SSL Certificate**
   ```bash
   sudo apt install -y certbot python3-certbot-nginx
   sudo certbot --nginx -d yourdomain.com
   ```

7. **Run Migrations**
   ```bash
   cd /var/www/nowp
   php cli/migrate.php
   ```

### Option 3: Docker

For containerized deployments.

#### Dockerfile

```dockerfile
FROM php:8.1-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application
COPY . /var/www

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www

EXPOSE 9000
CMD ["php-fpm"]
```

#### docker-compose.yml

```yaml
version: '3.8'

services:
  app:
    build: .
    container_name: nowp-app
    volumes:
      - .:/var/www
    networks:
      - nowp-network
    depends_on:
      - db

  nginx:
    image: nginx:alpine
    container_name: nowp-nginx
    ports:
      - "80:80"
    volumes:
      - .:/var/www
      - ./docker/nginx.conf:/etc/nginx/conf.d/default.conf
    networks:
      - nowp-network
    depends_on:
      - app

  db:
    image: mysql:8.0
    container_name: nowp-db
    environment:
      MYSQL_DATABASE: nowp
      MYSQL_ROOT_PASSWORD: root
      MYSQL_USER: nowp
      MYSQL_PASSWORD: secret
    volumes:
      - dbdata:/var/lib/mysql
    networks:
      - nowp-network

networks:
  nowp-network:
    driver: bridge

volumes:
  dbdata:
```

## Creating Admin User

### Method 1: Via API

```bash
# Register user
curl -X POST https://yourdomain.com/api/auth/register \
  -H 'Content-Type: application/json' \
  -d '{
    "email": "admin@yourdomain.com",
    "password": "secure-password",
    "name": "Admin User"
  }'

# Update role in database
mysql -u nowp -p nowp
UPDATE users SET role = 'admin' WHERE email = 'admin@yourdomain.com';
```

### Method 2: Direct Database Insert

```sql
INSERT INTO users (email, password, name, role, created_at, updated_at)
VALUES (
  'admin@yourdomain.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
  'Admin User',
  'admin',
  NOW(),
  NOW()
);
```

## Post-Deployment

### 1. Security Hardening

```bash
# Disable directory listing
# Add to .htaccess or nginx config
Options -Indexes

# Hide PHP version
# In php.ini
expose_php = Off

# Set secure file permissions
find /var/www/nowp -type f -exec chmod 644 {} \;
find /var/www/nowp -type d -exec chmod 755 {} \;
chmod 600 .env
```

### 2. Performance Optimization

```bash
# Enable OPcache
# In php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2

# Enable APCu if available
# In php.ini
apc.enabled=1
apc.shm_size=32M

# Update .env
CACHE_DRIVER=apcu
```

### 3. Setup Cron Jobs

```bash
# Edit crontab
crontab -e

# Add cleanup job (runs daily at 2 AM)
0 2 * * * cd /var/www/nowp && php cli/cleanup-orphans.php
```

### 4. Configure Backups

```bash
# Create backup script
nano /usr/local/bin/backup-nowp.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/backups/nowp"
DATE=$(date +%Y%m%d_%H%M%S)

# Backup database
mysqldump -u nowp -p'password' nowp > "$BACKUP_DIR/db_$DATE.sql"

# Backup files
tar -czf "$BACKUP_DIR/files_$DATE.tar.gz" /var/www/nowp/public/uploads

# Keep only last 7 days
find $BACKUP_DIR -type f -mtime +7 -delete
```

```bash
chmod +x /usr/local/bin/backup-nowp.sh

# Add to crontab (runs daily at 3 AM)
0 3 * * * /usr/local/bin/backup-nowp.sh
```

### 5. Setup Monitoring

Consider using:
- **Uptime monitoring**: UptimeRobot, Pingdom
- **Error tracking**: Sentry, Rollbar
- **Performance monitoring**: New Relic, Datadog
- **Log management**: Papertrail, Loggly

### 6. Deploy Admin Panel

```bash
# Build admin panel
cd admin
npm install
npm run build

# Copy dist to public directory
cp -r dist/* ../public/admin/
```

Access at: `https://yourdomain.com/admin/`

## Troubleshooting

### Issue: 500 Internal Server Error

**Solution:**
```bash
# Check error logs
tail -f /var/log/nginx/error.log
tail -f storage/logs/error.log

# Check permissions
ls -la storage/
ls -la public/uploads/

# Verify .env configuration
cat .env
```

### Issue: Database Connection Failed

**Solution:**
```bash
# Test MySQL connection
mysql -u nowp -p -h localhost nowp

# Check MySQL is running
sudo systemctl status mysql

# Verify credentials in .env
```

### Issue: Slow Performance

**Solution:**
```bash
# Enable caching
# In .env
CACHE_DRIVER=apcu  # or redis

# Check PHP memory limit
php -i | grep memory_limit

# Enable OPcache
php -i | grep opcache
```

### Issue: File Upload Fails

**Solution:**
```bash
# Check upload limits in php.ini
upload_max_filesize = 10M
post_max_size = 10M

# Check permissions
ls -la public/uploads/

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm
```

## Maintenance

### Regular Tasks

**Daily:**
- Monitor error logs
- Check disk space
- Verify backups completed

**Weekly:**
- Review security logs
- Check for PHP/MySQL updates
- Test backup restoration

**Monthly:**
- Update dependencies
- Review performance metrics
- Clean up old logs and backups

### Updating NoWP

```bash
# Backup first!
php cli/backup.php

# Pull latest code
git pull origin main

# Update dependencies
composer install --no-dev --optimize-autoloader

# Run migrations
php cli/migrate.php

# Clear cache
rm -rf storage/cache/*

# Restart services
sudo systemctl restart php8.1-fpm
sudo systemctl restart nginx
```

## Rollback Procedure

If something goes wrong:

```bash
# Restore database
mysql -u nowp -p nowp < /backups/nowp/db_YYYYMMDD_HHMMSS.sql

# Restore files
tar -xzf /backups/nowp/files_YYYYMMDD_HHMMSS.tar.gz -C /

# Revert code
git checkout previous-version-tag

# Reinstall dependencies
composer install --no-dev --optimize-autoloader
```

## Support

For deployment issues:
- Check documentation in `/docs`
- Review error logs
- Consult `TROUBLESHOOTING.md`
- Open an issue on GitHub

---

**Remember**: Always test in a staging environment before deploying to production!
