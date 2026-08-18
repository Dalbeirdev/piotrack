#!/usr/bin/env bash
#
# piotrack — offline installer.
#
# For a server with NO outbound internet access. Everything the application
# needs is already in this bundle: vendor/ and public/build are pre-built, so
# no Composer, no Node, no apt, no package downloads.
#
# It uses the PHP, MariaDB/MySQL and Apache already on the machine, and is
# written to sit safely beside an existing site: it adds a listener on its own
# port, adds one vhost, and never edits an existing config file.
#
#   sudo bash install.sh --check    # inspect only, change nothing
#   sudo bash install.sh            # install
#
# Log: /var/log/piotrack-install.log

set -Eeuo pipefail

# ---------------------------------------------------------------- settings --
APP_DIR="${APP_DIR:-/var/www/piotrack}"
APP_PORT="${APP_PORT:-8080}"
APP_HOST="${APP_HOST:-}"                       # blank = serve on IP:PORT
DB_NAME="${DB_NAME:-piotrack}"
DB_USER="${DB_USER:-piotrack}"
SEED_DEMO="${SEED_DEMO:-yes}"
BACKUP_DIR="${BACKUP_DIR:-/root/piotrack-predeploy-backups}"
LOG="/var/log/piotrack-install.log"

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CHECK_ONLY=0
[[ "${1:-}" == "--check" ]] && CHECK_ONLY=1

# ------------------------------------------------------------------ output --
bold=$'\e[1m'; red=$'\e[31m'; grn=$'\e[32m'; ylw=$'\e[33m'; dim=$'\e[2m'; off=$'\e[0m'
step() { printf '\n%s==> %s%s\n' "$bold" "$*" "$off" | tee -a "$LOG"; }
ok()   { printf '  %s+%s %s\n' "$grn" "$off" "$*" | tee -a "$LOG"; }
warn() { printf '  %s!%s %s\n' "$ylw" "$off" "$*" | tee -a "$LOG"; }
note() { printf '  %s%s%s\n'   "$dim" "$*" "$off" | tee -a "$LOG"; }
die()  { printf '\n  %s%s%s\n\n' "$red" "$*" "$off" | tee -a "$LOG"; exit 1; }

trap 'die "Failed at line $LINENO. See $LOG"' ERR

[[ $EUID -eq 0 ]] || die "Run with sudo: sudo bash $0 ${1:-}"
touch "$LOG"; printf '\n===== %s =====\n' "$(date -Is)" >> "$LOG"

# =========================================================== 1. PREFLIGHT ===
step "Preflight — checking what this machine already has"

command -v php >/dev/null || die "No PHP on this machine. This bundle cannot install one without internet."
PHP_BIN="$(command -v php)"
PHP_VER="$($PHP_BIN -r 'echo PHP_VERSION;')"
PHP_MAJMIN="$($PHP_BIN -r 'echo PHP_MAJOR_VERSION.PHP_MINOR_VERSION;')"
(( PHP_MAJMIN >= 82 )) || die "PHP $PHP_VER is too old — Laravel 12 needs 8.2 or newer."
ok "PHP $PHP_VER"

MISSING=""
for e in ctype curl dom fileinfo filter hash iconv json libxml mbstring openssl pcre session tokenizer pdo_mysql; do
  $PHP_BIN -m | grep -qix "$e" || MISSING="$MISSING $e"
done
[[ -z "$MISSING" ]] || die "PHP is missing required extensions:$MISSING (installing them needs apt, which needs internet)."
ok "All required PHP extensions present"

command -v mysql >/dev/null || command -v mariadb >/dev/null || die "No MySQL/MariaDB client found."
MYSQL_BIN="$(command -v mariadb || command -v mysql)"
ok "Database client: $($MYSQL_BIN --version | sed 's/.*Distrib //;s/,.*//' | head -c 40)"

systemctl is-active --quiet apache2 || die "Apache is not running — this installer configures an Apache vhost."
ok "Apache $(apache2 -v | head -1 | grep -oE '[0-9.]+' | head -1) running"

