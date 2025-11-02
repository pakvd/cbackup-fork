#!/bin/bash
# Wrapper script for docker compose up that sets file permissions before starting

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

echo "=== cBackup Docker Compose Startup ==="
echo ""

# Set file permissions before starting containers
if [ -f "./set-permissions.sh" ]; then
    echo "Setting file permissions..."
    ./set-permissions.sh
    echo ""
fi

# Run docker compose with all passed arguments
echo "Starting containers..."
docker compose "$@"

