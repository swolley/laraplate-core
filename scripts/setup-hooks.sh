#!/bin/bash

# Copia l'hook post-commit nel repository principale
cp scripts/post-commit .git/hooks/
chmod +x .git/hooks/post-commit

# Esegui lo script di setup in ogni submodule
#git submodule foreach 'cd scripts && ./setup-hooks.sh'