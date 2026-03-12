#!/bin/bash
set -e

# Start cron daemon
service cron start

# Start Apache in the foreground
exec apache2-foreground
