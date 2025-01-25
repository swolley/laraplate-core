#!/bin/bash

# Copia l'hook post-commit nel repository principale
cp scripts/post-commit .git/hooks/
chmod +x .git/hooks/post-commit