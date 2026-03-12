#!/bin/bash
# Run this once to set up a Solar Empire Reborn deploy directory.
# Usage: bash setup.sh

REPO=https://raw.githubusercontent.com/StevenG916/SolarEmpireReborn/main

echo "Fetching deploy files..."
curl -fsSL -O $REPO/docker-compose.yml
curl -fsSL -O $REPO/.env.example

echo "Fetching database init files..."
mkdir -p init
curl -fsSL -o init/01_new_server.sql $REPO/docs/sql/new_server.sql
curl -fsSL -o init/new_game.sql      $REPO/docs/sql/new_game.sql
curl -fsSL -o init/02_new_game.sh    $REPO/docker/mysql/init/02_new_game.sh
chmod +x init/02_new_game.sh

if [ ! -f .env ]; then
    cp .env.example .env
    echo ""
    echo "Edit .env with your passwords and settings, then run: docker compose up -d --build"
else
    echo ".env already exists, skipping."
    echo "Run: docker compose up -d --build"
fi
