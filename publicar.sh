#!/usr/bin/env bash
# Publica o site por FTP (com TLS quando o servidor o suporta) a partir desta pasta.
# Requisito: lftp (no macOS: brew install lftp).
# Utilização: ./publicar.sh
# Envia apenas ficheiros novos ou alterados e nunca apaga nada no servidor.

set -euo pipefail
cd "$(dirname "$0")"

if ! command -v lftp >/dev/null 2>&1; then
  echo "Falta o lftp. No macOS, instale com: brew install lftp"
  exit 1
fi

read -rp "Servidor FTP (ex.: ftp.gadgetmetodo.pt): " SERVIDOR
read -rp "Utilizador FTP: " UTILIZADOR
read -rp "Pasta do site no servidor [public_html]: " PASTA
PASTA=${PASTA:-public_html}
read -rsp "Palavra-passe FTP: " LFTP_PASSWORD
echo
export LFTP_PASSWORD

lftp --env-password -u "$UTILIZADOR" "$SERVIDOR" <<EOF
set ftp:ssl-allow yes
set net:max-retries 2
mirror --reverse --only-newer --verbose \
  --exclude-glob .git/ \
  --exclude-glob .gitignore \
  --exclude-glob .nojekyll \
  --exclude-glob .DS_Store \
  --exclude-glob README.md \
  --exclude-glob publicar.sh \
  . "$PASTA"
bye
EOF

echo "Publicação concluída. Verifique em https://www.gadgetmetodo.pt"