# How does Apache execute PHP here? Match whatever the existing site uses.
PHP_MODE=""
if apache2ctl -M 2>/dev/null | grep -qE 'php[0-9_]*_module'; then
  PHP_MODE="modphp"; ok "PHP served via mod_php"
elif compgen -G "/run/php/php*-fpm*.sock" >/dev/null; then
  FPM_SOCK="$(ls -1 /run/php/php*-fpm*.sock | head -1)"
  PHP_MODE="fpm"; ok "PHP served via PHP-FPM ($FPM_SOCK)"
elif apache2ctl -M 2>/dev/null | grep -q proxy_fcgi; then
  PHP_MODE="fpm"; FPM_SOCK=""
  warn "proxy_fcgi enabled but no FPM socket found — will look again after install"
else
  die "Cannot determine how Apache runs PHP (neither mod_php nor PHP-FPM found)."
fi

FREE_GB=$(df --output=avail -BG / | tail -1 | tr -dc '0-9')
(( FREE_GB >= 3 )) || die "Only ${FREE_GB}G free on / — need 3G."
ok "${FREE_GB}G free on /"

ss -tlnH "sport = :$APP_PORT" | grep -q . && die "Port $APP_PORT is already in use. Re-run with APP_PORT=<other>."
ok "Port $APP_PORT is free"

[[ -f "$SRC/artisan" && -d "$SRC/vendor" && -d "$SRC/public/build" ]] \
  || die "Bundle looks incomplete — expected artisan, vendor/ and public/build/ beside this script."
ok "Bundle contents verified (vendor + built assets present)"

[[ -e "$APP_DIR/.env" ]] && die "$APP_DIR/.env exists — piotrack is already installed here."

# Detect a co-resident osTicket so we can back it up before touching the DB server.
OST_DIR=""; OST_DB=""
for d in /var/www/html /var/www/osticket /var/www/html/upload; do
  [[ -f "$d/include/ost-config.php" ]] && { OST_DIR="$d"; break; }
done
if [[ -n "$OST_DIR" ]]; then
  OST_DB=$(grep -oP "define\('DBNAME'\s*,\s*'\K[^']+" "$OST_DIR/include/ost-config.php" 2>/dev/null || true)
  ok "Existing helpdesk detected at $OST_DIR${OST_DB:+ (database: $OST_DB)}"
fi

if (( CHECK_ONLY )); then
  step "Preflight passed — nothing was changed"
  cat <<EOF

  Would install to   $APP_DIR
  Serve on           http://$(hostname -I | awk '{print $1}'):$APP_PORT
  Database           $DB_NAME (MariaDB/MySQL)
  PHP                $PHP_VER via $PHP_MODE
  Demo data          $SEED_DEMO
$( [[ -n "$OST_DIR" ]] && echo "  Back up first      $OST_DIR${OST_DB:+ + database $OST_DB}" )

  Run without --check to install.

EOF
  exit 0
fi

# ============================================================= 2. CONFIRM ===
step "About to make changes"
cat <<EOF

  Install to      $APP_DIR
  Listen on       port $APP_PORT (osTicket on 80 is not touched)
  Create database $DB_NAME + user $DB_USER
  Add             one Apache vhost, one systemd worker, one cron entry
$( [[ -n "$OST_DIR" ]] && echo "  Back up         the existing helpdesk first" )

EOF
read -rp "  Type 'yes' to continue: " reply
[[ "$reply" == "yes" ]] || die "Cancelled — nothing was changed."

