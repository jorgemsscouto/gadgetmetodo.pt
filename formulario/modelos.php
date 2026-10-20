<?php
// Modelos dos emails do formulário e da página de erro para envios sem JavaScript.

declare(strict_types=1);

const COR_MARINHO = '#0A0F26';
const COR_PETROLEO = '#00636A';
const COR_TEXTO = '#333333';
const COR_SUAVE = '#687280';
const COR_LINHA = '#E5E7EB';
const COR_FUNDO = '#F5F7F8';
const FONTE = "'Segoe UI', Helvetica, Arial, sans-serif";

function h(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Nome usado na saudação: só letras e pontuação de nomes, para não reproduzir conteúdo arbitrário
function nome_saudacao(string $nome): string
{
    return preg_match("/^\\p{L}[\\p{L}\\s'.\\-]{1,59}$/u", $nome) ? $nome : '';
}

function bloco_assinatura_texto(array $d): string
{
    return "Gadgetmetodo, Lda.\nRua das Cruzes, 625, 4100-195 Porto, Portugal\n+351 915 416 112 · info@gadgetmetodo.pt\n" . $d['site'] . "\n";
}

/* Email de confirmação ao remetente */

function email_confirmacao_html(array $d): string
{
    $saudacao = nome_saudacao($d['nome']);
    $saudacao = $saudacao !== '' ? 'Caro(a) ' . h($saudacao) . ',' : 'Caro(a) Senhor(a),';
    $tipo = $d['tipo'] === 'Pedido de orçamento' ? 'pedido de orçamento' : 'pedido de informações';
    $servicos = implode('<br>', array_map('h', $d['servicos']));
    $f = FONTE;

    $linhas_resumo = '';
    foreach ([['Referência', h($d['referencia'])], ['Tipo de pedido', h($d['tipo'])], ['Áreas de interesse', $servicos], ['Data de receção', h($d['data'])]] as $i => [$rotulo, $valor]) {
        $borda = $i > 0 ? 'border-top:1px solid ' . COR_LINHA . ';' : '';
        $linhas_resumo .= '<tr><td style="' . $borda . 'padding:12px 0;width:170px;vertical-align:top;font-family:' . $f . ';font-size:13px;color:' . COR_SUAVE . ';">' . $rotulo . '</td>'
            . '<td style="' . $borda . 'padding:12px 0;vertical-align:top;font-family:' . $f . ';font-size:14px;color:' . COR_MARINHO . ';font-weight:600;">' . $valor . '</td></tr>';
    }

    $passos = '';
    foreach ([
        ['Análise', 'A nossa equipa analisa o pedido e o contexto da sua organização.'],
        ['Primeira conversa', 'Entramos em contacto para compreender os seus objetivos, prioridades e prazos.'],
        ['Proposta à medida', 'Apresentamos uma proposta com âmbito, entregáveis, calendário e investimento claros.'],
    ] as $i => [$titulo, $texto]) {
        $passos .= '<tr><td style="padding:0 16px 18px 0;vertical-align:top;width:36px;">'
            . '<div style="width:32px;height:32px;line-height:32px;text-align:center;background:' . COR_MARINHO . ';color:#FFFFFF;border-radius:8px;font-family:' . $f . ';font-size:13px;font-weight:700;">0' . ($i + 1) . '</div></td>'
            . '<td style="padding:0 0 18px;vertical-align:top;font-family:' . $f . ';font-size:14px;line-height:1.55;color:' . COR_TEXTO . ';"><strong style="color:' . COR_MARINHO . ';">' . $titulo . '.</strong> ' . $texto . '</td></tr>';
    }

    $site = h($d['site']);
    $ref = h($d['referencia']);

    return '<!doctype html><html lang="pt-PT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Confirmação de receção</title></head>'
        . '<body style="margin:0;padding:0;background:' . COR_FUNDO . ';">'
        . '<div style="display:none;max-height:0;overflow:hidden;">Recebemos o seu ' . $tipo . ' (Ref. ' . $ref . '). Entraremos em contacto brevemente.</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . COR_FUNDO . ';"><tr><td align="center" style="padding:32px 16px;">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#FFFFFF;border:1px solid ' . COR_LINHA . ';">'
        // Cabeçalho
        . '<tr><td style="padding:32px 40px 24px;border-bottom:1px solid ' . COR_LINHA . ';">'
        . '<img src="' . $site . '/assets/marca/logotipo.png" width="200" alt="Gadgetmetodo, Lda." style="display:block;width:200px;height:auto;border:0;"></td></tr>'
        // Corpo
        . '<tr><td style="padding:40px 40px 8px;">'
        . '<p style="margin:0 0 12px;font-family:' . $f . ';font-size:11px;letter-spacing:2px;text-transform:uppercase;color:' . COR_PETROLEO . ';font-weight:700;">Confirmação de receção</p>'
        . '<h1 style="margin:0 0 24px;font-family:' . $f . ';font-size:24px;line-height:1.3;color:' . COR_MARINHO . ';font-weight:700;">Obrigado pelo seu contacto.</h1>'
        . '<p style="margin:0 0 16px;font-family:' . $f . ';font-size:15px;line-height:1.6;color:' . COR_TEXTO . ';">' . $saudacao . '</p>'
        . '<p style="margin:0 0 16px;font-family:' . $f . ';font-size:15px;line-height:1.6;color:' . COR_TEXTO . ';">Agradecemos o interesse nos serviços da Gadgetmetodo. Confirmamos a receção do seu ' . $tipo . ', que ficou registado com a referência <strong style="color:' . COR_MARINHO . ';">' . $ref . '</strong>.</p>'
        . '<p style="margin:0 0 24px;font-family:' . $f . ';font-size:15px;line-height:1.6;color:' . COR_TEXTO . ';">Um membro da nossa equipa irá analisar o pedido e entrará em contacto consigo brevemente.</p>'
        // Catálogo
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 32px;background:' . COR_FUNDO . ';"><tr><td style="padding:22px 24px;">'
        . '<p style="margin:0 0 6px;font-family:' . $f . ';font-size:11px;letter-spacing:2px;text-transform:uppercase;color:' . COR_PETROLEO . ';font-weight:700;">Catálogo de Serviços 2026</p>'
        . '<p style="margin:0 0 16px;font-family:' . $f . ';font-size:14px;line-height:1.55;color:' . COR_TEXTO . ';">Enquanto analisamos o seu pedido, conheça em detalhe as nossas áreas de serviço, a forma como trabalhamos e exemplos de intervenção.</p>'
        . '<a href="' . h($d['link_catalogo']) . '" style="display:inline-block;padding:12px 20px;background:' . COR_MARINHO . ';color:#FFFFFF;text-decoration:none;font-family:' . $f . ';font-size:14px;font-weight:700;border-radius:8px;">Abrir o catálogo (PDF)</a>'
        . '</td></tr></table>'
        // Resumo
        . '<p style="margin:0 0 4px;font-family:' . $f . ';font-size:11px;letter-spacing:2px;text-transform:uppercase;color:' . COR_SUAVE . ';font-weight:700;">Resumo do pedido</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 32px;border-top:2px solid ' . COR_MARINHO . ';border-bottom:1px solid ' . COR_LINHA . ';">' . $linhas_resumo . '</table>'
        // Próximos passos
        . '<p style="margin:0 0 16px;font-family:' . $f . ';font-size:11px;letter-spacing:2px;text-transform:uppercase;color:' . COR_SUAVE . ';font-weight:700;">Próximos passos</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">' . $passos . '</table>'
        . '<p style="margin:0 0 28px;font-family:' . $f . ';font-size:15px;line-height:1.6;color:' . COR_TEXTO . ';">Se pretender acrescentar informação, basta responder a este email indicando a referência do pedido.</p>'
        . '<p style="margin:0 0 4px;font-family:' . $f . ';font-size:15px;line-height:1.6;color:' . COR_TEXTO . ';">Com os melhores cumprimentos,</p>'
        . '<p style="margin:0 0 40px;font-family:' . $f . ';font-size:15px;line-height:1.6;color:' . COR_MARINHO . ';font-weight:700;">A equipa da Gadgetmetodo</p>'
        . '</td></tr>'
        // Rodapé institucional
        . '<tr><td style="padding:28px 40px;background:' . COR_MARINHO . ';">'
        . '<p style="margin:0 0 6px;font-family:' . $f . ';font-size:13px;color:#FFFFFF;font-weight:700;">Gadgetmetodo, Lda.</p>'
        . '<p style="margin:0;font-family:' . $f . ';font-size:12px;line-height:1.7;color:#C9CED8;">Rua das Cruzes, 625 · 4100-195 Porto · Portugal<br>'
        . '+351 915 416 112 · <a href="mailto:info@gadgetmetodo.pt" style="color:#C9CED8;">info@gadgetmetodo.pt</a> · <a href="' . $site . '" style="color:#C9CED8;">www.gadgetmetodo.pt</a></p>'
        . '<p style="margin:14px 0 0;font-family:' . $f . ';font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8FC3C6;">Simplificar · Organizar · Fazer acontecer</p>'
        . '</td></tr></table>'
        // Nota legal
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;"><tr><td style="padding:20px 8px 0;font-family:' . $f . ';font-size:11px;line-height:1.6;color:' . COR_SUAVE . ';">'
        . 'Esta mensagem foi enviada automaticamente em resposta a um pedido submetido em www.gadgetmetodo.pt. Se não fez este pedido, pode ignorá-la. '
        . 'Se abrir o catálogo através desta mensagem, a data e hora do acesso ficam registadas e associadas ao seu pedido, para acompanhamento. '
        . 'Os seus dados são tratados de acordo com a nossa <a href="' . $site . '/privacidade/" style="color:' . COR_SUAVE . ';">política de privacidade</a>. '
        . 'A informação contida nesta mensagem é confidencial e destina-se exclusivamente ao seu destinatário.'
        . '</td></tr></table>'
        . '</td></tr></table></body></html>';
}

function email_confirmacao_texto(array $d): string
{
    $saudacao = nome_saudacao($d['nome']);
    $saudacao = $saudacao !== '' ? 'Caro(a) ' . $saudacao . ',' : 'Caro(a) Senhor(a),';
    $tipo = $d['tipo'] === 'Pedido de orçamento' ? 'pedido de orçamento' : 'pedido de informações';
    return "CONFIRMAÇÃO DE RECEÇÃO\n\n"
        . "Obrigado pelo seu contacto.\n\n"
        . $saudacao . "\n\n"
        . "Agradecemos o interesse nos serviços da Gadgetmetodo. Confirmamos a receção do seu " . $tipo . ", que ficou registado com a referência " . $d['referencia'] . ".\n\n"
        . "Um membro da nossa equipa irá analisar o pedido e entrará em contacto consigo brevemente.\n\n"
        . "CATÁLOGO DE SERVIÇOS 2026\n"
        . "Enquanto analisamos o seu pedido, conheça em detalhe as nossas áreas de serviço, a forma como trabalhamos e exemplos de intervenção:\n"
        . $d['link_catalogo'] . "\n\n"
        . "RESUMO DO PEDIDO\n"
        . "Referência: " . $d['referencia'] . "\n"
        . "Tipo de pedido: " . $d['tipo'] . "\n"
        . "Áreas de interesse: " . implode('; ', $d['servicos']) . "\n"
        . "Data de receção: " . $d['data'] . "\n\n"
        . "PRÓXIMOS PASSOS\n"
        . "01. Análise: a nossa equipa analisa o pedido e o contexto da sua organização.\n"
        . "02. Primeira conversa: entramos em contacto para compreender os seus objetivos, prioridades e prazos.\n"
        . "03. Proposta à medida: apresentamos uma proposta com âmbito, entregáveis, calendário e investimento claros.\n\n"
        . "Se pretender acrescentar informação, basta responder a este email indicando a referência do pedido.\n\n"
        . "Com os melhores cumprimentos,\nA equipa da Gadgetmetodo\n\n"
        . "--\n" . bloco_assinatura_texto($d) . "\n"
        . "Esta mensagem foi enviada automaticamente em resposta a um pedido submetido em www.gadgetmetodo.pt. Se não fez este pedido, pode ignorá-la. "
        . "Se abrir o catálogo através desta mensagem, a data e hora do acesso ficam registadas e associadas ao seu pedido, para acompanhamento. "
        . "Os seus dados são tratados de acordo com a nossa política de privacidade: " . $d['site'] . "/privacidade/\n";
}

/* Email interno com o pedido */

function email_interno_html(array $d): string
{
    $f = FONTE;
    $linhas = '';
    $campos = [
        ['Tipo de pedido', h($d['tipo'])],
        ['Nome', h($d['nome'])],
        ['Empresa', h($d['empresa'])],
        ['Email', '<a href="mailto:' . h($d['email']) . '" style="color:' . COR_PETROLEO . ';">' . h($d['email']) . '</a>'],
        ['Telemóvel', '<a href="tel:' . h(preg_replace('/[^0-9+]/', '', $d['telemovel'])) . '" style="color:' . COR_PETROLEO . ';">' . h($d['telemovel']) . '</a>'],
        ['Serviços', implode('<br>', array_map('h', $d['servicos']))],
        ['Recebido em', h($d['data'])],
    ];
    foreach ($campos as $i => [$rotulo, $valor]) {
        $borda = $i > 0 ? 'border-top:1px solid ' . COR_LINHA . ';' : '';
        $linhas .= '<tr><td style="' . $borda . 'padding:10px 0;width:150px;vertical-align:top;font-family:' . $f . ';font-size:13px;color:' . COR_SUAVE . ';">' . $rotulo . '</td>'
            . '<td style="' . $borda . 'padding:10px 0;vertical-align:top;font-family:' . $f . ';font-size:14px;color:' . COR_MARINHO . ';">' . $valor . '</td></tr>';
    }
    $assunto_resposta = rawurlencode('Re: ' . $d['tipo'] . ' [' . $d['referencia'] . ']');

    return '<!doctype html><html lang="pt-PT"><head><meta charset="utf-8"><title>' . h($d['tipo']) . '</title></head>'
        . '<body style="margin:0;padding:0;background:' . COR_FUNDO . ';">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . COR_FUNDO . ';"><tr><td align="center" style="padding:24px 16px;">'
        . '<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:100%;max-width:640px;background:#FFFFFF;border:1px solid ' . COR_LINHA . ';">'
        . '<tr><td style="padding:24px 32px;background:' . COR_MARINHO . ';">'
        . '<p style="margin:0 0 4px;font-family:' . $f . ';font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8FC3C6;font-weight:700;">Novo pedido pelo website</p>'
        . '<p style="margin:0;font-family:' . $f . ';font-size:20px;color:#FFFFFF;font-weight:700;">' . h($d['tipo']) . ' · ' . h($d['referencia']) . '</p></td></tr>'
        . '<tr><td style="padding:24px 32px 8px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $linhas . '</table></td></tr>'
        . '<tr><td style="padding:16px 32px 8px;"><p style="margin:0 0 8px;font-family:' . $f . ';font-size:11px;letter-spacing:2px;text-transform:uppercase;color:' . COR_SUAVE . ';font-weight:700;">Mensagem</p>'
        . '<div style="padding:16px 18px;background:' . COR_FUNDO . ';font-family:' . $f . ';font-size:14px;line-height:1.6;color:' . COR_TEXTO . ';">' . nl2br(h($d['mensagem'])) . '</div></td></tr>'
        . '<tr><td style="padding:20px 32px 32px;"><a href="mailto:' . h($d['email']) . '?subject=' . $assunto_resposta . '" style="display:inline-block;padding:12px 20px;background:' . COR_PETROLEO . ';color:#FFFFFF;text-decoration:none;font-family:' . $f . ';font-size:14px;font-weight:700;border-radius:8px;">Responder ao pedido</a>'
        . '<p style="margin:16px 0 0;font-family:' . $f . ';font-size:12px;color:' . COR_SUAVE . ';">O remetente recebeu uma confirmação automática com a referência ' . h($d['referencia']) . ' e o catálogo de serviços. As aberturas do catálogo ficam registadas na <a href="' . h($d['registos']) . '" style="color:' . COR_PETROLEO . ';">área reservada</a>. Responder a este email envia a resposta diretamente para o remetente.</p></td></tr>'
        . '</table></td></tr></table></body></html>';
}

function email_interno_texto(array $d): string
{
    return "NOVO PEDIDO PELO WEBSITE\n"
        . $d['tipo'] . " · " . $d['referencia'] . "\n\n"
        . "Nome: " . $d['nome'] . "\n"
        . "Empresa: " . $d['empresa'] . "\n"
        . "Email: " . $d['email'] . "\n"
        . "Telemóvel: " . $d['telemovel'] . "\n"
        . "Serviços: " . implode('; ', $d['servicos']) . "\n"
        . "Recebido em: " . $d['data'] . "\n\n"
        . "MENSAGEM\n" . $d['mensagem'] . "\n\n"
        . "O remetente recebeu uma confirmação automática com o catálogo de serviços. Aberturas do catálogo: " . $d['registos'] . "\n"
        . "Responder a este email envia a resposta diretamente para o remetente.\n";
}

/* Página de erro para envios sem JavaScript */

function pagina_erro(string $mensagem, array $erros): string
{
    $lista = '';
    foreach ($erros as $texto) {
        $lista .= '<li>' . h($texto) . '</li>';
    }
    return '<!doctype html><html lang="pt-PT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>Pedido não enviado | Gadgetmetodo, Lda.</title>'
        . '<link rel="stylesheet" href="/assets/css/site.css"></head><body>'
        . '<main class="secao"><div class="contentor estreito">'
        . '<span class="sobretitulo">Pedido não enviado</span><h1>Falta corrigir alguns dados</h1>'
        . '<p class="lead">' . h($mensagem) . '</p>' . ($lista ? '<ul>' . $lista . '</ul>' : '')
        . '<div class="acoes"><a class="botao" href="javascript:history.back()">Voltar ao formulário</a>'
        . '<a class="botao botao-secundario" href="mailto:info@gadgetmetodo.pt">Escrever para info@gadgetmetodo.pt</a></div>'
        . '</div></main></body></html>';
}
