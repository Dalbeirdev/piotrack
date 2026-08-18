#!/usr/bin/env bash
#
# piotrack — one-command deployment for Ubuntu.
#
#   sudo bash deploy-ubuntu.sh --check     # preflight only, changes nothing
#   sudo bash deploy-ubuntu.sh             # full deploy
#
# Designed to be safe on a server that is already running something else
# (an osTicket helpdesk, another PHP app). It NEVER changes the system PHP
# version, never edits an existing vhost, and never deletes anything. If a
# precondition fails it stops rather than guessing.
#
# Everything it does is logged to /var/log/piotrack-deploy.log.

set -Eeuo pipefail

# ---------------------------------------------------------------- settings --
APP_DIR="${APP_DIR:-/var/www/piotrack}"
APP_HOST="${APP_HOST:-piotrack.local}"
REPO="${REPO:-https://github.com/Dalbeirdev/piotrack.git}"
BRANCH="${BRANCH:-main}"
PHP_V="8.4"
DB_NAME="piotrack"
DB_USER="piotrack"
BACKUP_DIR="${BACKUP_DIR:-/root/piotrack-predeploy-backups}"
LOG="/var/log/piotrack-deploy.log"

CHECK_ONLY=0
[[ "${1:-}" == "--check" ]] && CHECK_ONLY=1

# ----------------------------------------------------------------- output ---
bold=$'\e[1m'; red=$'\e[31m'; grn=$'\e[32m'; ylw=$'\e[33m'; dim=$'\e[2m'; off=$'\e[0m'
step() { printf '\n%s==> %s%s\n' "$bold" "$*" "$off" | tee -a "$LOG"; }
ok()   { printf '  %s✓%s %s\n' "$grn" "$off" "$*" | tee -a "$LOG"; }
warn() { printf '  %s!%s %s\n' "$ylw" "$off" "$*" | tee -a "$LOG"; }
die()  { printf '\n  %s✗ %s%s\n\n' "$red" "$*" "$off" | tee -a "$LOG"; exit 1; }
note() { printf '  %s%s%s\n' "$dim" "$*" "$off" | tee -a "$LOG"; }

trap 'die "Failed on line $LINENO. Nothing further was changed. See $LOG"' ERR

[[ $EUID -eq 0 ]] || die "Run with sudo: sudo bash $0 ${1:-}"
mkdir -p "$(dirname "$LOG")"; touch "$LOG"
printf '\n===== %s =====\n' "$(date -Is)" >> "$LOG"

# =========================================================== 1. PREFLIGHT ===
step "Preflight"

. /etc/os-release 2>/dev/null || die "Cannot read /etc/os-release — is this Ubuntu?"
[[ "${ID:-}" == "ubuntu" ]] || die "This script targets Ubuntu; found '${ID:-unknown}'."
ok "Ubuntu ${VERSION_ID:-?}"

FREE_GB=$(df --output=avail -BG / | tail -1 | tr -dc '0-9')
(( FREE_GB >= 5 )) || die "Only ${FREE_GB}G free on / — need at least 5G."
ok "${FREE_GB}G free on /"

# Which PHP does the EXISTING site run on? We must not disturb it.
if command -v php >/dev/null 2>&1; then
  SYS_PHP=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "?")
  ok "System PHP is ${SYS_PHP} — it will NOT be changed"
  [[ "$SYS_PHP" == "$PHP_V" ]] || note "piotrack will use its own PHP ${PHP_V} via a separate FPM pool"
else
  SYS_PHP="none"; ok "No system PHP present"
fi

# Detect a co-resident osTicket so we can back it up and protect it.
OST_DIR=""; OST_DB=""
for d in /var/www/html /var/www/osticket /var/www/html/upload; do
  if [[ -f "$d/include/ost-config.php" ]]; then OST_DIR="$d"; break; fi
done
if [[ -n "$OST_DIR" ]]; then
  OST_DB=$(grep -oP "define\('DBNAME'\s*,\s*'\K[^']+" "$OST_DIR/include/ost-config.php" 2>/dev/null || true)
  ok "osTicket found at $OST_DIR${OST_DB:+ (database: $OST_DB)}"
  [[ -n "$OST_DB" ]] || warn "Could not read its database name from ost-config.php — back it up by hand first"
else
  note "No osTicket install detected"
fi

WEBSRV=""
systemctl is-active --quiet apache2 && WEBSRV="apache2"
systemctl is-active --quiet nginx   && WEBSRV="${WEBSRV:+$WEBSRV+}nginx"
[[ -n "$WEBSRV" ]] && ok "Web server running: $WEBSRV" || note "No web server running yet"
[[ "$WEBSRV" == *"+"* ]] && die "Both Apache and nginx are running — resolve that first, this script won't guess."