# ============================================================== 3. BACKUP ===
if [[ -n "$OST_DIR" ]]; then
  step "Backing up the existing helpdesk"
  mkdir -p "$BACKUP_DIR"; STAMP=$(date +%F-%H%M)

  tar czf "$BACKUP_DIR/osticket-files-$STAMP.tar.gz" -C / "${OST_DIR#/}" 2>/dev/null
  ok "Files -> osticket-files-$STAMP.tar.gz ($(du -h "$BACKUP_DIR/osticket-files-$STAMP.tar.gz" | cut -f1))"

  if [[ -n "$OST_DB" ]]; then
    OU=$(grep -oP "define\('DBUSER'\s*,\s*'\K[^']+" "$OST_DIR/include/ost-config.php" || echo root)
    OP=$(grep -oP "define\('DBPASS'\s*,\s*'\K[^']*" "$OST_DIR/include/ost-config.php" || echo "")
    if mysqldump -u"$OU" ${OP:+-p"$OP"} --single-transaction --routines "$OST_DB" \
         > "$BACKUP_DIR/osticket-db-$STAMP.sql" 2>>"$LOG" && [[ -s "$BACKUP_DIR/osticket-db-$STAMP.sql" ]]; then
      ok "Database -> osticket-db-$STAMP.sql ($(du -h "$BACKUP_DIR/osticket-db-$STAMP.sql" | cut -f1))"
    else
      die "Could not dump '$OST_DB'. Back it up by hand before continuing."
    fi
  fi
  note "Copy $BACKUP_DIR off this machine — a local backup is not a backup."
fi

# ============================================================== 4. FILES ====
step "Installing application files"
mkdir -p "$APP_DIR"
tar -C "$SRC" -cf - --exclude=install.sh . | tar -C "$APP_DIR" -xf -
ok "Copied to $APP_DIR ($(du -sh "$APP_DIR" | cut -f1))"

mkdir -p "$APP_DIR"/storage/{app,framework/{cache,sessions,views},logs} "$APP_DIR/bootstrap/cache"
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
ok "Writable directories prepared"

# ============================================================ 5. DATABASE ===
step "Creating the database"
DB_PASS=$(head -c 32 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 24)

MYSQL_ADMIN=(mysql)
if ! mysql -e "SELECT 1" >/dev/null 2>&1; then
  note "Root socket auth unavailable — enter the MariaDB root password."
  read -rsp "  MariaDB root password: " RPW; echo
  MYSQL_ADMIN=(mysql -u root -p"$RPW")
  "${MYSQL_ADMIN[@]}" -e "SELECT 1" >/dev/null 2>&1 || die "Could not authenticate to MariaDB."
fi

"${MYSQL_ADMIN[@]}" <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
DROP USER IF EXISTS '$DB_USER'@'localhost';
CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
ok "Database '$DB_NAME' and user '$DB_USER' ready"

# ================================================================= 6. ENV ===
step "Configuring the application"
IP=$(hostname -I | awk '{print $1}')
URL="http://${APP_HOST:-$IP}:${APP_PORT}"

cat > "$APP_DIR/.env" <<EOF
APP_NAME=Piotrack
APP_ENV=staging
APP_KEY=
APP_DEBUG=false
APP_URL=${URL}

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}

# No Redis on this host - Laravel's database drivers cover all three.
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database

# This server has no outbound internet: mail is written to the log, and the
# provider abstractions run their fixture drivers. Nothing calls out.
MAIL_MAILER=log
AI_DRIVER=fixture
ANALYTICS_CALLS_DRIVER=fixture

DEMO_SEED_ALLOWED=true
EOF
chown www-data:www-data "$APP_DIR/.env"; chmod 640 "$APP_DIR/.env"

cd "$APP_DIR"
$PHP_BIN artisan key:generate --force --quiet
ok "Application key generated"

$PHP_BIN artisan migrate --force --quiet
ok "Database migrated"

if [[ "$SEED_DEMO" == "yes" ]]; then
  $PHP_BIN artisan db:seed --class=DemoSeeder --force --quiet
  ok "Demo dataset seeded"
fi

$PHP_BIN artisan storage:link --quiet 2>/dev/null || true
$PHP_BIN artisan config:cache --quiet
$PHP_BIN artisan route:cache --quiet
$PHP_BIN artisan view:cache --quiet
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
ok "Configuration cached"

# ============================================================== 7. APACHE ===
step "Publishing through Apache"

grep -qE "^\s*Listen\s+$APP_PORT\b" /etc/apache2/ports.conf || {
  echo "Listen $APP_PORT" >> /etc/apache2/ports.conf
  ok "Added 'Listen $APP_PORT' to ports.conf"
}

if [[ "$PHP_MODE" == "fpm" && -z "${FPM_SOCK:-}" ]]; then
  FPM_SOCK="$(ls -1 /run/php/php*-fpm*.sock 2>/dev/null | head -1 || true)"
  [[ -n "$FPM_SOCK" ]] || die "PHP-FPM socket not found; cannot wire Apache to PHP."
