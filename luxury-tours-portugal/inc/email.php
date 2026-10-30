<?php
// Inquérito Luxury Tours Portugal: modelos do email de convite (assunto, HTML e texto simples) em cada idioma.
// Os textos vêm de i18n/*.json (secção "email"); o inglês é a fonte das restantes traduções.
// Nos modelos para descarregar, {{greeting}} e {{link}} são campos de mail merge (colunas do CSV de envio).

declare(strict_types=1);

function ltp_email_assunto(string $idioma): string
{
    return ltp_t($idioma, 'email.subject');
}

function ltp_email_saudacao(string $idioma, string $nome, string $tratamento = ''): string
{
    if (trim($nome) === '') {
        return '';
    }
    $chave = $tratamento === 'm' ? 'intro.greeting_m' : ($tratamento === 'f' ? 'intro.greeting_f' : 'intro.greeting');
    return ltp_t($idioma, $chave, ['name' => $nome]);
}

function ltp_email_data_limite(string $idioma): string
{
    $fecho = ltp_data_fecho();
    return $fecho ? ltp_formatar_data($fecho, $idioma) : '';
}

// $saudacao e $ligacao são texto já pronto (ou os campos {{greeting}} e {{link}} para mail merge)
function ltp_email_html(string $idioma, string $saudacao, string $ligacao): string
{
    $t = fn(string $c, array $v = []) => ltp_h(ltp_t($idioma, $c, $v));
    $rtl = (ltp_textos($idioma)['dir'] ?? 'ltr') === 'rtl';
    $dir = $rtl ? 'rtl' : 'ltr';
    $alinhar = $rtl ? 'right' : 'left';
    $base = ltp_endereco();
    $logo = ltp_h($base . 'assets/img/luxury-tours-portugal.png');
    $logo2 = ltp_h($base . 'assets/img/wine-tourism-in-portugal.png');
    $privacidade = (string) ltp_cfg()['privacidade_url'];
    $fontes = $rtl ? "Tahoma, 'Segoe UI', Arial, sans-serif" : "'Helvetica Neue', Helvetica, Arial, sans-serif";
    $titulos = $rtl ? $fontes : "Georgia, 'Times New Roman', serif";
    $data = ltp_email_data_limite($idioma);
    $p2 = ltp_h(ltp_t($idioma, 'email.p2', ['date' => "\u{2068}" . $data . "\u{2069}"]));
    $l = ltp_h($ligacao);
    $s = ltp_h($saudacao);
    $linhaPrivacidade = $privacidade !== ''
        ? '<br><a href="' . ltp_h($privacidade) . '" style="color:#7d6332;">' . $t('email.privacy') . '</a>'
        : '';

    return <<<HTML
<!doctype html>
<html lang="{$idioma}" dir="{$dir}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{$t('email.subject')}</title>
</head>
<body style="margin:0;padding:0;background:#faf7f1;" dir="{$dir}">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">{$t('email.preheader')}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#faf7f1;">
<tr><td align="center" style="padding:32px 12px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #e4dccd;" dir="{$dir}">
<tr><td align="center" style="padding:36px 32px 24px;border-bottom:1px solid #e4dccd;">
<img src="{$logo}" width="340" alt="Luxury Tours Portugal by Wine Tourism in Portugal" style="display:block;width:340px;max-width:100%;height:auto;border:0;">
</td></tr>
<tr><td dir="{$dir}" style="padding:36px 40px 8px;text-align:{$alinhar};font-family:{$fontes};font-size:16px;line-height:1.7;color:#2f2e2c;">
<p style="margin:0 0 18px;font-family:{$titulos};font-size:19px;color:#2f2e2c;">{$s}</p>
<p style="margin:0 0 16px;">{$t('email.p1')}</p>
<p style="margin:0 0 28px;color:#5d5850;">{$p2}</p>
</td></tr>
<tr><td align="center" style="padding:0 40px 28px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
<td style="background:#2f2e2c;border-radius:2px;">
<a href="{$l}" style="display:inline-block;padding:15px 30px;font-family:{$fontes};font-size:15px;font-weight:bold;letter-spacing:0.03em;color:#ffffff;text-decoration:none;">{$t('email.button')}</a>
</td></tr></table>
</td></tr>
<tr><td dir="{$dir}" style="padding:0 40px 32px;text-align:{$alinhar};font-family:{$fontes};font-size:13px;line-height:1.6;color:#5d5850;">
<p style="margin:0 0 6px;">{$t('email.link_fallback')}</p>
<p style="margin:0 0 26px;word-break:break-all;" dir="ltr"><a href="{$l}" style="color:#7d6332;">{$l}</a></p>
<p style="margin:0;font-size:16px;color:#2f2e2c;">{$t('email.closing')}</p>
<p style="margin:4px 0 0;font-family:{$titulos};font-size:17px;color:#2f2e2c;">{$t('email.signature')}</p>
</td></tr>
<tr><td align="center" style="padding:24px 32px 30px;background:#faf7f1;border-top:1px solid #e4dccd;font-family:{$fontes};font-size:12px;line-height:1.6;color:#5d5850;">
<img src="{$logo2}" width="180" alt="Wine Tourism in Portugal" style="display:block;width:180px;max-width:100%;height:auto;border:0;margin:0 auto 12px;">
{$t('email.footer')}{$linhaPrivacidade}
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

function ltp_email_texto(string $idioma, string $saudacao, string $ligacao): string
{
    $data = ltp_email_data_limite($idioma);
    $linhas = [
        $saudacao,
        '',
        ltp_t($idioma, 'email.p1'),
        '',
        ltp_t($idioma, 'email.p2', ['date' => $data]),
        '',
        ltp_t($idioma, 'email.button') . ':',
        $ligacao,
        '',
        ltp_t($idioma, 'email.closing'),
        ltp_t($idioma, 'email.signature'),
        '',
        '--',
        ltp_t($idioma, 'email.footer'),
    ];
    $privacidade = (string) ltp_cfg()['privacidade_url'];
    if ($privacidade !== '') {
        $linhas[] = ltp_t($idioma, 'email.privacy') . ': ' . $privacidade;
    }
    if ($saudacao === '') {
        array_splice($linhas, 0, 2);
    }
    return implode("\n", $linhas) . "\n";
}
