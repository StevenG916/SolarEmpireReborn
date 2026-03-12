# Solar Empire Reborn — CLAUDE.md

This file is the primary reference for AI-assisted development on this project.
Read this before making any changes.

---

## Project Overview

**Solar Empire Reborn** is a modernized fork of Solar Empire v1.3, a browser-based
space strategy game originally released on SourceForge in 2004 by Moriarty.

The goal is to make it self-hostable by a small friend group using Docker, running
on modern PHP 8.3 and MySQL 8.4, accessible via a Cloudflare tunnel.

**GitHub:** https://github.com/StevenG916/SolarEmpireReborn  
**Local dev path:** `C:\Projects\SolarEmpireV1`

---

## Stack

| Layer     | Technology                        |
|-----------|-----------------------------------|
| Language  | PHP 8.3                           |
| Web server| Apache (via `php:8.3-apache` image)|
| Database  | MySQL 8.4                         |
| Container | Docker + Docker Compose           |
| Hosting   | Cloudflare Tunnel → localhost:8080|

---

## Repository Structure

```
/
├── inc/                    # Core include files
│   ├── common.inc.php      # DB wrappers, auth, utility functions — loaded everywhere
│   ├── config.inc.php      # All config via env vars, URL helpers
│   ├── maint.inc.php       # DB connection for CLI maintenance scripts (CLI only)
│   ├── session_funcs.inc.php
│   ├── user.inc.php
│   ├── admin.inc.php
│   ├── generator.funcs.php # Universe generation
│   ├── system.class.php
│   └── ...
├── docs/
│   └── sql/
│       ├── new_server.sql  # Server-level tables (run once at setup)
│       └── new_game.sql    # Per-game tables using "gamename_" placeholder
├── docker/
│   ├── entrypoint.sh       # Starts cron + Apache inside container
│   └── mysql/init/
│       └── 02_new_game.sh  # Substitutes gamename_ → game1_ and seeds se_games row
├── img/                    # Static assets + runtime-generated universe maps
├── css/                    # Stylesheets (style1.css through styleN.css)
├── js/                     # Client-side JS
├── Dockerfile              # Clones repo from GitHub, builds PHP+Apache image
├── docker-compose.yml      # Local dev — uses context: . (local files)
├── docker-compose.deploy.yml # Remote deploy — uses context: GitHub URL
├── .env.example            # Template — copy to .env and fill in
└── setup.sh                # One-liner remote deploy helper
```

---

## Two Compose Files — Important

| File | Purpose | Used by |
|------|---------|---------|
| `docker-compose.yml` | **Local dev** — builds from local files (`context: .`) | You, from `C:\Projects\SolarEmpireV1` |
| `docker-compose.deploy.yml` | **Remote deploy** — clones from GitHub | Self-hosters with empty directory |

**Always use the default** (`docker compose up -d --build`) from `C:\Projects\SolarEmpireV1`.  
Never use `-f docker-compose.deploy.yml` locally — it clones from GitHub and won't have your uncommitted changes.

---

## Environment Variables

All config is via `.env` (never committed). See `.env.example` for the full list.

| Variable | Used in | Purpose |
|----------|---------|---------|
| `DB_HOST` | `config.inc.php` | MySQL hostname (default: `db` in Docker) |
| `DB_NAME` | `config.inc.php` | Database name |
| `DB_USER` | `config.inc.php` | Database user |
| `DB_PASSWORD` | `config.inc.php` | Database password |
| `MYSQL_ROOT_PASSWORD` | `docker-compose.yml` | MySQL root password |
| `SE_GAME_NAME` | `02_new_game.sh` | Table prefix, e.g. `game1` → `game1_users` etc. |
| `SE_GAME_DISPLAY_NAME` | `02_new_game.sh` | Display name shown in-game |
| `SE_ADMIN_PASSWORD` | `02_new_game.sh` | Admin login password |
| `PORT` | `docker-compose.yml` | Host port (default: 8080) |
| `SERVER_NAME` | `config.inc.php` | Server display name |
| `SEND_MAIL` | `config.inc.php` | Set to 1 to enable registration emails |

---

## Database Architecture

The game supports multiple concurrent games in one database. Each game has its own
set of tables prefixed by `db_name` (e.g. `game1_users`, `game1_ships`, etc.).

