#!/bin/bash

# Copy the post-commit hook to the main repository
cp scripts/post-commit .git/hooks/
chmod +x .git/hooks/post-commit