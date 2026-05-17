#!/bin/bash

set -e  # Zatrzymaj skrypt, jeśli którykolwiek krok zakończy się błędem

# === KONFIGURACJA ===
KEY_PATH="/home/mk/Pobrane/RentalKey.pem"
LOCAL_PATH="/home/mk/rental-app/"
REMOTE_USER="ubuntu"
REMOTE_HOST="ec2-52-58-11-23.eu-central-1.compute.amazonaws.com"
REMOTE_PATH="/var/www/rental-app"
SERVER_TYPE="nginx"  # Możliwe: nginx, apache, none

# === PRZESYŁANIE ===
echo "📦 Przesyłanie projektu do $REMOTE_USER@$REMOTE_HOST..."
rsync -avz --delete --no-owner --no-group -e "ssh -i $KEY_PATH" "$LOCAL_PATH" "$REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH"

# === CACHE SYMFONY ===
echo "🧹 Czyszczenie cache Symfony..."
ssh -i "$KEY_PATH" "$REMOTE_USER@$REMOTE_HOST" << EOF
cd $REMOTE_PATH
php bin/console cache:clear
php bin/console cache:warmup
EOF

# === RESTART SERWERA WWW ===
if [ "$SERVER_TYPE" = "nginx" ]; then
  echo "🔁 Restart Nginx..."
  ssh -i "$KEY_PATH" "$REMOTE_USER@$REMOTE_HOST" "sudo systemctl restart nginx"
elif [ "$SERVER_TYPE" = "apache" ]; then
  echo "🔁 Restart Apache..."
  ssh -i "$KEY_PATH" "$REMOTE_USER@$REMOTE_HOST" "sudo systemctl restart apache2"
else
  echo "⚠️ Pominięto restart serwera (ustawienie: SERVER_TYPE=none)"
fi

echo "✅ Wdrożenie zakończone pomyślnie!"
