# Solar Empire Reborn

A modernized fork of [Solar Empire v1.3](http://sourceforge.net/projects/solar-empire/), a browser-based space strategy game originally released on SourceForge in 2004 by Moriarty.

Solar Empire Reborn updates the codebase to run on **PHP 8.3**, **MySQL 8.4**, and **Docker**, making it easy to self-host for a group of friends.

---

## Quick Start

You need [Docker](https://docs.docker.com/get-docker/) and [Docker Compose](https://docs.docker.com/compose/install/) installed.

```bash
# Clone the repo
git clone https://github.com/StevenG916/SolarEmpireReborn.git
cd SolarEmpireReborn

# Configure
cp .env.example .env
nano .env   # Set your passwords and game name

# Start
docker compose up -d --build
```

The game will be available at **http://localhost:8080** (or whatever port you set in `.env`).

---

## Remote Deploy (No Git Required)

If you just want to run the game on a server without cloning the repo:

```bash
mkdir solar-empire && cd solar-empire

# Grab the deploy compose file and example config
curl -fsSL -O https://raw.githubusercontent.com/StevenG916/SolarEmpireReborn/main/docker-compose.deploy.yml
curl -fsSL -O https://raw.githubusercontent.com/StevenG916/SolarEmpireReborn/main/.env.example

# Grab the database init files
mkdir -p init
curl -fsSL -o init/01_new_server.sql https://raw.githubusercontent.com/StevenG916/SolarEmpireReborn/main/docs/sql/new_server.sql
curl -fsSL -o init/new_game.sql      https://raw.githubusercontent.com/StevenG916/SolarEmpireReborn/main/docs/sql/new_game.sql
curl -fsSL -o init/02_new_game.sh    https://raw.githubusercontent.com/StevenG916/SolarEmpireReborn/main/docker/mysql/init/02_new_game.sh
chmod +x init/02_new_game.sh

# Configure and start
cp .env.example .env
nano .env   # CHANGE THE PASSWORDS
docker compose -f docker-compose.deploy.yml up -d --build
```

---

## First Run

1. Open the game at `http://localhost:8080`
2. Log in as **admin** (password = whatever you set `SE_ADMIN_PASSWORD` to in `.env`)
3. Go to the **Admin Panel** and **Generate the Universe** &mdash; without this there are no star systems and players can't play
4. Set the game to **active** if it isn't already
5. Sign up as a test player to verify everything works
6. Point a Cloudflare tunnel or reverse proxy at the port if you want external access

---

## Configuration

Edit `.env` before starting. All values have defaults, but **passwords must be changed**.

| Variable | Purpose | Default |
|----------|---------|---------|
| `MYSQL_ROOT_PASSWORD` | MySQL root password | `changeme_root` |
| `DB_PASSWORD` | App database password | `changeme_db` |
| `SE_GAME_NAME` | Table prefix (e.g. `game1` &rarr; `game1_users`) | `game1` |
| `SE_GAME_DISPLAY_NAME` | Name shown in-game | `Solar Empire` |
| `SE_ADMIN_PASSWORD` | Admin login password | `changeme_admin` |
| `PORT` | Host port | `8080` |
| `SERVER_NAME` | Server display name | `My Solar Empire Server` |
| `SEND_MAIL` | Enable registration emails | `0` |

Game setup variables (`SE_GAME_NAME`, `SE_GAME_DISPLAY_NAME`, `SE_ADMIN_PASSWORD`) are only used on first run when the database is created. To re-apply, wipe the volume:

```bash
docker compose down -v
docker compose up -d --build
```

---

## Common Operations

```bash
# Rebuild after code changes
docker compose up -d --build

# View logs
docker logs se_app
docker logs se_db

# Shell into the app container
docker exec -it se_app bash

# Run maintenance scripts manually
docker exec -it se_app php /var/www/html/run_hourly.php
docker exec -it se_app php /var/www/html/run_daily.php

# Wipe everything and start fresh
docker compose down -v
docker compose up -d --build
```

---

## About the Game

Solar Empire is a multiplayer space strategy game played in a web browser. Players compete to build fleets, colonize planets, trade resources, and dominate the galaxy.

- **Ships** &mdash; Buy, name, and command a fleet from tiny Scouts to massive Brobdingnagian flagships
- **Combat** &mdash; Attack other players' ships and planets with fighters, bombs, and super weapons
- **Planets** &mdash; Colonize worlds, assign workers to production, and build planetary defenses
- **Economy** &mdash; Mine resources from star systems, trade at ports, manage your credits
- **Clans** &mdash; Form alliances, share intel, and coordinate attacks with other players
- **Auctions** &mdash; Bid on rare ships and equipment at Bilko's Auction House

---

## What Changed from the Original

Solar Empire v1.3 was written for PHP 4/5 and MySQL 4/5. This fork modernizes it for current infrastructure:

- **PHP 8.3 compatible** &mdash; All `mysql_*` calls replaced with `mysqli` wrappers, `ereg` functions replaced with `str_contains`/`preg_match`, `each()` replaced with `foreach`, deprecated `${var}` string interpolation fixed, `$PHP_SELF` and `$HTTP_POST_VARS` globals replaced
- **MySQL 8.4 compatible** &mdash; Fixed `ONLY_FULL_GROUP_BY` violations, guarded division-by-zero errors
- **Dockerized** &mdash; One-command setup with Docker Compose, no manual Apache/PHP/MySQL configuration
- **Environment-based config** &mdash; All credentials via `.env`, nothing hardcoded
- **Cron built in** &mdash; Hourly and daily maintenance scripts run automatically inside the container

---

## Stack

| Layer | Technology |
|-------|------------|
| Language | PHP 8.3 |
| Web Server | Apache (via `php:8.3-apache`) |
| Database | MySQL 8.4 |
| Container | Docker + Docker Compose |

---

## Credits

Solar Empire was designed by **Bryan Livingston**. Additional programming by Rob Hardy and Randee Shirts. Open source contributions by Moriarty, KilerCris, TheRumour, Semicolon, and DJCapelis. Ship images rendered by Admiral V'Pier.

Modernization for PHP 8.3 / MySQL 8.4 / Docker by [StevenG916](https://github.com/StevenG916).

Original source: https://sourceforge.net/projects/solar-empire/

---

## License

Solar Empire was released as open source on SourceForge. This fork preserves that status. All modifications are open source under the same terms.
