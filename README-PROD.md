# Guía de producción — DigitalOcean Deploy

Todo lo que necesitas para llevar el sistema a producción de forma segura. Provisionar el servidor, hardening, configurar BD, queue workers, crons, backups, monitoring.

> **Antes de leer esto**, asegúrate de entender los conceptos en [README.md](README.md) y haber leído [`docs/CRONS-AND-SETTINGS.md`](docs/CRONS-AND-SETTINGS.md) para entender las 4 capas (cron del SO → scheduler → comandos → settings).

---

## Resumen ejecutivo — lo que vas a tener en prod

| Componente | Tecnología | Donde corre |
|---|---|---|
| Web server | nginx | Droplet (recibe HTTPS, sirve estáticos) |
| App backend | Laravel 13 + PHP 8.3-FPM | Droplet (procesa requests) |
| BD | PostgreSQL 16 con `unaccent` | Droplet (local socket) o Managed DB |
| Queue worker | `php artisan queue:work` via Supervisor | Droplet (proceso persistente) |
| Cron scheduler | `php artisan schedule:run` | Crontab del SO (cada minuto) |
| Cron backup BD | `pg_dump` | Crontab del SO (diario, fuera de Laravel) |
| HTTPS | Let's Encrypt (Certbot) | nginx |
| Storage | `local` disk en `storage/app/public` + symlink | Droplet (o Spaces si crece) |
| Mail | SMTP (Mailgun / SES / Postmark) | Externo |
| Backups | pg_dump diario a `/var/backups/` | Crontab del SO |

---

## 1. Provisionar el droplet

### 1.1. Crear droplet

- **Ubuntu 24.04 LTS** (o 22.04 si prefieres stable mayor)
- **2 vCPU / 4 GB RAM / 80 GB SSD** ($24/mes) recomendado
- Si arrancas con poco tráfico, 1 vCPU / 2 GB ($12/mes) alcanza
- Región: la más cercana a tus clientes

### 1.2. Memoria Swap (CRITICO para `npm run build` si Droplet tiene 1 GB de RAM)

Sin swap, `npm run build` se mata con "JavaScript heap out of memory" en droplets de 1 GB. Lo configuras **permanente** asi:

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile

# Persistir despues de reboots — agregar a /etc/fstab
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab

# Verificar
free -h
```

Debes ver `Swap: 2.0Gi` en el output del `free -h`. Si no aparece, algo fallo.

### 1.3. Usuario no-root

NUNCA trabajes como root. Crea un usuario dedicado:

```bash
adduser deploy
usermod -aG sudo deploy
mkdir -p /home/deploy/.ssh
cp ~/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys

# Prueba login con deploy desde tu PC
ssh deploy@<ip-del-droplet>
```
### 1.4. Firewall (UFW)

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 2222/tcp     # tu puerto SSH custom
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

Solo HTTP/HTTPS + SSH custom. Todo lo demás bloqueado.

### 1.5. SSH hardening

Edita `sudo nano /etc/ssh/sshd_config`:

```
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
Port 2222     # cambiar puerto default 22
AllowUsers deploy
```

Si el Droplet tiene Ubuntu 22.04 o 24.04, el sistema ignora el puerto de tu archivo sshd_config a menos que desactives los sockets primero. En tu primera terminal (la que sigue conectada), ejecuta estos comandos para obligar a Ubuntu a leer tu archivo de configuración y aplicar los cambios:

```bash
sudo systemctl disable --now ssh.socket
sudo systemctl enable --now ssh.service
sudo systemctl restart ssh
```

> ⚠️ Prueba de fuego: Abre una SEGUNDA terminal y verifica que puedes loguearte como deploy. Si te equivocaste en algo, te quedas afuera, pero aún tienes la primera terminal abierta para corregirlo.
```bash
ssh -i ~/.ssh/id_digitalocean -p 2222 deploy@<ip-droplet>
```

### 1.6. Fail2ban (anti brute-force)

```bash
sudo apt install -y fail2ban
sudo systemctl enable fail2ban --now
```
Como se cambio de puerto hay que actualizar el fail2ban al nuevo puerto
```bash
sudo nano /etc/fail2ban/jail.local
```

Pegamos el siguiente contenido 
```bash
[DEFAULT]
   # Bloquea la IP por 1 hora si falla 5 veces en 10 minutos
   bantime  = 1h
   findtime = 10m
   maxretry = 5

   [sshd]
   enabled = true
   port    = 2222
   logpath = %(sshd_log)s
   backend = %(sshd_backend)s
