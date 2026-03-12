#!/bin/bash
# Runs inside the MySQL container on first startup.
# Substitutes the "gamename_" placeholder in new_game.sql with the actual
# game prefix, inserts the se_games row, and loads the result.

set -e

GAME_NAME="${SE_GAME_NAME:-game1}"
GAME_DISPLAY="${SE_GAME_DISPLAY_NAME:-Solar Empire}"
ADMIN_PW="${SE_ADMIN_PASSWORD:-passwd}"

echo "Setting up game schema for prefix: ${GAME_NAME}_"

# Substitute placeholder and pipe into the already-selected database
sed "s/gamename_/${GAME_NAME}_/g; s/'gamename'/'${GAME_NAME}'/g" \
    /docker-entrypoint-initdb.d/new_game.sql \
    | mysql -u root -p"${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE}"

# Insert the se_games row (the placeholder SQL inserts a "gamename" row —
# we already replaced it above, but let's do a clean explicit insert instead)
mysql -u root -p"${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE}" <<SQL
DELETE FROM \`se_games\` WHERE \`db_name\` = '${GAME_NAME}';
INSERT INTO \`se_games\`
  (\`name\`, \`db_name\`, \`admin_name\`, \`admin_pw\`, \`status\`, \`paused\`,
   \`description\`, \`intro_message\`, \`num_stars\`)
VALUES
  ('${GAME_DISPLAY}', '${GAME_NAME}', 'Admin', MD5('${ADMIN_PW}'), 1, 1,
   'Welcome to Solar Empire!', 'Welcome! Sign up and join the game.', 150);
SQL

echo "Game '${GAME_NAME}' ready. Admin password set to '${ADMIN_PW}' — change this!"
