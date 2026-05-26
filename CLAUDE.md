# Proyecto: B2B SaaS multi-tenant (Laravel 13 + Inertia + Vue 3 + Postgres)

> **Para futuras conversaciones**: este archivo se lee automáticamente al
> trabajar en este directorio. Tiene todo el contexto que se necesita. No
> hace falta que el usuario re-explique decisiones.

---

## Stack

- **Backend**: Laravel 13 + PHP 8.3 + PostgreSQL 16 (con extensión `unaccent`)
- **Frontend**: Inertia.js v2 + Vue 3 (Composition API + `<script setup>`) + Ant Design Vue 4 + Tailwind v4
- **Auth**: Sanctum bearer tokens con abilities
- **Permissions**: Spatie Permission con traits custom
- **Tests**: PHPUnit (Feature + Unit), SQLite in-memory para tests, Postgres para perf
- **Queue**: `database` driver (sin Redis — el usuario no usa Redis a propósito)
- **Storage**: `local` disk (sin S3 — el usuario solo guarda logos, imports, fotos perfil)
- **Build**: Vite + esbuild
- **Dev env**: Windows + Laragon (`C:\laragon\bin\nodejs\node-v22`, `C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64\php.exe`)
- **Prod env**: Digital Ocean droplet (todavía no configurado — el usuario quiere ayuda con eso cuando llegue)

---

## Decisiones de diseño (NO sugerir cambiar)

- **NO Redis**: descartado conscientemente. Sub-1ms con índices Postgres ya cubre. Cache de queries = premature.
- **NO S3 ni storage externo**: solo guarda fotos perfil, logos, imports. Disk `local`.
- **NO webhooks (todavía)**: documentado como feature premium futura.
- **NO observers cross-módulo**: feature futura.
- **NO code splitting agresivo del bundle**: 2.7MB es aceptable hasta tener tráfico.
- **`/dev/null` no aplica**: PowerShell — usar `$null`, `$env:VAR`, backtick para continuación de línea.
- **Comandos que el usuario corre en dev**: solo `php artisan serve`, `npm run dev/build`, `php artisan queue:work`. No quiere más.

---

## Módulo master template: `Customers`

> **PROPÓSITO**: Customers es el **patrón de referencia** que el scaffold
> `php artisan make:module` clona para crear módulos de negocio nuevos
> (Products, Sales, Categories, Brands, etc.).

### Por qué Customer es el master

Customer tiene todo lo que un módulo de negocio multi-tenant necesita:

- `BelongsToTenant` trait → cada workspace ve solo sus registros (con super bypass).
- Rutas con `permission:X.action` por acción (granular).
- `tenant_id` nullable + `HideSuperScope` automático.
- Audit log polimórfico + soft-delete + trash + restore + force-delete.
- Bulk ops auto-async (> 200 registros), undo 60s, duplicate, edit-all batch.
- Exports (CSV streaming + Excel/PDF/Word async) con límites por formato + memory_limit.
- Import 3-layer dedup + preview/commit two-phase.
- Favoritos polimórficos + recent items + saved views + column selector.
- Plan gating vía `FeatureGate` + `config/features.php`.
- Mobile responsive + dark theme + i18n full (es/en — pt no existe aunque algunos docs viejos lo mencionen).

### Scaffold disponible

```bash
php artisan make:module {Name} --group=BusinessManagement
```

Genera ~50 archivos (controller, service, model, 9 FormRequests, 6 Jobs,
3 Exports, 1 Import, 6 Pages Vue, 13 Components, config + i18n × 2 idiomas (es/en),
migration, factory). Auto-registra el módulo en la tabla `system_modules`,
appendea routes, y agrega entries en `config/polymorphic.php` + `config/purge.php`.

El módulo generado trae 2 campos base: `name` (required) + `description` (text nullable).
Las columnas custom del dominio (price, stock, FKs) se agregan a mano post-scaffold
editando la migration.

El comando vive en `app/Console/Commands/MakeModuleCommand.php`.
Detalle completo del scaffold en [`docs/CREATE-MODULE.md`](docs/CREATE-MODULE.md).

### Lo que el scaffold NO automatiza (manual post-scaffold)

- Entrada en sidebar: `resources/js/Layouts/AppLayout.vue` + `resources/lang/{es,en,pt}/sidebar.php`
- Permisos en `database/seeders/RolesAndPermissionsSeeder.php`
- Plan features específicos en `config/features.php`
- Columnas custom de la migration (FKs, índices del dominio)
- Si tiene FKs entrantes: array `dependents()` del modelo
- Capa API REST opcional (Resource + ApiController + rutas en `routes/api.php`).
  Los módulos generados son web-only (Inertia) por defecto. Solo Customer
  expone API hoy, como patrón de referencia.

---

## Cómo correr cosas en este proyecto

### Tests
```bash
# Filtrado por módulo
& cmd /c "C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64\php.exe artisan test --filter=Customer 2>&1" | Select-Object -Last 5

# Suite completa
& cmd /c "C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64\php.exe artisan test 2>&1" | Select-Object -Last 5

# Perf (skipea sin Postgres)
& cmd /c "C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64\php.exe artisan test --group=performance 2>&1"
```

### Build
```powershell
$env:Path = "C:\laragon\bin\nodejs\node-v22;$env:Path"
& cmd /c "npm run build 2>&1" | Select-Object -Last 3
```

### Migrations
```powershell
& cmd /c "C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64\php.exe artisan migrate --force 2>&1" | Select-Object -Last 8
```

