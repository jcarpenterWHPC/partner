#!/bin/bash
#==============================================================================
# WHPC Partner Portal (__HOST__) -- EC2 user-data / first-boot provisioning
#------------------------------------------------------------------------------
# Generated from the home server's deploy/ec2-user-data.sh pattern on 2026-10-29.
# Target image:  Ubuntu Server 24.04 LTS (Noble Numbat), x86_64
# Provisions:    Apache 2.4 + mod_php 8.3 + Composer, the PHP extensions a
#                CodeIgniter 4 app needs, the __HOST__ vhost, php.ini tuning,
#                AWS CLI v2, a GitHub deploy key, and `whpc-deploy`.
# Box role:      __ROLE__   (prod = production tuning; test = + dev tooling)
#==============================================================================

set -euxo pipefail
exec > >(tee -a /var/log/whpc-userdata.log) 2>&1

export DEBIAN_FRONTEND=noninteractive
# cloud-init runs this with no HOME, and Composer refuses to run without one.
export HOME=/root COMPOSER_HOME=/root/.composer
APT_OPTS='-y -o Dpkg::Options::=--force-confold -o Dpkg::Options::=--force-confdef'
BOX_ROLE='__ROLE__'
SITE_HOST='__HOST__'
REPO='git@github.com:jcarpenterWHPC/partner.git'

#------------------------------------------------------------------------------
# 1. Base system + timezone
#------------------------------------------------------------------------------
apt-get update -y
timedatectl set-timezone America/Chicago

#------------------------------------------------------------------------------
# 2. Packages
#------------------------------------------------------------------------------
# shellcheck disable=SC2086
apt-get install $APT_OPTS \
    apache2 \
    libapache2-mod-php8.3 \
    php8.3-cli \
    php8.3-common \
    php8.3-opcache \
    php8.3-readline \
    php8.3-mysql \
    php8.3-curl \
    php8.3-intl \
    php8.3-mbstring \
    php8.3-gd \
    php8.3-xml \
    php8.3-zip \
    libapache2-mod-security2 \
    mysql-client \
    git \
    rsync \
    unzip \
    jq \
    curl \
    ca-certificates

if [ "$BOX_ROLE" = "test" ]; then
    # PHPUnit's SQLite driver and Node for the Playwright suite -- dev box only.
    # shellcheck disable=SC2086
    apt-get install $APT_OPTS php8.3-sqlite3 nodejs npm
fi

#------------------------------------------------------------------------------
# 3. Composer (official installer, signature-verified)
#------------------------------------------------------------------------------
EXPECTED_SIG="$(curl -fsSL --max-time 30 https://composer.github.io/installer.sig)"
curl -fsSL --max-time 60 https://getcomposer.org/installer -o /tmp/composer-setup.php
ACTUAL_SIG="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
if [ "$EXPECTED_SIG" != "$ACTUAL_SIG" ]; then
    echo 'ERROR: Composer installer checksum mismatch -- aborting.' >&2
    rm -f /tmp/composer-setup.php
    exit 1
fi
php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
rm -f /tmp/composer-setup.php

#------------------------------------------------------------------------------
# 4. AWS CLI v2 (official installer; the app reads Secrets Manager via the role)
#------------------------------------------------------------------------------
curl -fsSL --max-time 120 https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip -o /tmp/awscliv2.zip
unzip -q /tmp/awscliv2.zip -d /tmp/awscliv2
/tmp/awscliv2/aws/install
rm -rf /tmp/awscliv2 /tmp/awscliv2.zip

#------------------------------------------------------------------------------
# 5. Apache modules
#------------------------------------------------------------------------------
a2enmod rewrite headers expires deflate
a2enmod security2 || true

#------------------------------------------------------------------------------
# 6. PHP overrides (Apache + CLI SAPIs)
#------------------------------------------------------------------------------
if [ "$BOX_ROLE" = "test" ]; then
    PHP_DISPLAY_ERRORS='Off'
    PHP_OPCACHE_REVALIDATE='1'
else
    PHP_DISPLAY_ERRORS='Off'
    PHP_OPCACHE_REVALIDATE='60'
fi
cat > /etc/php/8.3/apache2/conf.d/99-whpc.ini <<INI
; WHPC Partner Portal -- PHP overrides
expose_php = Off
memory_limit = 256M
upload_max_filesize = 50M
post_max_size = 55M
max_execution_time = 120
date.timezone = America/Chicago
display_errors = ${PHP_DISPLAY_ERRORS}
log_errors = On
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 192
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
opcache.revalidate_freq = ${PHP_OPCACHE_REVALIDATE}
INI
cp /etc/php/8.3/apache2/conf.d/99-whpc.ini /etc/php/8.3/cli/conf.d/99-whpc.ini

#------------------------------------------------------------------------------
# 7. Apache vhost (docroot /var/www/public, plain HTTP origin behind CloudFront/ALB)
#------------------------------------------------------------------------------
cat > "/etc/apache2/sites-available/${SITE_HOST}.conf" <<VHOST
<VirtualHost *:80>
    ServerName ${SITE_HOST}
    ServerAdmin webmaster@whpc.com

    DocumentRoot /var/www/public
    LimitRequestBody 52428800

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined

    <Directory /var/www/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch "^\.">
        Require all denied
    </FilesMatch>

    <Directory /var/www/writable>
        Require all denied
    </Directory>

    <FilesMatch "(^composer\.(json|lock)|^\.env|\.git)">
        Require all denied
    </FilesMatch>

    <Directory />
        Options -Indexes
    </Directory>
