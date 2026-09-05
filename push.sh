#!/bin/bash

#########################################
# Auto GitHub Push Script
#########################################

# ===== CONFIG =====
REPO_URL="https://github.com/basaihinn-prog/onexbet.git"
BRANCH="main"
COMMIT_MESSAGE="${1:-Auto update $(date '+%Y-%m-%d %H:%M:%S')}"
# ==================

set -e

echo "======================================"
echo " GitHub Auto Push"
echo "======================================"

# Check Git
if ! command -v git >/dev/null 2>&1; then
    echo "Error: Git is not installed."
    exit 1
fi

# Initialize repository if needed
if [ ! -d ".git" ]; then
    echo "[*] Initializing Git repository..."
    git init
fi

# Configure remote
if git remote get-url origin >/dev/null 2>&1; then
    git remote set-url origin "$REPO_URL"
else
    git remote add origin "$REPO_URL"
fi

# Create/switch to branch
git checkout -B "$BRANCH"

echo "[*] Adding all files..."
git add -A

# Commit only if there are changes
if git diff --cached --quiet; then
    echo "[✓] No changes to commit."
    exit 0
fi

echo "[*] Committing..."
git commit -m "$COMMIT_MESSAGE"

echo "[*] Pushing to GitHub..."
git push -u origin "$BRANCH"

echo
echo "======================================"
echo " Push Complete!"
echo " Repository: $REPO_URL"
echo " Branch: $BRANCH"
echo "======================================"