if [[ -e /var/run/reboot-required ]]; then
  warn "A reboot is pending on this system."
  note "Reboot first, then re-run. Stacking a deploy on an unrebooted kernel makes failures hard to attribute."
fi

[[ -e "$APP_DIR/.env" ]] && die "$APP_DIR/.env already exists — piotrack looks deployed. Use the update path in the runbook."

if (( CHECK_ONLY )); then
  step "Preflight only — nothing was changed"
  echo
  echo "  Would deploy:  $REPO ($BRANCH)"
  echo "  To:            $APP_DIR"
  echo "  Served at:     http://$APP_HOST"
  echo "  Using:         PHP $PHP_V (own FPM pool), PostgreSQL, Redis"
  [[ -n "$OST_DIR" ]] && echo "  Protecting:    osTicket at $OST_DIR (PHP $SYS_PHP untouched)"
  echo
  echo "  Run without --check to proceed."
  echo
  exit 0
fi

# ============================================================ 2. CONFIRM ====
step "About to make changes"
cat <<EOF

  Install PHP ${PHP_V} (alongside ${SYS_PHP}), PostgreSQL, Redis, Node, Composer
  Deploy piotrack to ${APP_DIR}, served at http://${APP_HOST}
  Add ONE new vhost; existing sites are not edited
$( [[ -n "$OST_DIR" ]] && echo "  Back up osTicket to ${BACKUP_DIR} first" )

EOF
read -rp "  Type 'yes' to continue: " reply
[[ "$reply" == "yes" ]] || die "Cancelled — nothing was changed."

# ============================================================= 3. BACKUP ====
if [[ -n "$OST_DIR" ]]; then
  step "Backing up the existing helpdesk"
  mkdir -p "$BACKUP_DIR"
  STAMP=$(date +%F-%H%M)

  tar czf "$BACKUP_DIR/osticket-files-$STAMP.tar.gz" -C / "${OST_DIR#/}" 2>/dev/null
  ok "Files → $BACKUP_DIR/osticket-files-$STAMP.tar.gz ($(du -h "$BACKUP_DIR/osticket-files-$STAMP.tar.gz" | cut -f1))"

  if [[ -n "$OST_DB" ]]; then
    OST_USER=$(grep -oP "define\('DBUSER'\s*,\s*'\K[^']+" "$OST_DIR/include/ost-config.php" || echo root)
    OST_PASS=$(grep -oP "define\('DBPASS'\s*,\s*'\K[^']*" "$OST_DIR/include/ost-config.php" || echo "")
    if mysqldump -u"$OST_USER" ${OST_PASS:+-p"$OST_PASS"} --single-transaction --routines \
        "$OST_DB" > "$BACKUP_DIR/osticket-db-$STAMP.sql" 2>>"$LOG"; then
      SZ=$(du -h "$BACKUP_DIR/osticket-db-$STAMP.sql" | cut -f1)
      [[ -s "$BACKUP_DIR/osticket-db-$STAMP.sql" ]] || die "Database dump is empty — stopping."
      ok "Database → $BACKUP_DIR/osticket-db-$STAMP.sql ($SZ)"
    else
      die "Could not dump database '$OST_DB'. Back it up manually, then re-run."
    fi
  fi
  note "These live only on this machine — copy them somewhere else afterwards."
fi

# ============================================================ 4. PACKAGES ===
step "Installing the stack"
export DEBIAN_FRONTEND=noninteractive

apt-get update -qq >>"$LOG" 2>&1
apt-get install -y -qq software-properties-common curl git unzip ca-certificates >>"$LOG" 2>&1

# Confirm we can actually fetch the code BEFORE installing 300MB of packages.
# piotrack is a private repository, so this is where a missing deploy key surfaces.
if [[ ! -d "$APP_DIR/.git" ]]; then
  if GIT_TERMINAL_PROMPT=0 GIT_SSH_COMMAND="ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new" \
     git ls-remote --exit-code --heads "$REPO" "$BRANCH" >/dev/null 2>&1; then
    ok "Repository reachable (branch $BRANCH)"
  else
    cat >&2 <<EOF

  Cannot read $REPO

  piotrack is a private repository, so this server needs its own read access.
  Set up a deploy key (once), then re-run:

    ssh-keygen -t ed25519 -f ~/.ssh/piotrack_deploy -N "" -C "piotrack-deploy"
    cat ~/.ssh/piotrack_deploy.pub

  Add that key at:
    https://github.com/Dalbeirdev/piotrack/settings/keys  ->  Add deploy key
    (leave "Allow write access" UNCHECKED)

  Then point ssh at it:

    printf 'Host github.com\\n  IdentityFile ~/.ssh/piotrack_deploy\\n  IdentitiesOnly yes\\n' >> ~/.ssh/config

  And re-run this script with the SSH URL:

    REPO=git@github.com:Dalbeirdev/piotrack.git sudo -E bash \$0

