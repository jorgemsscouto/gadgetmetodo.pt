#!/usr/bin/env bash
# Cria formulario/config.php com os dados de envio de email do formulário de contacto.
# Utilização: ./configurar-formulario.sh              configura e envia um email de teste
#             ./configurar-formulario.sh --automatico  só gera o config.php (usado pelo publicar.sh)
#             ./configurar-formulario.sh --esquecer    apaga a palavra-passe guardada e o config.php
# A palavra-passe é pedida uma única vez e fica nas Chaves do macOS (noutros sistemas, em
# .formulario.senha com acesso só do dono). Nem ela nem o config.php entram no Git.

set -euo pipefail
cd "$(dirname "$0")"

# Dados de envio (Amen)
REMETENTE="info@gadgetmetodo.pt"
DESTINATARIO="info@gadgetmetodo.pt"
BCC="jorgemsscouto@gmail.com"
SMTP_HOST="smtp-pt.securemail.pro"
SMTP_PORTA=465
SMTP_SEGURANCA="ssl"
SMTP_UTILIZADOR="info@gadgetmetodo.pt"

DESTINO="formulario/config.php"
FICHEIRO_SENHA=".formulario.senha"
SERVICO_CHAVES="gadgetmetodo-smtp"
TEM_CHAVES=false
command -v security >/dev/null 2>&1 && TEM_CHAVES=true
MODO="${1:-}"

if [ "$MODO" = "--esquecer" ]; then
  $TEM_CHAVES && security delete-generic-password -s "$SERVICO_CHAVES" -a "$SMTP_UTILIZADOR" >/dev/null 2>&1 || true
  rm -f "$FICHEIRO_SENHA" "$DESTINO"
  echo "Palavra-passe do email e config.php apagados."
  exit 0
fi

SMTP_SENHA=""
if $TEM_CHAVES; then
  SMTP_SENHA=$(security find-generic-password -s "$SERVICO_CHAVES" -a "$SMTP_UTILIZADOR" -w 2>/dev/null || true)
elif [ -f "$FICHEIRO_SENHA" ]; then
  SMTP_SENHA=$(cat "$FICHEIRO_SENHA")
fi

if [ -z "$SMTP_SENHA" ]; then
  if [ "$MODO" = "--automatico" ]; then
    echo "Formulário por configurar: corra ./configurar-formulario.sh uma vez."
    exit 1
  fi
  read -rsp "Palavra-passe da caixa $SMTP_UTILIZADOR (pedida só desta vez): " SMTP_SENHA
  echo
  NOVA_SENHA=true
else
  NOVA_SENHA=false
fi

# Escapa um valor para uma string PHP entre plicas
php_str() {
  local v="$1"
  v=${v//\\/\\\\}
  v=${v//\'/\\\'}
  printf "'%s'" "$v"
}

escrever_config() {
  (umask 077; cat > "$DESTINO" <<EOF
<?php
// Gerado por configurar-formulario.sh. Não colocar no Git.
return [
    'remetente_email' => $(php_str "$REMETENTE"),
    'remetente_nome'  => 'Gadgetmetodo, Lda.',
    'destinatario'    => $(php_str "$DESTINATARIO"),
    'bcc'             => $(php_str "$BCC"),
    'smtp' => [
        'host'       => $(php_str "$SMTP_HOST"),
        'porta'      => $SMTP_PORTA,
        'seguranca'  => $(php_str "$SMTP_SEGURANCA"),
        'utilizador' => $(php_str "$SMTP_UTILIZADOR"),
        'senha'      => $(php_str "$SMTP_SENHA"),
    ],
    'origens' => ['https://www.gadgetmetodo.pt', 'https://gadgetmetodo.pt'],
    'site'    => 'https://www.gadgetmetodo.pt',
];
EOF
  )
}

if [ "$MODO" = "--automatico" ]; then
  escrever_config
  exit 0
fi

# Envio de teste a partir deste computador para a caixa da empresa e para a cópia oculta
CRED=$(mktemp); MSG=$(mktemp)
trap 'rm -f "$CRED" "$MSG"' EXIT
printf 'machine %s login %s password %s\n' "$SMTP_HOST" "$SMTP_UTILIZADOR" "$SMTP_SENHA" > "$CRED"
printf 'From: Gadgetmetodo, Lda. <%s>\r\nTo: <%s>\r\nSubject: Teste de envio do formulario do site\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nEmail de teste enviado por configurar-formulario.sh atraves de %s (porta %s).\r\nSe o recebeu, o envio do formulario esta bem configurado.\r\n' "$REMETENTE" "$DESTINATARIO" "$SMTP_HOST" "$SMTP_PORTA" > "$MSG"
if [ "$SMTP_SEGURANCA" = "ssl" ]; then URL="smtps://$SMTP_HOST:$SMTP_PORTA"; else URL="smtp://$SMTP_HOST:$SMTP_PORTA"; fi

echo "A enviar email de teste para $DESTINATARIO e $BCC..."
if curl -sS --max-time 30 --ssl-reqd --netrc-file "$CRED" --mail-from "$REMETENTE" \
  --mail-rcpt "$DESTINATARIO" --mail-rcpt "$BCC" --upload-file "$MSG" "$URL"; then
  echo "Email de teste enviado. Confirme a receção em $DESTINATARIO e em $BCC (veja também o spam)."
else
  echo "O envio de teste falhou: a palavra-passe não foi guardada. Confirme-a no painel da Amen e corra o script de novo."
  exit 1
fi

# Guarda a palavra-passe só depois de um envio bem-sucedido
if $NOVA_SENHA; then
  if $TEM_CHAVES; then
    security add-generic-password -U -s "$SERVICO_CHAVES" -a "$SMTP_UTILIZADOR" -w "$SMTP_SENHA" >/dev/null
    echo "Palavra-passe guardada nas Chaves do macOS."
  else
    (umask 077; printf '%s' "$SMTP_SENHA" > "$FICHEIRO_SENHA")
    echo "Palavra-passe guardada em $FICHEIRO_SENHA."
  fi
fi

escrever_config
echo "Criado $DESTINO. Publique com ./publicar.sh para o enviar para o alojamento."
