#!/bin/bash

# === Configurazione ===
PROJECT_DIR="/var/www/mevacoin"
GIT_REPO="https://github.com/pasqualelembo78/webwallet.git"
GIT_USER="Pasquale Lembo"
GIT_EMAIL="lembopasquale78@gmail.com"

echo "==== Vai nella cartella del progetto ===="
cd "$PROJECT_DIR" || { echo "❌ Cartella $PROJECT_DIR non trovata"; exit 1; }

echo "==== Inizializzazione Git ===="
git init

echo "==== Configurazione Git ===="
git config --global user.name "$GIT_USER"
git config --global user.email "$GIT_EMAIL"

echo "==== Creazione .gitignore ===="
cat > .gitignore <<'EOF'
# Ignora file sensibili e build
*.wallet
*.keys
*.log
build/
node_modules/
dist/
*~
EOF

echo "==== Collegamento alla repo remota ===="
# Rimuove origin se esiste già
git remote remove origin 2>/dev/null
git remote add origin "$GIT_REPO"

echo "==== Aggiunta file e commit ===="
git add .
git commit -m "Primo invio del progetto mevacoin web"

echo "==== Imposta branch principale ===="
git branch -M main

echo "==== Invio su GitHub ===="
git push -u origin main

echo "✅ Operazione completata. Se è la prima volta ti verrà chiesto di autenticarti con token GitHub."