**Server-level tables** (shared, from `new_server.sql`):
- `se_games` — one row per game, holds config and admin credentials
- `user_accounts` — global user accounts (login spans all games)
- `user_history` — audit log
- `daily_tips`, `option_list`, `se_svr_star_names`, `se_central_forum`

**Per-game tables** (prefixed, from `new_game.sql`):
- `{game}_users`, `{game}_ships`, `{game}_stars`, `{game}_planets`
- `{game}_clans`, `{game}_messages`, `{game}_news`, `{game}_diary`
- `{game}_bilkos`, `{game}_ports`, `{game}_ship_types`, `{game}_db_vars`
- `{game}_bmrkt`, `{game}_user_options`, `{game}_politics`

`{game}_db_vars` holds all game-tunable settings (turn rates, costs, flags, etc.)
and is the source of truth for runtime game config.

---

## DB Wrapper Functions (inc/common.inc.php)

Never use raw `mysqli_*` calls in game PHP files. Always use these wrappers:

| Function | Purpose |
|----------|---------|
| `db_connect()` | Open connection (called at request start) |
| `db($sql)` | Execute query, store result in `$db_func_query` |
| `dbr($type)` | Fetch row from `db()` result. `0` = array, `1` = assoc |
| `db2($sql)` | Second query slot (for nested queries) |
| `dbr2($type)` | Fetch row from `db2()` result |
| `dbn($sql)` | Fire-and-forget query (no result needed) |
| `db_escape($str)` | Escape a string for use in a query |
| `db_insert_id()` | Get last insert ID |
| `dbDie()` | Print error + backtrace and exit |

**Maintenance scripts** (`run_hourly.php`, `run_daily.php`) use `$maint_link` directly
via `inc/maint.inc.php` — they are CLI-only and bypass the web wrappers.

---

## Known Issues / In Progress

- [x] All `mysql_*` → `mysqli_*` migration complete
- [x] `config.inc.php` reads from env vars
- [x] `${var}` string interpolation → `{$var}` (PHP 8.2 deprecation)
- [x] `eregi()` / `ereg()` → `preg_match()` / `str_contains()`
- [x] `mt_srand()` float precision fix
- [x] `URL_SHORT` double-slash bug fixed (`rtrim(..., '/')`)
- [x] Login form `$login_name` warning leaking into field fixed
- [x] All game PHP files scanned and fixed for `mysql_*`, `ereg*`, `each()`, `${var}`, `$HTTP_*_VARS`
- [x] `maint.inc.php` `mt_srand()` fix applied
- [ ] CSS: verify all style sheets load correctly after URL_SHORT fix
- [ ] Test full login → game flow end to end
- [ ] Test universe generation (admin panel → Generate Universe)
- [ ] Test signup flow
- [ ] Cron jobs: verify hourly/daily maintenance runs inside container

---

## Dev Workflow

### Run locally
```bash
cd C:\Projects\SolarEmpireV1
docker compose up -d --build
# Game at http://localhost:8080
```

### Rebuild after code changes
```bash
docker compose up -d --build
```

### Wipe DB and start fresh (re-runs init SQL)
```bash
docker compose down -v   # -v removes volumes including db_data
docker compose up -d --build
```

### Push update + rebuild
```bash
git add .
git commit -m "your message"
git push
docker compose up -d --build
```

### View logs
```bash
docker logs se_app
docker logs se_db
```

### Shell into app container
```bash
docker exec -it se_app bash
```

### Run maintenance scripts manually
```bash
docker exec -it se_app php /var/www/html/run_hourly.php
docker exec -it se_app php /var/www/html/run_daily.php
```

---

## What NOT to Do

- Never hardcode credentials in any PHP file — use env vars via `config.inc.php`
- Never commit `.env`
- Never use raw `mysql_*` functions — they don't exist in PHP 8
- Never use `${var}` string interpolation — deprecated in PHP 8.2, use `{$var}`
- Never use `ereg()`, `eregi()`, `split()` — removed in PHP 7
- Never use `mysql_insert_id()` — use `db_insert_id()`
- Never use `mysql_escape_string()` — use `db_escape()`
- Do not run `docker compose -f docker-compose.deploy.yml` locally