fi

{
  echo "<VirtualHost *:${APP_PORT}>"
  [[ -n "$APP_HOST" ]] && echo "    ServerName ${APP_HOST}"
  cat <<EOF
    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>
EOF
  [[ "$PHP_MODE" == "fpm" ]] && cat <<EOF

    <FilesMatch "\.php\$">
        SetHandler "proxy:unix:${FPM_SOCK}|fcgi://localhost"
    </FilesMatch>
EOF
  cat <<EOF

    ErrorLog  \${APACHE_LOG_DIR}/piotrack-error.log
    CustomLog \${APACHE_LOG_DIR}/piotrack-access.log combined
</VirtualHost>
EOF
} > /etc/apache2/sites-available/piotrack.conf

a2enmod rewrite >>"$LOG" 2>&1
[[ "$PHP_MODE" == "fpm" ]] && a2enmod proxy_fcgi setenvif >>"$LOG" 2>&1
a2ensite piotrack >>"$LOG" 2>&1

apache2ctl configtest >>"$LOG" 2>&1 || die "Apache config test FAILED — piotrack not enabled, existing site untouched. See $LOG"
systemctl reload apache2
ok "Vhost live on port $APP_PORT (port 80 untouched)"

# ============================================================= 8. RUNTIME ===
step "Background work"

cat > /etc/systemd/system/piotrack-worker.service <<EOF
[Unit]
Description=piotrack queue worker
After=network.target mariadb.service mysql.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
ExecStart=${PHP_BIN} ${APP_DIR}/artisan queue:work database --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable --now --quiet piotrack-worker
ok "Queue worker running"

( crontab -u www-data -l 2>/dev/null | grep -Fv "$APP_DIR" || true
  echo "* * * * * cd $APP_DIR && $PHP_BIN artisan schedule:run >> /dev/null 2>&1" ) | crontab -u www-data -
ok "Scheduler cron installed"

# ============================================================== 9. VERIFY ===
step "Verifying"
sleep 2

CODE=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "http://127.0.0.1:${APP_PORT}/" || echo 000)
[[ "$CODE" == "200" ]] && ok "piotrack responds on ${APP_PORT} (HTTP $CODE)" \
  || warn "piotrack returned HTTP $CODE — check $APP_DIR/storage/logs/laravel.log"

UP=$(curl -s --max-time 10 "http://127.0.0.1:${APP_PORT}/up" 2>/dev/null | head -c 60 || true)
[[ -n "$UP" ]] && ok "health endpoint answering"

OCODE=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "http://127.0.0.1/" || echo 000)
[[ "$OCODE" =~ ^(200|30[12])$ ]] && ok "existing helpdesk still serving on port 80 (HTTP $OCODE)" \
  || warn "port 80 returned HTTP $OCODE — verify the helpdesk before you walk away"

systemctl is-active --quiet piotrack-worker && ok "worker active" || warn "worker NOT active"

# ============================================================= 10. SUMMARY ==
step "Done"
cat <<EOF

  ${bold}piotrack is installed.${off}

  URL       ${bold}${URL}${off}
  Sign in   demo@piotrack.test  /  piotrack-demo-2026
            ${ylw}change this password after first login${off}

  Path      ${APP_DIR}
  Database  ${DB_NAME}   (credentials in ${APP_DIR}/.env)
  Log       ${LOG}
$( [[ -n "$OST_DIR" ]] && echo "  Backups   ${BACKUP_DIR}" )

  ${bold}Your helpdesk is untouched on port 80.${off}

  ${bold}To remove piotrack completely${off}
    sudo systemctl disable --now piotrack-worker
    sudo rm -f /etc/systemd/system/piotrack-worker.service
    sudo a2dissite piotrack && sudo systemctl reload apache2
    sudo crontab -u www-data -l | grep -v ${APP_DIR} | sudo crontab -u www-data -
    sudo rm -rf ${APP_DIR}
    sudo mysql -e "DROP DATABASE ${DB_NAME}; DROP USER '${DB_USER}'@'localhost';"

EOF
