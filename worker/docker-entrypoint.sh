#!/bin/bash
# Worker container entrypoint script
# Copies cbackup.jar to shared volume, then starts Java application

set -e

echo "=== Worker container entrypoint starting ==="
echo "Current user: $(whoami)"
echo "Working directory: $(pwd)"

# Check if /app/app.jar exists
if [ ! -f /app/app.jar ]; then
    echo "✗ ERROR: /app/app.jar not found!"
    ls -la /app/
    exit 1
fi

echo "✓ Found /app/app.jar:"
ls -lh /app/app.jar

# Create shared bin directory
echo "Creating /shared/bin directory..."
mkdir -p /shared/bin
echo "✓ Directory created"

# Check permissions on /shared/bin
echo "Checking /shared/bin permissions:"
ls -ld /shared/bin

# Copy JAR file to shared location
echo "Copying JAR file to /shared/bin/cbackup.jar..."
cp -v /app/app.jar /shared/bin/cbackup.jar

# Verify copy was successful
FILE_SIZE=$(stat -c%s /shared/bin/cbackup.jar 2>/dev/null || echo "0")
if [ "$FILE_SIZE" -lt 1000 ]; then
    echo "✗ ERROR: Copied file is too small ($FILE_SIZE bytes)"
    exit 1
fi

echo "✓ Successfully copied cbackup.jar (size: $FILE_SIZE bytes)"
ls -lh /shared/bin/cbackup.jar

# Set correct permissions
chmod 555 /shared/bin/cbackup.jar
echo "✓ Set permissions to 555"

# Try to change ownership (may fail with volume mounts)
chown appuser:appuser /shared/bin/cbackup.jar 2>&1 || echo "Note: chown failed (expected with volume mounts)"

# Switch to appuser and start Java application
echo "Switching to appuser and starting Java application..."
cd /app
exec su appuser -c "java -jar app.jar"

