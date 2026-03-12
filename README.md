# Solar Empire Reborn

A modernized Docker-deployable version of **Solar Empire v1.3**, a browser-based space strategy game originally released on SourceForge in 2004 by Moriarty.

Original source: https://sourceforge.net/projects/solarempire/  
Original author: Moriarty (2000–2004)

This fork updates the codebase to run on PHP 8.3 and MySQL 8.4 in a Docker environment. The original game is redistributed here in the spirit of its original open-source release. All modifications are open source under the same terms.

---

## Deploy (self-host)

You only need two files on your host machine:

```bash
mkdir solar-empire && cd solar-empire
curl -O https://raw.githubusercontent.com/StevenG916/SolarEmpireReborn/main/docker-compose.yml
curl -O https://raw.githubusercontent.com/StevenG916/SolarEmpireReborn/main/.env.example
cp .env.example .env
nano .env          # set your passwords and game name
docker compose up -d
```

That's it. Docker pulls the image, builds from source, seeds the database, and starts the game.

Point a Cloudflare tunnel (or reverse proxy) at `http://localhost:8080`.

---

## Update

```bash
docker compose down
docker compose up -d --build
```

This re-clones the repo and rebuilds the image with the latest code. The database volume is preserved.

---

## Configuration (`.env`)

| Variable | Default | Description |
|---|---|---|
| `MYSQL_ROOT_PASSWORD` | `changeme_root` | MySQL root password |
| `DB_NAME` | `solarempire` | Database name |
| `DB_USER` | `solarempire` | Database user |
| `DB_PASSWORD` | `changeme_db` | Database password |
| `SE_GAME_NAME` | `game1` | Table prefix for the game (no spaces) |
| `SE_GAME_DISPLAY_NAME` | `Solar Empire` | Display name shown in-game |
| `SE_ADMIN_PASSWORD` | `changeme_admin` | Admin login password |
| `PORT` | `8080` | Host port to expose the game on |
| `SERVER_NAME` | `My Solar Empire Server` | Server display name |
| `SEND_MAIL` | `0` | Set to `1` to enable registration emails |

---

## First run

After `docker compose up -d`, log in at `http://localhost:8080` with username `admin` and the password you set in `SE_ADMIN_PASSWORD`.

From the admin panel, run **Generate Universe** before any players sign up.

---

## Changes from v1.3

- All `mysql_*` functions replaced with `mysqli_*` (PHP 8 compatibility)
- Config values moved to environment variables (no hardcoded credentials)
- `${var}` string interpolation updated to `{$var}` (PHP 8.2+)
- `eregi()` / `ereg()` replaced with `preg_match()` / `str_contains()`
- `mt_srand()` float precision fix
- Dockerfile + docker-compose for one-command self-hosting
- Cron-based maintenance scripts (hourly/daily) run inside the container
