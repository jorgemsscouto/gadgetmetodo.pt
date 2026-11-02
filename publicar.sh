#!/usr/bin/env bash
# Publica o site por FTP (com TLS quando o servidor o suporta) a partir desta pasta.
# Requisito: lftp (no macOS: brew install lftp).
# Utilização: ./publicar.sh             publica (pede as credenciais só na primeira vez)
#             ./publicar.sh --esquecer  apaga as credenciais guardadas (por exemplo, após mudar a palavra-passe)
# Envia os ficheiros novos ou diferentes dos que estão no servidor e nunca apaga nada.

set -euo pipefail
cd "$(dirname "$0")"

# Servidor, utilizador e pasta ficam em .publicar.conf; a palavra-passe fica nas Chaves do macOS
# (noutros sistemas, em .publicar.senha com acesso só do dono). Nenhum destes ficheiros vai para o Git nem para o site.
CONF=".publicar.conf"
FICHEIRO_SENHA=".publicar.senha"
SERVICO_CHAVES="gadgetmetodo-ftp"
TEM_CHAVES=false
command -v security >/dev/null 2>&1 && TEM_CHAVES=true

if [ "${1:-}" = "--esquecer" ]; then
  if [ -f "$CONF" ]; then
    UTILIZADOR=$(sed -n 's/^UTILIZADOR=//p' "$CONF")
    $TEM_CHAVES && security delete-generic-password -s "$SERVICO_CHAVES" -a "$UTILIZADOR" >/dev/null 2>&1 || true
  fi
  rm -f "$CONF" "$FICHEIRO_SENHA"
  echo "Credenciais apagadas. Na próxima publicação serão pedidas de novo."
  exit 0
fi

if ! command -v lftp >/dev/null 2>&1; then
  echo "Falta o lftp. No macOS, instale com: brew install lftp"
  exit 1
fi

if [ -f "$CONF" ]; then
  SERVIDOR=$(sed -n 's/^SERVIDOR=//p' "$CONF")
  UTILIZADOR=$(sed -n 's/^UTILIZADOR=//p' "$CONF")
  PASTA=$(sed -n 's/^PASTA=//p' "$CONF")
else
  read -rp "Servidor FTP [gadgf9.ftp.tb-hosting.com]: " SERVIDOR
  SERVIDOR=${SERVIDOR:-gadgf9.ftp.tb-hosting.com}
  read -rp "Utilizador FTP: " UTILIZADOR
  read -rp "Pasta do site no servidor [www]: " PASTA
  PASTA=${PASTA:-www}
fi

case "$PASTA" in
  ""|/|.|./)
    echo "A raiz do FTP não é pública: o site tem de ir para a pasta www. Corrija com ./publicar.sh --esquecer"
    exit 1
    ;;
esac

LFTP_PASSWORD=""
if $TEM_CHAVES; then
  LFTP_PASSWORD=$(security find-generic-password -s "$SERVICO_CHAVES" -a "$UTILIZADOR" -w 2>/dev/null || true)
elif [ -f "$FICHEIRO_SENHA" ]; then
  LFTP_PASSWORD=$(cat "$FICHEIRO_SENHA")
fi
NOVA_SENHA=false
if [ -z "$LFTP_PASSWORD" ]; then
  read -rsp "Palavra-passe FTP (fica guardada para as próximas vezes): " LFTP_PASSWORD
  echo
  NOVA_SENHA=true
fi
export LFTP_PASSWORD

# Atualiza a configuração do formulário a partir dos segredos guardados (não pede nada)
./configurar-formulario.sh --automatico || true

if ! lftp --env-password -u "$UTILIZADOR" "$SERVIDOR" <<EOF
set ftp:ssl-allow yes
set net:max-retries 2
mirror --reverse --verbose --no-perms \
  --exclude-glob .git/ \
  --exclude-glob .gitignore \
  --exclude-glob .nojekyll \
  --exclude-glob .DS_Store \
  --exclude-glob .publicar.conf \
  --exclude-glob .publicar.senha \
  --exclude-glob ".formulario.*" \
  --exclude-glob README.md \
  --exclude-glob publicar.sh \
  --exclude-glob configurar-formulario.sh \
  --exclude-glob formulario/config.exemplo.php \
  --exclude-glob luxury-tours-portugal/config.exemplo.php \
  --exclude-glob luxury-tours-portugal/LEIA-ME.md \
  --exclude-glob luxury-tours-portugal/dados/ \
  --exclude-glob documentos/ \
  --exclude-glob ferramentas/ \
  . "$PASTA"
bye
EOF
then
  echo "A publicação falhou. Se a palavra-passe ou o utilizador mudaram, corra ./publicar.sh --esquecer e publique de novo."
  exit 1
fi

# Guarda as credenciais só depois de uma publicação bem-sucedida
if [ ! -f "$CONF" ]; then
  (umask 077; printf 'SERVIDOR=%s\nUTILIZADOR=%s\nPASTA=%s\n' "$SERVIDOR" "$UTILIZADOR" "$PASTA" > "$CONF")
fi
if $NOVA_SENHA; then
  if $TEM_CHAVES; then
    security add-generic-password -U -s "$SERVICO_CHAVES" -a "$UTILIZADOR" -w "$LFTP_PASSWORD" >/dev/null
    echo "Credenciais guardadas (palavra-passe nas Chaves do macOS)."
  else
    (umask 077; printf '%s' "$LFTP_PASSWORD" > "$FICHEIRO_SENHA")
    echo "Credenciais guardadas em $CONF e $FICHEIRO_SENHA."
  fi
fi

echo "Publicação concluída. Verifique em https://www.gadgetmetodo.pt"

# Avisa o Bing e outros motores aderentes ao IndexNow das páginas do sitemap (não bloqueia em caso de falha)
CHAVE_INDEXNOW="3c88a8903774d00a271eaa88a1eaeeb1"
URLS=$(grep -o '<loc>[^<]*</loc>' sitemap.xml | sed 's#</*loc>##g' | sed 's/.*/"&"/' | paste -sd, -)
if curl -s -o /dev/null -w "%{http_code}" -X POST "https://api.indexnow.org/indexnow" \
  -H "Content-Type: application/json; charset=utf-8" \
  -d "{\"host\":\"www.gadgetmetodo.pt\",\"key\":\"$CHAVE_INDEXNOW\",\"keyLocation\":\"https://www.gadgetmetodo.pt/$CHAVE_INDEXNOW.txt\",\"urlList\":[$URLS]}" | grep -qE "^20[02]$"; then
  echo "Motores de pesquisa avisados via IndexNow."
else
  echo "Aviso IndexNow sem sucesso (não afeta o site)."
fi