</VirtualHost>
VHOST
a2dissite 000-default.conf || true
a2ensite "${SITE_HOST}.conf"

# A static health-check target for the ALB until the repo is deployed over it.
install -d -m 755 /var/www/public
echo 'ok' > /var/www/public/healthcheck.html
chown -R ubuntu:www-data /var/www
# secrets:refresh runs as ubuntu and must keep .env in Apache's group.
usermod -aG www-data ubuntu

#------------------------------------------------------------------------------
# 8. Deploy account SSH: GitHub host key + a per-box deploy key (public half
#    is printed to the next-steps file; the private key never leaves the box).
#------------------------------------------------------------------------------
install -d -m 700 -o ubuntu -g ubuntu /home/ubuntu/.ssh
ssh-keyscan -t rsa,ecdsa,ed25519 github.com >> /home/ubuntu/.ssh/known_hosts 2>/dev/null || true
chown ubuntu:ubuntu /home/ubuntu/.ssh/known_hosts
chmod 600 /home/ubuntu/.ssh/known_hosts
if [ ! -f /home/ubuntu/.ssh/id_ed25519 ]; then
    sudo -u ubuntu ssh-keygen -t ed25519 -N '' -C "deploy@${SITE_HOST}" -f /home/ubuntu/.ssh/id_ed25519
fi
git config --system --add safe.directory /var/www

#------------------------------------------------------------------------------
# 9. Deploy / update helper: `sudo whpc-deploy`
#------------------------------------------------------------------------------
cat > /usr/local/sbin/whpc-deploy <<DEPLOY
#!/bin/bash
# Deploy or update the WHPC Partner Portal at /var/www. Run as root (sudo).
set -euo pipefail
REPO='${REPO}'
APP_DIR='/var/www'
DEPLOY_USER='ubuntu'
BRANCH="\${1:-main}"

run_as_deploy() { sudo -H -u "\$DEPLOY_USER" GIT_SSH_COMMAND='ssh -o StrictHostKeyChecking=accept-new' "\$@"; }

if [ ! -d "\$APP_DIR/.git" ]; then
    echo '==> First deploy: cloning repo into /var/www'
    rm -rf /var/www/html
    run_as_deploy git clone "\$REPO" /tmp/whpc-partner
    shopt -s dotglob
    rsync -a --remove-source-files /tmp/whpc-partner/ "\$APP_DIR"/
    rm -rf /tmp/whpc-partner
else
    echo "==> Updating existing checkout (\$BRANCH)"
    run_as_deploy git -C "\$APP_DIR" fetch origin
    run_as_deploy git -C "\$APP_DIR" checkout -q "\$BRANCH"
    run_as_deploy git -C "\$APP_DIR" pull --ff-only origin "\$BRANCH"
fi

echo '==> Installing PHP dependencies'
if [ -f /etc/whpc-box-role ] && [ "\$(cat /etc/whpc-box-role)" = "test" ]; then
    run_as_deploy composer install --no-interaction -d "\$APP_DIR"
else
    run_as_deploy composer install --no-dev --optimize-autoloader --no-interaction -d "\$APP_DIR"
fi

if [ ! -f "\$APP_DIR/.env" ]; then
    echo '!! No /var/www/.env found. Build it from Secrets Manager:'
    echo '     sudo -u ubuntu php /var/www/spark secrets:refresh'
    echo '   Then re-run: sudo whpc-deploy'
    exit 1
fi

echo '==> Running database migrations'
run_as_deploy php "\$APP_DIR/spark" migrate --all

echo '==> Fixing ownership / permissions'
chown -R "\$DEPLOY_USER":www-data "\$APP_DIR"
find "\$APP_DIR/writable" -type d -exec chmod 775 {} +
find "\$APP_DIR/writable" -type f -exec chmod 664 {} +
chmod -R g+s "\$APP_DIR/writable"

echo '==> Restarting Apache (mod_php opcache lives in the workers)'
apache2ctl configtest && systemctl restart apache2
echo '==> Done.'
DEPLOY
chmod 755 /usr/local/sbin/whpc-deploy
echo "$BOX_ROLE" > /etc/whpc-box-role

#------------------------------------------------------------------------------
# 10. Operator notes
#------------------------------------------------------------------------------
{
    echo "WHPC Partner Portal (${SITE_HOST}) -- base provisioning complete"
    echo "=============================================================="
    echo "Box role: ${BOX_ROLE}"
    echo
    echo "1. Add this deploy key to github.com/jcarpenterWHPC/partner (Settings -> Deploy keys):"
    cat /home/ubuntu/.ssh/id_ed25519.pub
    echo
    echo "2. First deploy (clones the repo):      sudo whpc-deploy"
    echo "3. Build .env from Secrets Manager:     sudo -u ubuntu php /var/www/spark secrets:refresh"
    echo "4. Second deploy (migrate + perms):     sudo whpc-deploy"
    echo "5. Verify:                              curl -I http://localhost/"
} > /root/WHPC-DEPLOY-NEXT-STEPS.txt

#------------------------------------------------------------------------------
# 11. Services
#------------------------------------------------------------------------------
systemctl enable apache2
apache2ctl configtest
systemctl restart apache2
echo '==> WHPC Partner base provisioning complete.'
