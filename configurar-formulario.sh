#!/usr/bin/env bash
# Cria formulario/config.php com os dados de envio de email do formulário de contacto.
# O ficheiro fica só neste computador (não entra no Git) e é enviado para o alojamento pelo publicar.sh.

set -euo pipefail
cd "$(dirname "$0")"

DESTINO="formulario/config.php"

if [ -f "$DESTINO" ]; then
  read -rp "Já existe $DESTINO. Substituir? [s/N]: " RESPOSTA
  [[ "$RESPOSTA" =~ ^[sS]$ ]] || exit 0
fi

# Escapa um valor para uma string PHP entre plicas
php_str() {
  local v="$1"
  v=${v//\\/\\\\}
  v=${v//\'/\\\'}
  printf "'%s'" "$v"
}

echo "Dados da caixa de email que envia as mensagens (consultar no painel da Amen, secção de email)."
read -rp "Email remetente [info@gadgetmetodo.pt]: " REMETENTE
REMETENTE=${REMETENTE:-info@gadgetmetodo.pt}
read -rp "Email que recebe os pedidos [info@gadgetmetodo.pt]: " DESTINATARIO
DESTINATARIO=${DESTINATARIO:-info@gadgetmetodo.pt}
read -rp "Email em cópia oculta (BCC), vazio para nenhum: " BCC
read -rp "Servidor SMTP (vazio para usar o envio do próprio alojamento): " SMTP_HOST

SMTP_PORTA=465
SMTP_SEGURANCA=ssl
SMTP_UTILIZADOR="$REMETENTE"
SMTP_SENHA=""
if [ -n "$SMTP_HOST" ]; then
  read -rp "Porta SMTP [465]: " P
  SMTP_PORTA=${P:-465}
  if [ "$SMTP_PORTA" = "587" ]; then SMTP_SEGURANCA=tls; fi
  read -rp "Cifra: ssl (porta 465) ou tls (porta 587) [$SMTP_SEGURANCA]: " C
  SMTP_SEGURANCA=${C:-$SMTP_SEGURANCA}
  read -rp "Utilizador SMTP [$REMETENTE]: " U
  SMTP_UTILIZADOR=${U:-$REMETENTE}
  read -rsp "Palavra-passe da caixa de email: " SMTP_SENHA
  echo
fi

umask 077
cat > "$DESTINO" <<EOF
<?php
// Gerado por configurar-formulario.sh. Não colocar no Git.
return [
    'remetente_email' => $(php_str "$REMETENTE"),
    'remetente_nome'  => 'Gadgetmetodo, Lda.',
    'destinatario'    => $(php_str "$DESTINATARIO"),
    'bcc'             => $(php_str "$BCC"),
    'smtp' => [
        'host'       => $(php_str "$SMTP_HOST"),
        'porta'      => $(printf '%d' "$SMTP_PORTA"),
        'seguranca'  => $(php_str "$SMTP_SEGURANCA"),
        'utilizador' => $(php_str "$SMTP_UTILIZADOR"),
        'senha'      => $(php_str "$SMTP_SENHA"),
    ],
    'origens' => ['https://www.gadgetmetodo.pt', 'https://gadgetmetodo.pt'],
    'site'    => 'https://www.gadgetmetodo.pt',
];
EOF

echo "Criado $DESTINO. Publique com ./publicar.sh para o enviar para o alojamento."