### Si esbuild se cuelga (raro en Windows)
```powershell
Get-Process | Where-Object { $_.Name -match 'esbuild|node' } | Stop-Process -Force
```

---

## Convenciones de feedback que el usuario espera

- Sin emojis (a menos que él los pida)
- Honestidad brutal — si pregunta "está al 100?" y NO está, decírselo
- Sin elogios redundantes ("excelente pregunta!")
- Respuestas cortas a preguntas cortas
- Code edits con `Edit` tool, no `Write` completo
- Validation siempre antes de afirmar "está hecho" (build + tests)
- Cuando él dice "haz todo lo que tengas que hacer", actuar autonomous
- Español neutro estricto: NO argentinismos (vos/tenés/podés/verificá/abrí/hacé/querés/usá/cambiá/editá/probá/acá/etc.) en código NI en respuestas

---

## Flujo de deploy a produccion (explicar SIEMPRE al inicio cuando aplique)

El usuario tiene poca experiencia con Git/GitHub workflows. Si en el chat
vas a hacer cambios de codigo que necesiten llegar a produccion
(droplet Digital Ocean del usuario), **al INICIO del chat** (o
antes del primer commit) explicale el flujo en lenguaje simple. Recordatorio
honesto al usuario: Claude a veces rompe cosas — el rollback es la red de
seguridad, NO una rareza. Mencionalo si el cambio toca algo no-trivial.

### Los 5 pasos del flujo

1. Yo trabajo en la rama `claude/<nombre>` que me asignaron.
2. Commit + push a esa rama.
3. Yo creo el PR (Pull Request) de esa rama hacia `main`.
4. Yo mergeo el PR (o si el cambio es sensible le pido confirmacion).
5. El usuario entra por SSH al droplet y corre los comandos de actualizacion.

### Paso 0 — Como encontrar la ruta del proyecto en el VPS

Si el usuario no recuerda donde vive la app, primero hace SSH al droplet y
corre UNO de estos para encontrarla:

```bash
sudo find /var/www /home -name "artisan" -type f 2>/dev/null
# o
sudo grep -r "root" /etc/nginx/sites-enabled/ | grep -v "#"
```

**Ruta conocida actual: `/var/www/crm-base`** (Laravel app, owner tipicamente
`www-data`). Si en un chat futuro el usuario confirma otra ruta, actualiza
este archivo.

### Paso 5 — Comandos exactos para actualizar el VPS

```bash
cd /var/www/crm-base
git checkout main
git pull origin main
php artisan config:clear
php artisan route:clear
php artisan view:clear
sudo systemctl reload php8.3-fpm
```

Si hay error de permisos, prefijar `sudo` o `sudo -u www-data`. Si la version
de PHP-FPM no es 8.3, detectarla con:
`sudo systemctl list-units --type=service | grep php`.

Si el cambio incluye:
- **migraciones**: agregar `php artisan migrate --force` antes del reload.
- **paquetes composer**: agregar `composer install --no-dev --optimize-autoloader`.
- **frontend (Vue/JS/CSS)**: agregar `npm ci && npm run build`.
- **rutas/config**: agregar `php artisan config:cache && php artisan route:cache`
  DESPUES de limpiar (re-cachear con la version nueva).

### Rollback — opciones de menor a mayor riesgo

**1. Revert via GitHub (recomendado, lo hago yo):**
   - Boton "Revert" en el PR mergeado crea un PR nuevo que deshace el cambio.
   - Merge ese segundo PR. En el VPS: `git pull` normal. Listo.
   - Lo puedo ejecutar con la tool `mcp__github__create_pull_request` apuntando
     a la rama `revert-<n>-<branch>` que crea GitHub automaticamente.

**2. Rollback rapido en el VPS (parche temporal):**
   ```bash
   cd /var/www/crm-base
   git log --oneline -5                # copiar SHA del commit anterior al fix
   git checkout <SHA>
   php artisan config:clear && php artisan view:clear
   sudo systemctl reload php8.3-fpm
   ```
   Deja el VPS en "detached HEAD". Despues hacer Opcion 1 y volver a
   `git checkout main && git pull`.

**3. NUNCA hacer:** `git reset --hard` + `git push --force` sobre `main`.
   Reescribe historial. Prohibido.

### Casos especiales de rollback

| Tipo de cambio       | Rollback adicional necesario                                  |
|----------------------|---------------------------------------------------------------|
| Solo PHP/JS/CSS      | Trivial: revert + pull                                        |
| Migracion nueva      | `php artisan migrate:rollback` ANTES del git revert           |
| Cambio en `.env`     | Revert NO toca `.env` — arreglar a mano                       |
| Paquete composer     | Revert + `composer install`                                   |
| Bundle (Vue/Vite)    | Revert + `npm run build`                                      |

### Notas importantes

- NO tengo SSH al droplet — el paso 5 lo corre SIEMPRE el usuario.
- NO tengo acceso al `.env` ni a la DB de produccion.
- El banner amarillo de GitHub "had recent pushes / Compare & pull request"
  es solo una sugerencia, NO significa que el PR ya exista. El PR existe solo
  cuando aparece en la pestaña "Pull requests" del repo con un numero (#1, #2).
- Mergear un PR NO borra el PR ni el codigo viejo — todo queda en el historial
  de `main` para siempre y se puede revertir cuando sea.
- Si el cambio es trivial y de bajo riesgo, mergeo yo solo despues de avisar.
  Si toca migraciones, infra, auth, pagos, permisos o algo critico, pido OK
  explicito antes de mergear.