```

Reiniciar el servicio fail2ban:
```bash
sudo systemctl restart fail2ban
```

Comandos para comprobar el funcionamiento del fail2ban:
```bash
sudo fail2ban-client status
sudo fail2ban-client status sshd
```

Default config ya cubre SSH. Después se sumará nginx, editando `/etc/fail2ban/jail.local`.

### 1.7. Instalar el stack
```bash
sudo apt update && sudo apt upgrade -y
```

> Si sale un mensaje seleccionar: `keep the local version currently installed`

```bash
sudo apt install -y nginx postgresql-16 redis-server supervisor unzip git curl \
    php8.3-fpm php8.3-{cli,pgsql,mbstring,xml,bcmath,zip,intl,fileinfo,gd,curl,opcache} \
    certbot python3-certbot-nginx
```

> `redis-server` es opcional (la app usa queue `database` driver). Útil si más adelante mueves sesiones HTTP a Redis.

Composer:
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 1.8. Instalar Node.js 20 (LTS)
```bash
# Descargar e importar el repositorio oficial de Node 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -

# Instalar Node.js (esto ya incluye la versión correcta de npm)
sudo apt install -y nodejs
```

## 2. Configurar PostgreSQL

### 2.1. Crear BD + user dedicado

Para generar un password fuerte de 32 caracteres (cópialo y guárdalo), ejecuta en tu terminal normal:
```bash
openssl rand -base64 32
```

Entrar a la consola de PostgreSQL:
```bash
sudo -u postgres psql
```

Paso 1: Creación
```sql
CREATE USER db_user WITH PASSWORD '<password-fuerte-aleatorio>';
CREATE DATABASE db_name OWNER db_user;
```

Paso 2: El cambio de contexto (Espera a ver el mensaje: "You are now connected to database...")
```sql
\c db_name
```

Paso 3: Permisos y salida
```sql
CREATE EXTENSION IF NOT EXISTS unaccent;
REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT ALL ON SCHEMA public TO db_user;
ALTER SCHEMA public OWNER TO db_user;
\q
```

> NO uses el usuario `postgres` superuser para la app. Crea uno dedicado con permisos mínimos.

### 2.2. Bloquear conexiones remotas

Edita `sudo nano /etc/postgresql/16/main/postgresql.conf`:

```
listen_addresses = 'localhost'
```
> Nota: Para buscar usa las teclas CTRL + W y escribir listen_addresses y descomentar esa linea.

Editar configuracion a solo conexiones locales `sudo nano /etc/postgresql/16/main/pg_hba.conf` :

```
# TYPE  DATABASE  USER     ADDRESS         METHOD
local   all       postgres                 peer
local   all       all                      scram-sha-256
host    all       all      127.0.0.1/32    scram-sha-256
```

NUNCA pongas `0.0.0.0/0` ni `host all all all md5`.

Reiniciar Postgres:
```bash
sudo systemctl restart postgresql
```

> Verificar el estado del postgres `sudo systemctl status postgresql`

### 2.3. Alternativa: DigitalOcean Managed Databases

Si quieres delegar backups, failover, scaling: $15/mes. Cambias `DB_HOST` por el endpoint del Managed DB y listo. **Recomendado en serio para producción**.

---

## 3. Deploy del código

### 3.1. Clonar el repo

```bash
sudo mkdir -p /var/www
sudo chown deploy:www-data /var/www
cd /var/www

git clone https://github.com/TU_USUARIO/crm-base.git
cd crm-base
```

### 3.2. Permisos del filesystem

```bash
# Owner: deploy (usuario), grupo: www-data (PHP-FPM)
sudo chown -R deploy:www-data /var/www/crm-base

# Permisos generales: directorios 755, archivos 644
sudo find /var/www/crm-base -type d -exec chmod 755 {} \;
sudo find /var/www/crm-base -type f -exec chmod 644 {} \;

# storage/ y bootstrap/cache/ necesitan escritura por PHP-FPM
sudo chmod -R 775 /var/www/crm-base/storage
sudo chmod -R 775 /var/www/crm-base/bootstrap/cache

# CRITICO: agregar `deploy` al grupo www-data. Sin esto, cuando corras
# `php artisan ...` como deploy y la app trate de escribir al log
# (laravel.log es owner www-data), va a fallar con "Permission denied".
sudo usermod -a -G www-data deploy

# IMPORTANTE: la membership del grupo solo aplica en sesiones nuevas.
# Cierra el SSH y vuelve a entrar, o ejecuta:
newgrp www-data
groups   # debe listar www-data
```

### 3.3. `.env` de producción

```bash
cp .env.example .env
sudo chown deploy:www-data .env
sudo chmod 640 .env
nano .env
```

Configurá:

```env
APP_NAME="Tu App"
APP_ENV=production
APP_DEBUG=false      # NUNCA true en prod
APP_URL=https://midominio.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=baseapp
DB_USERNAME=baseapp
DB_PASSWORD=<password-generado-arriba>

