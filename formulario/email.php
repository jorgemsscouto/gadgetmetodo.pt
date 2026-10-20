<?php
// Envio de email partilhado pelo formulário de contacto e pela área de clientes.

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/lib/PHPMailer/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/SMTP.php';

function novo_email(array $cfg): PHPMailer
{
    $m = new PHPMailer(true);
    $m->CharSet = PHPMailer::CHARSET_UTF8;
    $m->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
    $smtp = $cfg['smtp'] ?? [];
    if (!empty($smtp['host'])) {
        $m->isSMTP();
        $m->Host = $smtp['host'];
        $m->Port = (int) ($smtp['porta'] ?? 465);
        $m->Timeout = 20;
        $seguranca = $smtp['seguranca'] ?? 'ssl';
        if ($seguranca === 'ssl') {
            $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($seguranca === 'tls') {
            $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $m->SMTPAutoTLS = false;
        }
        if (!empty($smtp['utilizador'])) {
            $m->SMTPAuth = true;
            $m->Username = $smtp['utilizador'];
            $m->Password = $smtp['senha'] ?? '';
        }
    } else {
        $m->isMail();
    }
    $m->setFrom($cfg['remetente_email'], $cfg['remetente_nome']);
    $m->Sender = $cfg['remetente_email'];
    return $m;
}
