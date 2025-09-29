#!/bin/bash
set -e

echo "=== Pulizia Node/NPM vecchi ==="
sudo apt remove --purge -y nodejs npm
sudo apt autoremove -y

echo "=== Installazione dipendenze per n ==="
sudo apt update
sudo apt install -y curl build-essential

echo "=== Installazione n (Node version manager globale) ==="
sudo rm -f /usr/local/bin/n
sudo npm install -g n

echo "=== Aggiornamento Node e NPM all'ultima LTS ==="
sudo n lts
node -v
npm -v

echo "=== Clonazione repo web wallet ==="
rm -rf monerowebwallet.com
git clone https://github.com/woodser/monerowebwallet.com
cd monerowebwallet.com

echo "=== Installazione dipendenze ==="
set +e
npm install
set -e

echo "=== Build web wallet con OpenSSL legacy provider ==="
NODE_OPTIONS=--openssl-legacy-provider ./bin/build_browser_app.sh

echo "=== Web wallet pronto! ==="
echo "Apri il browser su http://localhost:9100"
