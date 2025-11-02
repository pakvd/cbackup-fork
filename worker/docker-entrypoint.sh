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

# Find Java executable (from root context)
# In eclipse-temurin images, Java is typically in /opt/java/openjdk
JAVA_CMD=$(which java 2>/dev/null)
if [ -z "$JAVA_CMD" ] || [ ! -x "$JAVA_CMD" ]; then
    # Try common Java locations for eclipse-temurin images
    if [ -x "/opt/java/openjdk/bin/java" ]; then
        JAVA_CMD="/opt/java/openjdk/bin/java"
    elif [ -x "/usr/local/openjdk-21/bin/java" ]; then
        JAVA_CMD="/usr/local/openjdk-21/bin/java"
    elif [ -x "/usr/lib/jvm/java-21-openjdk/bin/java" ]; then
        JAVA_CMD="/usr/lib/jvm/java-21-openjdk/bin/java"
    else
        echo "Searching for Java..."
        JAVA_CMD=$(find /usr -name java -type f -executable 2>/dev/null | grep -E 'bin/java' | head -1)
        if [ -z "$JAVA_CMD" ]; then
            echo "✗ ERROR: Java not found!"
            exit 1
        fi
    fi
fi

echo "Using Java: $JAVA_CMD"
echo "Java version: $($JAVA_CMD -version 2>&1 | head -1)"

# Get JAVA_HOME if available
JAVA_HOME_DIR=$(dirname $(dirname $JAVA_CMD))
if [ -d "$JAVA_HOME_DIR" ]; then
    export JAVA_HOME="$JAVA_HOME_DIR"
    echo "JAVA_HOME: $JAVA_HOME"
fi

cd /app
# Use su without - to preserve environment, or pass JAVA_HOME explicitly
# su without - preserves current environment including PATH
exec su appuser -c "export JAVA_HOME=${JAVA_HOME:-$JAVA_HOME_DIR} && export PATH=\$PATH:$(dirname $JAVA_CMD) && cd /app && $JAVA_CMD -jar app.jar"

