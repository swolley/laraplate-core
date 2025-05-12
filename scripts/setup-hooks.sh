#!/bin/bash

# Directory of the script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
MODULE_DIR="$(dirname "$SCRIPT_DIR")"
GIT_FILE="$MODULE_DIR/.git"
ICON_CHECKMARK="\033[32m✓\033[0m"
ICON_CROSS="\033[31m✗\033[0m"

# Function to convert relative path to absolute path
# Arguments:
#   $1: Relative path to convert
# Returns:
#   Absolute path
get_absolute_path() {
    local relative_path="$1"
    if command -v realpath >/dev/null 2>&1; then
        realpath "$relative_path"
    else
        # Fallback for systems without realpath
        local absolute_path
        absolute_path="$(cd "$(dirname "$relative_path")" && pwd)/$(basename "$relative_path")"
        echo "$absolute_path"
    fi
}

# Check if this is a git submodule
if [ -f "$GIT_FILE" ]; then
    echo "Found submodule in $MODULE_DIR"
    # Read the gitdir from the .git file
    GIT_DIR=$(cat "$GIT_FILE" | cut -d' ' -f2)
    # Convert relative path to absolute if needed
    if [[ "$GIT_DIR" == gitdir:* ]]; then
        GIT_DIR="${GIT_DIR#gitdir:}"
    fi
    if [[ "$GIT_DIR" != /* ]]; then
        GIT_DIR="$MODULE_DIR/$GIT_DIR"
    fi
else
    echo "Installing git hooks in $MODULE_DIR"
    # Regular git repository
    GIT_DIR="$MODULE_DIR/.git"
fi

HOOKS_DIR=$(get_absolute_path "$GIT_DIR/hooks")

# Make all hook scripts executable
chmod +x "$SCRIPT_DIR"/*.sh

# Check if hooks directory exists and contains files
if [ ! -d "$SCRIPT_DIR/hooks" ] || [ -z "$(ls -A "$SCRIPT_DIR/hooks")" ]; then
    echo "    No hooks found"
else
    # Create symlinks for each hook
    for hook in "$SCRIPT_DIR"/hooks/*; do
        hook_name=$(basename "$hook" .sh)
        if [ "$hook_name" != "install" ]; then
            if [ -L "$HOOKS_DIR/$hook_name" ] && [ "$(readlink "$HOOKS_DIR/$hook_name")" = "$hook" ]; then
                echo -e "    $ICON_CHECKMARK Hook already set: $hook_name"
            else
                ln -sf "$(get_absolute_path "$hook")" "$HOOKS_DIR/$hook_name" && \
                echo -e "    $ICON_CHECKMARK Installed $hook_name hook" || \
                echo -e "    $ICON_CROSS Failed to install $hook_name hook"
            fi
        fi
    done
fi
