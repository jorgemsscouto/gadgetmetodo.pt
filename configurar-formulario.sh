#!/usr/bin/env bash
# Cria formulario/config.php com os dados de envio de email do formulário e do registo do catálogo.
# Utilização: ./configurar-formulario.sh              configura e envia um email de teste
#             ./configurar-formulario.sh --automatico  só gera o config.php (usado pelo publicar.sh)
#             ./configurar-formulario.sh --acesso      mostra o endereço e a palavra-passe da área reservada
#             ./configurar-formulario.sh --esquecer    apaga os segredos guardados e o config.php
# A palavra-passe do email é pedida uma única vez. Os segredos ficam nas Chaves do macOS (noutros
# sistemas, em ficheiros .formulario.* com acesso só do dono) e nunca entram no Git nem no site.

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
AREA_RESERVADA="https://www.gadgetmetodo.pt/formulario/registos.php"

DESTINO="formulario/config.php"
TEM_CHAVES=false
command -v security >/dev/null 2>&1 && TEM_CHAVES=true
MODO="${1:-}"

# Segredos: nas Chaves do macOS ou em ficheiros locais protegidos
ler_segredo() {   # nome
  if $TEM_CHAVES; then
    security find-generic-password -s "gadgetmetodo-$1" -a gadgetmetodo -w 2>/dev/null || true
  elif [ -f ".formulario.$1" ]; then
    cat ".formulario.$1"
  fi
}
guardar_segredo() {   # nome valor
  if $TEM_CHAVES; then
    security add-generic-password -U -s "gadgetmetodo-$1" -a gadgetmetodo -w "$2" >/dev/null
  else
    (umask 077; printf '%s' "$2" > ".formulario.$1")
  fi
}
apagar_segredo() {   # nome
  if $TEM_CHAVES; then
    security delete-generic-password -s "gadgetmetodo-$1" -a gadgetmetodo >/dev/null 2>&1 || true
  fi
  rm -f ".formulario.$1"
}
aleatorio() {   # n.º de bytes, em hexadecimal
  od -An -tx1 -N"$1" /dev/urandom | tr -d ' \n'
}
sha256() {
  if command -v shasum >/dev/null 2>&1; then shasum -a 256; else sha256sum; fi | cut -d' ' -f1
}

if [ "$MODO" = "--esquecer" ]; then
  for nome in smtp chave registos; do apagar_segredo "$nome"; done
  $TEM_CHAVES && security delete-generic-password -s gadgetmetodo-smtp -a "$SMTP_UTILIZADOR" >/dev/null 2>&1 || true
  rm -f .formulario.senha
  rm -f "$DESTINO"
  echo "Segredos do formulário e config.php apagados."
  exit 0
fi

# Chave de assinatura dos links e palavra-passe da área reservada: criadas uma vez e mantidas
CHAVE=$(ler_segredo chave)
if [ -z "$CHAVE" ]; then
  CHAVE=$(aleatorio 32)
  guardar_segredo chave "$CHAVE"
fi
SENHA_REGISTOS=$(ler_segredo registos)
NOVA_AREA=false
if [ -z "$SENHA_REGISTOS" ]; then
  SENHA_REGISTOS=$(aleatorio 12)
  guardar_segredo registos "$SENHA_REGISTOS"
  NOVA_AREA=true
fi

mostrar_acesso() {
  echo "Área reservada (pedidos e aberturas do catálogo): $AREA_RESERVADA"
  echo "Palavra-passe: $SENHA_REGISTOS"
}

if [ "$MODO" = "--acesso" ]; then
  mostrar_acesso
  exit 0
fi

SMTP_SENHA=$(ler_segredo smtp)
# Palavra-passe guardada pela versão anterior deste script
if [ -z "$SMTP_SENHA" ]; then
  if $TEM_CHAVES; then
    SMTP_SENHA=$(security find-generic-password -s gadgetmetodo-smtp -a "$SMTP_UTILIZADOR" -w 2>/dev/null || true)
  elif [ -f .formulario.senha ]; then
    SMTP_SENHA=$(cat .formulario.senha)
  fi
  [ -n "$SMTP_SENHA" ] && guardar_segredo smtp "$SMTP_SENHA"
fi
NOVA_SENHA=false
if [ -z "$SMTP_SENHA" ]; then
  if [ "$MODO" = "--automatico" ]; then
    echo "Formulário por configurar: corra ./configurar-formulario.sh uma vez."
    exit 1
  fi
  read -rsp "Palavra-passe da caixa $SMTP_UTILIZADOR (pedida só desta vez): " SMTP_SENHA
  echo
  NOVA_SENHA=true
fi

# Escapa um valor para uma string PHP entre plicas
php_str() {
  local v="$1"
  v=${v//\\/\\\\}
  v=${v//\'/\\\'}
  printf "'%s'" "$v"
}

escrever_config() {
  local hash_registos
  hash_registos=$(printf '%s' "$SENHA_REGISTOS" | sha256)
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
    'chave'        => '$CHAVE',
    'admin_sha256' => '$hash_registos',
    'origens' => ['https://www.gadgetmetodo.pt', 'https://gadgetmetodo.pt'],
    'site'    => 'https://www.gadgetmetodo.pt',
];
EOF
  )
}

if [ "$MODO" = "--automatico" ]; then
  escrever_config
  if $NOVA_AREA; then
    echo "Criado o acesso à área reservada. Para ver a palavra-passe: ./configurar-formulario.sh --acesso"
  fi
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

# Guarda a palavra-passe do email só depois de um envio bem-sucedido
if $NOVA_SENHA; then
  guardar_segredo smtp "$SMTP_SENHA"
  echo "Palavra-passe do email guardada."
fi

escrever_config
echo "Criado $DESTINO."
echo
mostrar_acesso
echo "(Para voltar a ver estes dados: ./configurar-formulario.sh --acesso)"
echo
echo "Publique com ./publicar.sh para enviar a configuração para o alojamento."