# Mail SMTP (Mailgun, SES, Postmark, o Gmail App Password)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@midominio.com
MAIL_PASSWORD=<app-password-del-proveedor>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@midominio.com
MAIL_FROM_NAME="${APP_NAME}"

# Queue
QUEUE_CONNECTION=database

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_DOMAIN=subdominio.dominio.com
SESSION_SECURE_COOKIE=true     # HTTPS only

# App
APP_LOCALE=es
APP_TIMEZONE=UTC
```

### 3.4. Instalar dependencias

```bash
composer install --no-dev --optimize-autoloader
npm ci
NODE_OPTIONS="--max-old-space-size=2048" npm run build
sudo chown -R deploy:www-data /var/www/crm-base/public/build
```

> `--no-dev` excluye dev dependencies (PHPUnit, Pail, Pint, Sail). Más liviano.

#### CRITICO: dompdf y la carpeta `storage/fonts`

El script `post-autoload-dump` de `composer install` copia las fuentes de dompdf a `storage/fonts/`. **El problema**: ese script corre como `deploy`, pero PHP-FPM corre como `www-data` y necesita escribir ahí en runtime el archivo `dompdf_font_family_cache.php` cada vez que genera un PDF.

Sin permisos correctos, el primer intento de descargar PDF de Factura / Cotización / Orden tira `Permission denied` o el PDF descarga **vacío / corrupto / sin estilo**.

```bash
# Forzar que storage/fonts exista y tenga permisos para www-data
mkdir -p /var/www/crm-base/storage/fonts
sudo chown -R deploy:www-data /var/www/crm-base/storage/fonts
sudo chmod -R 775 /var/www/crm-base/storage/fonts

# Verificar que las fonts base de dompdf estan ahi
ls /var/www/crm-base/storage/fonts/ | head
# Debe listar: DejaVuSans*.ttf, DejaVuSerif*.ttf, etc. (~30 archivos .ttf y .ufm)
# Si está VACIO, el post-autoload-dump fallo silenciosamente — re-ejecutalo:
composer dump-autoload
```

#### CRITICO: `NODE_OPTIONS` permanente

`npm run build` necesita > 512 MB de heap, lo cual Node por default no le da. Sin el `NODE_OPTIONS=--max-old-space-size=2048` (ni siquiera con swap, porque es limite interno de Node, no del SO), el build crashea.

Para que **futuros builds** no requieran prefijar la variable cada vez:

```bash
echo 'export NODE_OPTIONS=--max-old-space-size=2048' >> ~/.bashrc
source ~/.bashrc
```

Despues `npm run build` solo funciona.

Generar la key:
```bash
php artisan key:generate
```

### 3.5. Migrar + sembrar inicial

#### Opcion A — `setup:project --allow-production` (recomendado para PRIMERA vez)

`setup:project` corre `migrate:fresh --seed` + los 8 demo seeders. En prod requiere el flag explicito y doble confirmacion para evitar accidentes:

```bash
php artisan setup:project --allow-production
```

Te va a pedir:
1. `Estas ABSOLUTAMENTE seguro?` → escribe `yes`
2. Confirmacion final: escribe literal `borrar todo` (sin comillas)
3. `Are you sure you want to continue?` → `yes`

Tarda 2-4 minutos. Crea:
- Estructura completa de tablas
- Catalogos base (paises, monedas, planes, settings)
- 8 usuarios humanos demo (Carlos super + 3 admins + 4 workers)
- Tenants Empresa 1, Empresa 2, Independiente
- Suscripciones de cada tenant
- Demo data en todos los modulos (productos, CRM, ventas, ops, mensajes, automations)

> ⚠️ `setup:project` es DESTRUCTIVO. Borra TODAS las tablas existentes. Solo correr en el primer setup o cuando quieras hacer reset total. Para deploys regulares ver Opcion B.

#### Opcion B — Migrate + seed incremental (deploys regulares, NO destructivo)

```bash
php artisan migrate --force                                          # solo migraciones nuevas
php artisan db:seed --class=SettingsSeeder --force                   # si hay settings nuevos
php artisan db:seed --class=SystemModulesSeeder --force              # si hay modulos nuevos
php artisan db:seed --class=RolesAndPermissionsSeeder --force        # si hay permisos nuevos
```

Idempotente: solo agrega lo que falta, no toca data existente.

#### Cambiar passwords demo INMEDIATAMENTE

Los seeders crean usuarios con password `123456` (visible en `UsersSeeder.php`). Antes del primer login publico:

```bash
php artisan tinker
>>> $u = App\Models\User::where('email', 'carlos@gmail.com')->first();
>>> $u->password = bcrypt('<password-fuerte-real-de-carlos>');
>>> $u->save();
>>> exit
```

Repetir para cada user que pretendas usar (`antonio@gmail.com`, `hector@gmail.com`, etc.) o borrar los que no necesites. La lista completa esta en `database/seeders/UsersSeeder.php`.

### 3.6. Storage symlink

```bash
php artisan storage:link
```

Sin esto las imágenes de usuarios y logos de workspaces devuelven 404.

### 3.7. Cache de optimización

```bash
php artisan config:cache
php artisan view:cache
php artisan event:cache
```

> Tras cada deploy de código nuevo, repetir esos 4 + `npm run build`.

---

## 4. nginx + HTTPS

### 4.1. Configurar virtualhost

```bash
sudo mkdir -p /etc/nginx/sites-available
sudo mkdir -p /etc/nginx/sites-enabled
sudo nano /etc/nginx/sites-available/baseapp
```

Pegar el codigo:
```nginx
server {
    listen 80;
    server_name subdominio.midominio.com;

    root /var/www/crm-base/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=()" always;

    client_max_body_size 10M;

    location ~ /\.(?!well-known) { deny all; }
    location ~ ^/(storage|bootstrap)/ { deny all; }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff2?)$ {
        expires 7d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    access_log /var/log/nginx/crm-base.access.log;
    error_log /var/log/nginx/crm-base.error.log;
}
```

Activar:
```bash
sudo ln -s /etc/nginx/sites-available/baseapp /etc/nginx/sites-enabled/
sudo nginx -t       # verificar syntax
sudo systemctl reload nginx
```

### 4.2. SSL con Let's Encrypt

```bash
sudo certbot --nginx -d subdominio.midominio.com
```

Certbot agrega automáticamente los bloques `ssl_certificate*` al nginx config + cron de auto-renovación.

### 4.3. PHP-FPM tuning

Edita `/etc/php/8.3/fpm/php.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0      ; ⚠️ 0 = NO revalida — tras deploy reload PHP-FPM
opcache.jit=1255
opcache.jit_buffer_size=128M