EOF
    die "No read access to the repository — nothing was installed."
  fi
fi

# Ondrej PPA adds PHP 8.4 as ADDITIONAL packages. The default `php` alternative
# is left pointing wherever it already pointed.
add-apt-repository -y ppa:ondrej/php >>"$LOG" 2>&1
apt-get update -qq >>"$LOG" 2>&1
apt-get install -y -qq \
  php${PHP_V}-fpm php${PHP_V}-cli php${PHP_V}-pgsql php${PHP_V}-mbstring php${PHP_V}-xml \
  php${PHP_V}-curl php${PHP_V}-zip php${PHP_V}-gd php${PHP_V}-bcmath php${PHP_V}-intl \
  php${PHP_V}-redis >>"$LOG" 2>&1
ok "PHP ${PHP_V} installed (system default still $(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo none))"

apt-get install -y -qq postgresql postgresql-contrib redis-server >>"$LOG" 2>&1
systemctl enable --now postgresql redis-server >>"$LOG" 2>&1
ok "PostgreSQL and Redis running"

if ! command -v node >/dev/null 2>&1 || [[ "$(node -v | tr -dc '0-9.' | cut -d. -f1)" -lt 20 ]]; then
  curl -fsSL https://deb.nodesource.com/setup_22.x | bash - >>"$LOG" 2>&1
  apt-get install -y -qq nodejs >>"$LOG" 2>&1
fi
ok "Node $(node -v)"

if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer | php${PHP_V} -- --install-dir=/usr/local/bin --filename=composer >>"$LOG" 2>&1
fi
ok "Composer $(composer --version --no-ansi 2>/dev/null | head -1)"

# ============================================================ 5. DATABASE ===
step "Creating the database"
DB_PASS=$(openssl rand -base64 24 | tr -d '/+=' | head -c 28)

if sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='$DB_NAME'" | grep -q 1; then
  warn "Database '$DB_NAME' already exists — reusing it"
else
  sudo -u postgres psql -qc "CREATE DATABASE $DB_NAME;" >>"$LOG"
  ok "Database '$DB_NAME' created"
fi
sudo -u postgres psql -qc "DROP ROLE IF EXISTS $DB_USER;" >>"$LOG" 2>&1 || true
sudo -u postgres psql -qc "CREATE ROLE $DB_USER LOGIN ENCRYPTED PASSWORD '$DB_PASS';" >>"$LOG"
sudo -u postgres psql -qc "GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;" >>"$LOG"
sudo -u postgres psql -qd "$DB_NAME" -c "GRANT ALL ON SCHEMA public TO $DB_USER;" >>"$LOG"
ok "Role '$DB_USER' created with a generated password"

# ========================================================= 6. APPLICATION ===
step "Deploying the application"
mkdir -p "$APP_DIR"
if [[ -d "$APP_DIR/.git" ]]; then
  git -C "$APP_DIR" fetch --quiet origin && git -C "$APP_DIR" checkout --quiet "$BRANCH" && git -C "$APP_DIR" pull --quiet
else
  git clone --quiet --branch "$BRANCH" "$REPO" "$APP_DIR"
fi
ok "Code at $(git -C "$APP_DIR" rev-parse --short HEAD)"

cd "$APP_DIR"
composer install --no-dev --optimize-autoloader --no-interaction --quiet >>"$LOG" 2>&1
ok "PHP dependencies installed"

npm ci --silent >>"$LOG" 2>&1
npm run build --silent >>"$LOG" 2>&1
ok "Front-end assets built"

cat > "$APP_DIR/.env" <<EOF
APP_NAME=Piotrack
APP_ENV=staging
APP_KEY=
APP_DEBUG=false
APP_URL=http://${APP_HOST}

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

MAIL_MAILER=log

AI_DRIVER=fixture
ANALYTICS_CALLS_DRIVER=fixture

DEMO_SEED_ALLOWED=true
EOF
chmod 640 "$APP_DIR/.env"

php${PHP_V} artisan key:generate --force --quiet
php${PHP_V} artisan migrate --force --quiet
ok "Database migrated"

php${PHP_V} artisan db:seed --class=DemoSeeder --force --quiet
ok "Demo dataset seeded"

php${PHP_V} artisan storage:link --quiet 2>/dev/null || true
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
ok "Permissions set"

# ============================================================ 7. RUNTIME ====
step "Web server and services"

cat > /etc/php/${PHP_V}/fpm/pool.d/piotrack.conf <<EOF
[piotrack]
user = www-data
group = www-data
listen = /run/php/php${PHP_V}-fpm-piotrack.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 20M
php_admin_value[post_max_size] = 20M
EOF
systemctl restart php${PHP_V}-fpm
systemctl enable --quiet php${PHP_V}-fpm
ok "Dedicated PHP-FPM pool (own socket, isolated from the existing site)"

