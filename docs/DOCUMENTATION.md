# Solar Empire Reborn — Documentation

> Modernized fork of Solar Empire v1.3 (2004, Moriarty / SourceForge)  
> PHP 8.3 · MySQL 8.4 · Docker · Apache

---

## Table of Contents

1. [Self-Hosting (Quick Start)](#self-hosting-quick-start)
2. [Configuration Reference](#configuration-reference)
3. [First Run Checklist](#first-run-checklist)
4. [Admin Panel Guide](#admin-panel-guide)
5. [Game Concepts](#game-concepts)
6. [Maintenance Scripts](#maintenance-scripts)
7. [Updating](#updating)
8. [Troubleshooting](#troubleshooting)
9. [Development Setup](#development-setup)

---

## Self-Hosting (Quick Start)

### Prerequisites
- A Linux machine (or VM) with Docker and Docker Compose installed
- A domain or subdomain pointed at your server via Cloudflare Tunnel, or just LAN access

### Deploy from scratch

```bash
# 1. Create a directory
mkdir solar-empire && cd solar-empire

# 2. Fetch the two files you need
curl -fsSL -O https://raw.githubusercontent.com/StevenG916/SolarEmpireReborn/main/docker-compose.deploy.yml
curl -fsSL -O https://raw.githubusercontent.com/StevenG916/SolarEmpireReborn/main/.env.example

# 3. Fetch the database init files
mkdir -p init
curl -fsSL -o init/01_new_server.sql https://raw.githubusercontent.com/StevenG916/SolarEmpireReborn/main/docs/sql/new_server.sql
curl -fsSL -o init/new_game.sql      https://raw.githubusercontent.com/StevenG916/SolarEmpireReborn/main/docs/sql/new_game.sql
curl -fsSL -o init/02_new_game.sh    https://raw.githubusercontent.com/StevenG916/SolarEmpireReborn/main/docker/mysql/init/02_new_game.sh
chmod +x init/02_new_game.sh

# 4. Configure
cp .env.example .env
nano .env   # Set your passwords, game name, and port

# 5. Start
docker compose -f docker-compose.deploy.yml up -d --build
```

Or use the setup script (does steps 2–4 automatically):
```bash
curl -fsSL https://raw.githubusercontent.com/StevenG916/SolarEmpireReborn/main/setup.sh | bash
nano .env
docker compose up -d --build
```

The game will be available at `http://your-server:8080` (or whatever port you set).

---

## Configuration Reference

Edit `.env` before starting. All values have safe defaults but **passwords must be changed**.

```env
# Repository (deploy mode only — which branch to build from)
REPO_URL=https://github.com/StevenG916/SolarEmpireReborn.git
REPO_BRANCH=main

# Database passwords — CHANGE THESE
MYSQL_ROOT_PASSWORD=changeme_root
DB_NAME=solarempire
DB_USER=solarempire
DB_PASSWORD=changeme_db

# Game setup — applied on FIRST RUN ONLY
SE_GAME_NAME=game1           # Table prefix, no spaces (e.g. game1 → game1_users)
SE_GAME_DISPLAY_NAME=Solar Empire
SE_ADMIN_PASSWORD=changeme_admin   # Password for the admin account

# App
PORT=8080                    # Host port to expose the game on
SERVER_NAME=My Solar Empire Server
SEND_MAIL=0                  # Set to 1 if you have SMTP configured
```

> **Note:** `SE_GAME_NAME`, `SE_GAME_DISPLAY_NAME`, and `SE_ADMIN_PASSWORD` are only
> used when the database is first created. Changing them later has no effect unless
> you wipe the database volume (`docker compose down -v`).

---

## First Run Checklist

After `docker compose up -d --build` completes:

1. **Open the game** at `http://localhost:8080` (or your configured port)
2. **Log in as admin** — username `admin`, password = whatever you set `SE_ADMIN_PASSWORD` to
3. **Go to Admin Panel** → find the **Game Variables** section
4. **Generate the Universe** — without this no stars exist and players can't play
5. **Set the game to active** if it isn't already
6. **Sign up as a test player** to verify the signup flow works
7. **Check cron** is running maintenance scripts (see [Maintenance Scripts](#maintenance-scripts))

---

## Admin Panel Guide

Access the admin panel by logging in with username `admin`.

### Key Settings

**Universe Generation**
- Set `uv_num_stars` (default: 150) — number of star systems
- Set `uv_map_layout` — 0=random, 1=grid, 2=galactic core, 3=clusters, 4=circle
- Set `uv_needs_gen` to `1` then wait for daily maintenance, OR trigger manually
- **Run Build Universe** from the admin panel to generate immediately

**Game Flags**
- `new_logins` — set to `0` to close signups
- `flag_space_attack` — set to `0` to disable ship combat
- `flag_planet_attack` — set to `0` to disable planet combat
- `sudden_death` — set to `1` to prevent respawning (end-game mode)

**Economy**
- `hourly_turns` — turns granted per hour (default: 10)
- `max_turns` — turn cap (default: 250)
- `start_cash` — starting credits (default: 5000)
- `start_ship` — starting ship type: 3=Scout, 4=Freighter, 5=Stealth Trader, 6=Harvester

**Scoring**
- `score_method` — 0=off, 1=kills-based, 2=points-based, 3=fiscal, 4=comprehensive

### Admin Password
Change the admin password from the admin panel under account settings.
The default is whatever you set in `SE_ADMIN_PASSWORD` in `.env`.

---

## Game Concepts

### Ships
Players start with one ship and can own up to `max_ships` (default: 100).
Ship classes range from tiny Scout Ships to the massive Brobdingnagian flagship.

Key ship stats:
- **Fighters** — offensive/defensive firepower
- **Shields** — absorb damage, regenerate hourly
- **Cargo Bays** — carry metal, fuel, electronics, organics, colonists
- **Mine Rate** — how fast the ship extracts resources per hour

### Resources
- **Metal** — mined from star systems, used to build fighters and electronics
- **Fuel** — mined from star systems, used for planet production
- **Electronics** — produced on planets, used in upgrades
- **Organics** — produced on planets, used for colonist growth
- **Colonists** — transported to planets to grow population and production

### Planets
Planets are captured from NPCs or other players. Colonists are assigned to:
- **Fighter production** (uses metal + fuel + electronics)
- **Electronics production** (uses metal + fuel)
- **Organics production** (grows passively)
- **Tax collection** (unassigned colonists pay tax)

### Clans
Players can form or join clans (guilds). Clans share a symbol and color,
and clan members can see each other's ships. Clan leaders can coordinate attacks.

### Bilkos Auction House
Located at Sol (system #1). Players can bid on rare items including:
- Ship upgrades (shield generators, fighter bays, etc.)
- Turns
- Planet equipment (missile launch pads, shield generators)

### Wormholes
Random warp shortcuts between distant systems. Can be shown or hidden on the map
via the `wormholes` game variable.

---

## Maintenance Scripts

Two scripts handle time-based game progression. They run automatically via cron
inside the Docker container, but can also be triggered manually.

### run_hourly.php
Runs every hour. Handles:
- Shield regeneration for all ships
- Mining (ships in mine mode collect resources)
- Turn distribution to all players
- Bilkos auction processing
- Sol scatter warning/enforcement (keeps Sol system clear)
- Empty clan cleanup

### run_daily.php
Runs at midnight. Handles:
- Daily tip rotation
- Inactive player retirement (default: 6 days inactive)
- Bounty interest (+4% per day)
- Planet production (fighters, electronics, organics)
- Planet tax collection and population growth
- Resource regeneration in star systems
- Days-left countdown
- Table optimization

### Manual execution
```bash
docker exec -it se_app php /var/www/html/run_hourly.php
docker exec -it se_app php /var/www/html/run_daily.php
```

### Check cron is running
```bash
docker exec -it se_app service cron status
docker exec -it se_app cat /var/log/se_hourly.log
docker exec -it se_app cat /var/log/se_daily.log
```

---

## Updating

### Update the game (pull latest code)
```bash
docker compose down
docker compose up -d --build
```

The database volume (`db_data`) is preserved. Only the app container is rebuilt
from the latest GitHub commit.

### Wipe everything and start fresh
```bash
docker compose down -v    # WARNING: destroys all game data
docker compose up -d --build
```

---

## Troubleshooting

### Game shows PHP warnings at the top of the page
PHP deprecation notices are suppressed in `config.inc.php` via:
```php
error_reporting(E_ALL & ~(E_NOTICE | E_STRICT | E_DEPRECATED));
```
If warnings still appear, they are actual errors — check `docker logs se_app`.

### CSS not loading / unstyled page
The CSS path is generated from `URL_SHORT` in `config.inc.php`. If the game is
served from the web root, `URL_SHORT` will be an empty string and CSS will load
from `/css/style1.css` correctly. If served from a subdirectory, check that
`URL_SHORT` resolves correctly.

### Can't connect to database
- Check `docker logs se_db` for MySQL errors
- Verify the `db` container is healthy: `docker compose ps`
- Make sure `.env` passwords match between `DB_PASSWORD` and `MYSQL_PASSWORD`

### Database already exists / init scripts didn't run
MySQL init scripts only run when the data directory is empty (first start).
If you've already started once, they won't run again. To re-run them:
```bash
docker compose down -v
docker compose up -d --build
```

### Login name field shows HTML/warning text
This was a bug where PHP warnings were being captured into `$login_name` via
`extract()`. Fixed in current version — `login_form.php` now reads directly from
`$_POST['l_name'] ?? ''`.

### Ships not regenerating shields / turns not increasing
Cron is not running. Check:
```bash
docker exec -it se_app service cron status
# If not running:
docker exec -it se_app service cron start
```

---

## Development Setup

### Requirements
- Windows, Mac, or Linux with Docker Desktop (or Docker Engine on Linux)
- Git

### Clone and run
```bash
git clone https://github.com/StevenG916/SolarEmpireReborn.git
cd SolarEmpireReborn
cp .env.example .env
# Edit .env if needed — defaults work fine for local dev
docker compose up -d --build
```

Game runs at http://localhost:8080.

### Making changes
Edit files locally in `C:\Projects\SolarEmpireV1` (or wherever you cloned),
then rebuild:
```bash
docker compose up -d --build
```

Docker will pick up your local file changes since `docker-compose.yml` uses
`context: .` (local build context).

### Committing
```bash
git add .
git commit -m "describe your change"
git push
```

Never commit `.env`. It is in `.gitignore`.

### File to edit for common tasks

| Task | File |
|------|------|
| DB connection / config | `inc/config.inc.php` |
| DB query wrappers | `inc/common.inc.php` |
| Login / session logic | `inc/session_funcs.inc.php` |
| Hourly game tick | `run_hourly.php` |
| Daily game tick | `run_daily.php` |
| Universe generation | `inc/generator.funcs.php`, `build_universe.php` |
| Ship purchase | `ship_build.php` |
| Combat | `attack.php` |
| Planet management | `planet.php`, `planet_build.php` |
| Admin panel | `admin.php`, `inc/admin.inc.php` |
| Signup | `signup.php`, `signup_form.php` |
| Login | `login_form.php`, `game_listing.php` |