upload_max_filesize=10M
post_max_size=12M
memory_limit=256M
max_execution_time=120
```

Reiniciar PHP-FPM:
```bash
sudo systemctl restart php8.3-fpm
```

> Tras cada deploy: `sudo systemctl reload php8.3-fpm` para que opcache tome los archivos nuevos.

### 4.4. Eliminar la página por defecto de Nginx

Eliminar la pagina de inicio para que los bots no busque la ip del drople, por ejemplo si entro a la ip del droplet en un navegador saldra error 404.
```bash
sudo rm /etc/nginx/sites-enabled/default
sudo systemctl reload nginx
```

---

## 5. Queue worker con Supervisor

**Sin esto los exports/emails/automations NUNCA se procesan.**

`/etc/supervisor/conf.d/baseapp-queue.conf`:

```ini
[program:baseapp-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/crm-base/artisan queue:work --queue=default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=deploy
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/baseapp-queue.log
stopwaitsecs=3600
```

Activar:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start baseapp-queue:*

# Ver status
sudo supervisorctl status baseapp-queue:*
```

**Cada deploy** que toca código de Jobs: `sudo supervisorctl restart baseapp-queue:*` (o `php artisan queue:restart` que los recicla en el próximo job).

---

## 6. Crons (las 3 entradas críticas)

`crontab -e` como usuario `deploy`:

```cron
# 1) Laravel scheduler — dispara TODOS los schedules internos de Laravel
* * * * * cd /var/www/crm-base && php artisan schedule:run >> /dev/null 2>&1

# 2) Backup BD diario a las 02:00 (independiente de Laravel)
0 2 * * * pg_dump -U baseapp baseapp | gzip > /var/backups/baseapp-$(date +\%Y\%m\%d).sql.gz

# 3) Limpieza de backups viejos (más de 14 días)
5 2 * * * find /var/backups/baseapp-*.sql.gz -mtime +14 -delete
```

### Verificar que los schedules de Laravel se disparan

```bash
php artisan schedule:list
```

Deberías ver:
- `app:cleanup-expired-downloads` (cada hora)
- `app:purge-soft-deleted` (diario 03:00 + 04:00)
- `subscriptions:check-expirations` (diario 03:00)
- `automations:tick` (cada minuto)

Detalle completo en [`docs/CRONS-AND-SETTINGS.md`](docs/CRONS-AND-SETTINGS.md).

---

## 7. Backups de BD

### Backup manual (cuando lo necesites)

```bash
pg_dump -U baseapp baseapp | gzip > /tmp/baseapp-manual-$(date +%Y%m%d-%H%M).sql.gz
```

### Backup automático ya cubierto

Cron diaria 02:00 + retención 14 días (sección 6 arriba).

### Restaurar un backup

```bash
gunzip < /var/backups/baseapp-20260516.sql.gz | psql -U baseapp baseapp
```

> Antes de restaurar, haz un backup del estado actual por si quieres volver.

### Backups off-site (recomendado producción seria)

Los backups en el mismo droplet no protegen contra "se rompió el droplet". Opciones:
- **DigitalOcean Spaces** ($5/mes 250 GB) — copiar el dump con `rclone` o `s3cmd`
- **Managed DB** ($15/mes) — backups gestionados incluidos
- **Backblaze B2** ($6/TB/mes) — más barato para volumen grande

Ejemplo con Spaces (cron diario):
```cron
10 2 * * * s3cmd put /var/backups/baseapp-$(date +\%Y\%m\%d).sql.gz s3://mi-bucket/backups/
```

---

## 8. Settings — qué configurar al primer login

Una vez deployado, login como super y entrá a Sidebar → **Configuración**. Revisá estos:

| Setting | Default | Qué cambiar |
|---|---|---|
| `app.name` | "Application Name" | Tu marca real |
| `app.support_email` | `soporte@example.com` | Tu email de soporte real |
| `features.subscription_enforcement_enabled` | `false` | Cambiar a `true` cuando tu billing esté listo |
| `notifications.email_enabled` | `true` | Mantener `true` en prod |
| `downloads.expire_after_hours` | 24 | Ajustar si exports muy grandes (subir) o ahorrás espacio (bajar) |
| `downloads.grace_hours` | 24 | Idem |

Lista completa de los 23 settings: [`docs/CRONS-AND-SETTINGS.md`](docs/CRONS-AND-SETTINGS.md#3-settings-disponibles-23-keys-en-9-grupos).

> El sender de email (`MAIL_FROM_NAME`, `MAIL_FROM_ADDRESS`) vive en `.env`, no en Settings. Las credenciales SMTP (`MAIL_USERNAME`, `MAIL_PASSWORD`) siempre en `.env` — jamás en BD.

---

## 9. SMTP — configuración del envío de correos

Guía completa paso a paso (Gmail App Password, Mailgun, AWS SES, Postmark) con troubleshooting:

**Ver [`docs/MAIL-SETUP.md`](docs/MAIL-SETUP.md)**.

Lo crítico para prod:

- `MAIL_PASSWORD` SIEMPRE en `.env` (jamás en BD)
- Tras tocar variables `MAIL_*`: `php artisan config:clear && php artisan queue:restart` (los workers viejos tienen la config vieja en memoria)
- Configurar SPF + DKIM + DMARC en el DNS del dominio (evita que los emails caigan a spam)
- Para volumen real: Mailgun, SES o Postmark — Gmail tope ~500/día

Toggle global de emails (sin tocar `.env`): setting `notifications.email_enabled` en `/system_management/settings`. En `false` silencia todos los emails (siguen apareciendo en la campana).

---

## 10. Anti SQL injection — ya cubierto

La app está protegida estructuralmente. Verificar que ningún PR futuro rompa esto:

- ✅ **Eloquent ORM + Query Builder** en todos lados → prepared statements automáticos
- ✅ **`whereRaw('unaccent(LOWER(name)) = unaccent(LOWER(?))', [$name])`** → parameter binding
- ✅ **Sort/direction validados** con `in_array(['asc', 'desc'])` + `in_array(['id', 'name', ...])`
- ✅ **Filtros + IDs vienen vía FormRequest** → tipos validados antes de query

Si alguien futuro escribe `DB::statement("SELECT * FROM x WHERE name = '{$name}'")` → vulnerable. NO hay un solo caso así hoy (`grep -r "DB::statement" app/`).

---

## 11. Rate limiting

Ya implementado a 3 niveles:

| Limit | Configurado en | Default |
|---|---|---|
| API global | `app/Providers/AppServiceProvider.php` | 60 req/min por token o IP |
| Exports | `routes/*.php` → `throttle:5,1` | 5 req/min por user |
| Bulk operations | `routes/*.php` → `throttle:10,1` | 10 req/min |
| Login | TODO (sin throttle hoy, futuro con `security.max_login_attempts` setting) | — |

---

## 12. Monitoring + logs

### Logs Laravel

```bash
tail -f /var/www/crm-base/storage/logs/laravel.log
```

Rotación automática (Laravel por default, 14 días).

### Logs nginx

```bash
tail -f /var/log/nginx/crm-base.access.log
tail -f /var/log/nginx/crm-base.error.log
```

### Logs supervisor (queue worker)

```bash
tail -f /var/log/supervisor/baseapp-queue.log
```

### Logs específicos de comandos

- `storage/logs/cleanup-downloads.log` — cleanup hourly
- `storage/logs/purge.log` — purge nightly

### Sentry (error tracking — futuro)

Hoy no está activado. Cuando quieras: `composer require sentry/sentry-laravel` + configurar DSN en `.env`. Detalle: [`docs/SENTRY.md`](docs/SENTRY.md).

### Métricas server

```bash
# CPU + RAM
htop

# Disco
df -h
du -sh /var/www/crm-base/storage/   # ¿está creciendo?

# Connections Postgres
sudo -u postgres psql -c "SELECT count(*) FROM pg_stat_activity;"

# Active queues
php artisan queue:monitor   # Laravel 11+ (si se activa)
```

---

## 13. Workflow de deploy continuo

Despues del primer deploy, los siguientes:

```bash
# Como deploy en el server
cd /var/www/crm-base

# 1. Pull nuevo codigo
git pull origin main

# 2. Reinstalar deps si composer.lock o package-lock cambiaron
composer install --no-dev --optimize-autoloader
npm ci

# 3. Migrate nuevo (si hay migraciones nuevas)
php artisan migrate --force

# 4. Re-seedear settings/permisos/modulos si hay nuevos
php artisan db:seed --class=SettingsSeeder --force
php artisan db:seed --class=SystemModulesSeeder --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force

# 5. Rebuild frontend (solo si toco Vue/JS/CSS)
npm run build      # ya tiene NODE_OPTIONS desde .bashrc
sudo chown -R deploy:www-data /var/www/crm-base/public/build

# 6. LIMPIAR caches viejos (NO `:cache` — clearear primero)
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# 7. Re-cachear (opcional, mejora performance pero requiere clear primero)
php artisan config:cache
php artisan view:cache
php artisan event:cache

# 8. Reload PHP-FPM (opcache toma archivos nuevos)
# ⚠️ CRITICO: con opcache.validate_timestamps=0 (default recomendado para prod),
# PHP-FPM SIEMPRE sirve el codigo cacheado en memoria hasta este reload, aunque
# el `git pull` haya traido el archivo nuevo. Sintoma comun: "el bug que ya
# arregle sigue saliendo en prod" — el archivo en disco esta bien pero opcache
# sirve el viejo. Este reload es OBLIGATORIO despues de CUALQUIER git pull
# que toque archivos PHP.
sudo systemctl reload php8.3-fpm

# 9. Reciclar queue workers
sudo supervisorctl restart baseapp-queue:*
# o equivalente:
php artisan queue:restart
```

### Si despues del deploy los usuarios siguen viendo cosas viejas

Despues de `npm run build`, el navegador puede seguir sirviendo el bundle JS cacheado anteriormente. Soluciones:

- **Usuario individual**: hard refresh con `Ctrl + Shift + R` (Windows/Linux) o `Cmd + Shift + R` (Mac), o ventana incognito.
- **Cloudflare como CDN**: Dashboard de Cloudflare → Caching → Configuration → "Purge Everything".
- **Verificar que el bundle se actualizo**: `ls -la public/build/manifest.json` — la fecha debe ser de la fecha del deploy. Si es vieja, `npm run build` no termino.

### Automatizar con GitHub Actions

Cuando estés listo, automatizar con un workflow `.github/workflows/deploy.yml` que haga SSH al droplet y corra los pasos de arriba. Feature futura, no crítica para arrancar.

---

## 14. Checklist pre-go-live

Antes de mostrar el sistema a un cliente real:

- [ ] Droplet provisionado con Ubuntu LTS
- [ ] Swap configurado y persistente en /etc/fstab (seccion 1.2)
- [ ] Usuario `deploy` no-root con SSH key, password auth deshabilitada
- [ ] `deploy` agregado al grupo `www-data` (seccion 3.2)
- [ ] UFW activo (solo 80/443/SSH custom)
- [ ] Fail2ban corriendo
- [ ] PostgreSQL 16 con `unaccent`, usuario dedicado, conexiones solo local
- [ ] HTTPS con Let's Encrypt funcionando
- [ ] Security headers en nginx config
- [ ] PHP-FPM con opcache + JIT activado
- [ ] `.env` con `APP_DEBUG=false`, `chmod 640`
- [ ] `NODE_OPTIONS=--max-old-space-size=2048` permanente en ~/.bashrc del deploy user
- [ ] `php artisan setup:project --allow-production` ejecutado (o migrate + seeds equivalente)
- [ ] Password del super (`carlos@gmail.com`) CAMBIADO inmediatamente — NO dejar el demo `123456`
- [ ] Passwords de admins (`antonio@gmail.com`, `hector@gmail.com`, etc.) cambiados o usuarios eliminados
- [ ] `php artisan storage:link` ejecutado
- [ ] `config/view/event:cache` ejecutados
- [ ] Supervisor con queue worker activo + auto-restart
- [ ] Cron `schedule:run` cada minuto
- [ ] Cron `pg_dump` diario 02:00
- [ ] SMTP configurado y probado (mandar un email de prueba)
- [ ] Settings revisados: `app.name`, `app.support_email`, `features.subscription_enforcement_enabled`
- [ ] DNS apuntando al droplet
- [ ] Browser test: login OK como super, crear tenant OK, crear usuario OK, export OK
- [ ] Mobile test: el sistema es responsive

---

## 15. Si algo se rompe en producción

### "Tira 500 en todo"

1. `tail -100 /var/www/crm-base/storage/logs/laravel.log` — leer el último error
2. Permisos de `storage/` y `bootstrap/cache/` — `chmod -R 775`
3. `.env` con `APP_DEBUG=true` temporal para ver el error (NO dejar así)

### "Login funciona pero todo da 401/redirect"

1. Cookies con `SESSION_SECURE_COOKIE=true` pero el sitio no es HTTPS → revisar nginx + Certbot
2. `SESSION_DRIVER=database` pero la tabla `sessions` no existe → `php artisan session:table && migrate`

### "Exports nunca llegan"

1. ¿Queue worker corriendo? `sudo supervisorctl status baseapp-queue:*`
2. ¿Hay jobs failed? `php artisan queue:failed` — si hay, ver el error y `queue:retry all`
3. ¿Setting `notifications.email_enabled` está en true?
4. ¿SMTP funciona? `php artisan tinker` → `Mail::raw('test', fn($m) => $m->to('tu@email.com')->subject('test'));`

### "Imágenes 404"

`php artisan storage:link` — se pierde con cada `git pull` si alguien borró el symlink.

### "PDF descarga vacío, corrupto o tira 500" (dompdf / fuentes)

Sintomas:
- `Permission denied` al hacer click en "PDF" de Factura/Cotización/Orden
- PDF descarga 0 KB
- PDF abre pero **sin estilos** (texto plano sin formato)
- Error 500 con `DOMPDF_FONT_DIR is not writable` en `storage/logs/laravel.log`

Causa: `storage/fonts/` no existe o no tiene permisos para que PHP-FPM (`www-data`) escriba el cache `dompdf_font_family_cache.php`.

Fix:
```bash
cd /var/www/crm-base

# 1. Asegurar que el dir existe + tiene permisos
sudo mkdir -p storage/fonts
sudo chown -R deploy:www-data storage/fonts
sudo chmod -R 775 storage/fonts

# 2. Verificar que las fonts base estan ahi
ls storage/fonts/ | wc -l
# Debe ser > 20 (DejaVuSans*, Helvetica*, etc). Si esta vacio:
composer dump-autoload     # re-ejecuta el post-autoload-dump que copia las fonts

# 3. Limpiar el cache viejo y reload
rm -f storage/fonts/dompdf_font_family_cache.php
sudo systemctl reload php8.3-fpm

# 4. Probar
# En el browser, abrir cualquier factura y darle a "PDF" → deberia abrir el PDF.
```

Verifica los logs si sigue fallando:
```bash
tail -50 storage/logs/laravel.log | grep -i "dompdf\|font"
```

### "BD lenta"

```bash
sudo -u postgres psql baseapp
EXPLAIN ANALYZE SELECT ...    # query lenta
\d+ customers                 # ver indexes de una tabla
```

Si una query no usa índice, considera agregarlo. Los principales ya están en las migraciones.

### `npm run build` se mata con "JavaScript heap out of memory"

Sintomas:
```
FATAL ERROR: Reached heap limit Allocation failed - JavaScript heap out of memory
Aborted (core dumped)
```

Causa: Node por default solo usa ~512 MB de heap. En droplets de 1 GB de RAM con un proyecto Vite grande, no alcanza.

Solucion:
1. Confirma que swap esta activo: `free -h` → debe mostrar Swap > 0.
2. Confirma que `NODE_OPTIONS` esta seteado: `echo $NODE_OPTIONS` → debe imprimir `--max-old-space-size=2048`.
3. Si NO esta, ejecuta lo de la seccion 3.4 (echo a .bashrc + source).

### `setup:project` falla con "Permission denied" en laravel.log

Sintomas:
```
The stream or file "/var/www/crm-base/storage/logs/laravel.log" could not be opened in append mode: Failed to open stream: Permission denied
```

Causa: el archivo de log es owner `www-data` (porque nginx escribe ahi), pero estas corriendo `artisan` como `deploy`, que no esta en el grupo `www-data`.

Solucion (seccion 3.2): `sudo usermod -a -G www-data deploy` + cerrar/abrir SSH (o `newgrp www-data`).

### Sidebar muestra `sidebar.X` como texto crudo en vez de la traduccion

Sintoma: en la navegacion aparece `sidebar.customers` o `sidebar.products` literal, en vez de "Clientes" o "Productos".

Causa: el bundle JS llama a `t('sidebar.X')` pero el archivo `resources/lang/*/sidebar.php` no tiene esa clave (o el bundle es viejo).

Solucion:
1. Verifica que la clave existe en `resources/lang/es/sidebar.php` y `resources/lang/en/sidebar.php`.
2. Re-corre `npm run build`.
3. Hard refresh del navegador.

### Usuario logueado no ve los modulos esperados en el sidebar

Sintoma: super o admin ve un sidebar casi vacio aunque deberia ver todo.

Causa #1: el rol no esta asignado al user. Verifica:
```bash
php artisan tinker
>>> App\Models\User::where('email', 'carlos@gmail.com')->first()->roles->pluck('name');
# Debe devolver Collection ['super']
```

Causa #2: el modulo no esta registrado en `system_modules` table. Los permisos `<module>.view` solo se generan iterando esa tabla. Si falta un modulo, revisa `database/seeders/SystemModulesSeeder.php` y re-corre:
```bash
php artisan db:seed --class=SystemModulesSeeder --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
```

Causa #3: cache de sesion del navegador. Cerrar sesion, refresh, volver a loguearse.

### Detalle completo de errores

[`docs/TROUBLESHOOTING.md`](docs/TROUBLESHOOTING.md).

---

## 16. Mantenimiento mensual

Una vez al mes:

- [ ] Verificar que los backups se están generando (`ls -la /var/backups/`)
- [ ] Restore test: bajar un backup y restaurarlo en una BD de prueba
- [ ] Actualizar SO: `sudo apt update && sudo apt upgrade -y`
- [ ] Reiniciar PHP-FPM + Postgres después
- [ ] Renovar SSL si Certbot falló (`sudo certbot renew --dry-run` para verificar)
- [ ] Revisar `storage/logs/` — si `laravel.log` pasa los 100 MB, rotar manualmente
- [ ] Revisar uso de disco (`df -h`, `du -sh /var/www/crm-base/storage/`)
- [ ] Revisar audit logs por anomalías (acciones sospechosas)
- [ ] Revisar usuarios super-only (¿alguien debería tener menos privilegios?)

---

## 17. Escalar más adelante

Si el droplet 2GB queda chico:

| Síntoma | Solución |
|---|---|
| CPU al 80%+ constante | Subir a 4GB / 4 vCPU ($48/mes) |
| Disco se llena | Migrar `storage/` a DigitalOcean Spaces (S3-compat) |
| BD lenta con + de 5 workspaces grandes | Pasar a Managed DB ($15/mes) o droplet dedicado para Postgres |
| Tráfico > 1000 req/min | Load balancer + 2 droplets app + 1 droplet BD |
| Queue saturado | Subir `numprocs=2` → `numprocs=4` en supervisor |

Caveat — la app **no usa Redis hoy** (decisión deliberada). Si escalas a múltiples app servers, vas a necesitar Redis para sesiones compartidas.

---

## 18. Documentación complementaria

| Documento | Para qué |
|---|---|
| [`docs/DEPLOY.md`](docs/DEPLOY.md) | Guía paso a paso de deploy (complementa este README) |
| [`docs/CRONS-AND-SETTINGS.md`](docs/CRONS-AND-SETTINGS.md) | Las 4 capas + 23 settings con su efecto |
| [`docs/TROUBLESHOOTING.md`](docs/TROUBLESHOOTING.md) | Errores comunes |
| [`docs/SENTRY.md`](docs/SENTRY.md) | Setup de error tracking (cuando quieras agregar) |