if [[ "$WEBSRV" == "apache2" || -z "$WEBSRV" ]]; then
  apt-get install -y -qq apache2 >>"$LOG" 2>&1
  a2enmod proxy_fcgi setenvif rewrite >>"$LOG" 2>&1
  cat > /etc/apache2/sites-available/piotrack.conf <<EOF
<VirtualHost *:80>
    ServerName ${APP_HOST}
    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    <FilesMatch "\.php\$">
        SetHandler "proxy:unix:/run/php/php${PHP_V}-fpm-piotrack.sock|fcgi://localhost"
    </FilesMatch>

    ErrorLog  \${APACHE_LOG_DIR}/piotrack-error.log
    CustomLog \${APACHE_LOG_DIR}/piotrack-access.log combined
</VirtualHost>
EOF
  a2ensite piotrack >>"$LOG" 2>&1
  apache2ctl configtest >>"$LOG" 2>&1 || die "Apache config test failed — piotrack vhost NOT enabled. See $LOG"
  systemctl reload apache2
  ok "Apache vhost added for ${APP_HOST} (existing sites untouched)"
else
  cat > /etc/nginx/sites-available/piotrack <<EOF
server {
    listen 80;
    server_name ${APP_HOST};
    root ${APP_DIR}/public;
    index index.php;

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_V}-fpm-piotrack.sock;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
EOF
  ln -sf /etc/nginx/sites-available/piotrack /etc/nginx/sites-enabled/piotrack
  nginx -t >>"$LOG" 2>&1 || die "nginx config test failed — see $LOG"
  systemctl reload nginx
  ok "nginx server block added for ${APP_HOST} (existing sites untouched)"
fi

cat > /etc/systemd/system/piotrack-worker.service <<EOF
[Unit]
Description=piotrack queue worker
After=network.target redis-server.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
ExecStart=/usr/bin/php${PHP_V} ${APP_DIR}/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable --now --quiet piotrack-worker
ok "Queue worker running as a service"

CRON="* * * * * cd ${APP_DIR} && /usr/bin/php${PHP_V} artisan schedule:run >> /dev/null 2>&1"
( crontab -u www-data -l 2>/dev/null | grep -Fv "${APP_DIR}" || true; echo "$CRON" ) | crontab -u www-data -
ok "Scheduler cron installed"

cd "$APP_DIR"
php${PHP_V} artisan config:cache --quiet
php${PHP_V} artisan route:cache --quiet
php${PHP_V} artisan view:cache --quiet
ok "Configuration cached"

# ============================================================== 8. VERIFY ===
step "Verifying"

for svc in php${PHP_V}-fpm postgresql redis-server piotrack-worker; do
  systemctl is-active --quiet "$svc" && ok "$svc active" || warn "$svc NOT active"
done

CODE=$(curl -s -o /dev/null -w '%{http_code}' -H "Host: ${APP_HOST}" http://127.0.0.1/ || echo 000)
[[ "$CODE" == "200" ]] && ok "piotrack responds (HTTP $CODE)" || warn "piotrack returned HTTP $CODE — check ${APP_DIR}/storage/logs/laravel.log"

if [[ -n "$OST_DIR" ]]; then
  OCODE=$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1/ || echo 000)
  [[ "$OCODE" =~ ^(200|301|302)$ ]] && ok "existing helpdesk still responds (HTTP $OCODE)" \
    || warn "existing site returned HTTP $OCODE — check it before you walk away"
fi

# ============================================================== 9. SUMMARY ==
step "Done"
cat <<EOF

  ${bold}piotrack is deployed.${off}

  URL        http://${APP_HOST}
  Sign in    demo@piotrack.test  /  piotrack-demo-2026
             ${ylw}change this password after first login${off}

  Path       ${APP_DIR}
  Database   ${DB_NAME} (credentials are in ${APP_DIR}/.env)
  Log        ${LOG}
$( [[ -n "$OST_DIR" ]] && echo "  Backups    ${BACKUP_DIR} — copy these off this machine" )

  ${bold}Make the hostname resolve${off}
  Add an A record on your internal DNS, or on each machine that will browse it:
    Windows  C:\\Windows\\System32\\drivers\\etc\\hosts   (as Administrator)
    Linux    /etc/hosts
  Line to add:
    $(hostname -I | awk '{print $1}')    ${APP_HOST}

  ${bold}To undo${off}
  sudo systemctl disable --now piotrack-worker
  sudo a2dissite piotrack 2>/dev/null || sudo rm -f /etc/nginx/sites-enabled/piotrack
  sudo systemctl reload ${WEBSRV:-apache2}
  (the existing site is unaffected by all of the above)

EOF
