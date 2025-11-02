#!/bin/bash
# Script to set correct file permissions on the host for Docker volume mounts
# This should be run on the host system before or after starting containers
# This script is automatically called by `make up` or `docker-compose-up.sh`

# Don't fail if some files don't exist
set +e

echo "=== Setting file permissions for cBackup ==="

# Get the script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
CORE_DIR="$SCRIPT_DIR/core"

if [ ! -d "$CORE_DIR" ]; then
    echo "⚠️  WARNING: core directory not found at $CORE_DIR"
    echo "   Skipping permissions setup (may be normal if core/ is not mounted)"
    exit 0
fi

echo "Setting permissions in $CORE_DIR..."

# Set permissions for yii files
# yii.bat should be non-writable, non-executable (444 = read-only)
if [ -f "$CORE_DIR/yii.bat" ]; then
    chmod 444 "$CORE_DIR/yii.bat"
    chmod -x "$CORE_DIR/yii.bat"
    echo "✓ Set permissions for yii.bat (444, non-executable)"
else
    echo "⚠ yii.bat not found"
fi

# yii should be non-executable but readable (644)
if [ -f "$CORE_DIR/yii" ]; then
    chmod 644 "$CORE_DIR/yii"
    chmod -x "$CORE_DIR/yii"
    echo "✓ Set permissions for yii (644, non-executable)"
else
    echo "⚠ yii not found"
fi

# Set permissions for bin files (if they exist on host)
# These files may be owned by root (from worker container), so we try to change ownership first
if [ -f "$CORE_DIR/bin/cbackup.jar" ]; then
    # Try to change ownership to current user if file is owned by root
    CURRENT_USER=$(whoami)
    FILE_OWNER=$(stat -c "%U" "$CORE_DIR/bin/cbackup.jar" 2>/dev/null || stat -f "%Su" "$CORE_DIR/bin/cbackup.jar" 2>/dev/null || echo "unknown")
    if [ "$FILE_OWNER" = "root" ] || [ "$FILE_OWNER" != "$CURRENT_USER" ]; then
        # Try to change ownership (may require sudo)
        sudo chown "$CURRENT_USER:$CURRENT_USER" "$CORE_DIR/bin/cbackup.jar" 2>/dev/null || true
    fi
    # Try to set permissions (suppress stderr - permission errors are expected if file is owned by root)
    if chmod 555 "$CORE_DIR/bin/cbackup.jar" 2>/dev/null; then
        echo "✓ Set permissions for cbackup.jar (555)"
    else
        # File may be owned by root from container - this is OK, entrypoint will fix it
        echo "⚠ Skipped cbackup.jar (permission denied - will be set by entrypoint script in container)"
    fi
fi

if [ -f "$CORE_DIR/bin/application.properties" ]; then
    # Try to change ownership to current user if file is owned by root
    CURRENT_USER=$(whoami)
    FILE_OWNER=$(stat -c "%U" "$CORE_DIR/bin/application.properties" 2>/dev/null || stat -f "%Su" "$CORE_DIR/bin/application.properties" 2>/dev/null || echo "unknown")
    if [ "$FILE_OWNER" = "root" ] || [ "$FILE_OWNER" != "$CURRENT_USER" ]; then
        # Try to change ownership (may require sudo)
        sudo chown "$CURRENT_USER:$CURRENT_USER" "$CORE_DIR/bin/application.properties" 2>/dev/null || true
    fi
    # Try to set permissions (suppress stderr - permission errors are expected if file is owned by root)
    if chmod 664 "$CORE_DIR/bin/application.properties" 2>/dev/null && chmod -x "$CORE_DIR/bin/application.properties" 2>/dev/null; then
        echo "✓ Set permissions for application.properties (664, non-executable)"
    else
        # File may be owned by root from container - this is OK, entrypoint will fix it
        echo "⚠ Skipped application.properties (permission denied - will be set by entrypoint script in container)"
    fi
fi

echo "=== Permissions set successfully ==="

# Check if files still have wrong permissions (may happen with volume mounts)
if [ -f "$CORE_DIR/yii.bat" ]; then
    PERMS=$(stat -c "%a" "$CORE_DIR/yii.bat" 2>/dev/null || stat -f "%OLp" "$CORE_DIR/yii.bat" 2>/dev/null || echo "unknown")
    if [ "$PERMS" != "444" ] && [ "$PERMS" != "unknown" ]; then
        echo "⚠️  WARNING: yii.bat permissions are $PERMS (expected 444)"
        echo "   This may be normal with volume mounts if files are owned by different user"
        echo "   Entrypoint script will attempt to fix this inside the container"
    fi
fi

