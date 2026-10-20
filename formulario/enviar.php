<?php
// Recebe o formulário de pedido, envia-o para a caixa da empresa e confirma a receção ao remetente.
// Responde em JSON quando o pedido vem do JavaScript e com redirecionamento quando vem de um envio normal.

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/lib/PHPMailer/Exception.php';
require __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require __DIR__ . '/lib/PHPMailer/SMTP.php';
require __DIR__ . '/modelos.php';

date_default_timezone_set('Europe/Lisbon');

const TIPOS = [
    'orcamento'   => 'Pedido de orçamento',
    'informacoes' => 'Pedido de informações',
];
const SERVICOS = [
    'organizacao-e-metodos'            => 'Organização e Métodos',
    'inteligencia-artificial'          => 'Inteligência artificial e transformação digital',
    'protecao-de-dados-e-conformidade' => 'Proteção de dados e conformidade',
    'estrategia-e-gestao'              => 'Estratégia e gestão',
    'formacao-ai-essentials'           => 'Formação AI Essentials',
    'outro'                            => 'Outro assunto ou ainda não sei',
];
const LIMITE_POR_HORA = 5;
const TEMPO_MINIMO = 3; // segundos entre abrir a página e enviar
const ERRO_GERAL = 'Não foi possível enviar o pedido. Tente novamente ou escreva para info@gadgetmetodo.pt.';

$quer_json = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function responder(bool $ok, int $codigo, array $dados, bool $json): void
{
    http_response_code($codigo);
    header('Cache-Control: no-store');
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok] + $dados, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($ok) {
        header('Location: /contactos/obrigado/', true, 303);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo pagina_erro($dados['mensagem'] ?? ERRO_GERAL, $dados['erros'] ?? []);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responder(false, 405, ['mensagem' => 'Método não permitido.'], true);
}

$ficheiro_config = __DIR__ . '/config.php';
if (!is_file($ficheiro_config)) {
    error_log('Formulário: falta o ficheiro config.php');
    responder(false, 503, ['mensagem' => 'O formulário está temporariamente indisponível. Escreva para info@gadgetmetodo.pt.'], $quer_json);
}
$cfg = require $ficheiro_config;

// Só aceita envios a partir do próprio site
$origem = rtrim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
if ($origem !== '' && !in_array($origem, $cfg['origens'] ?? [], true)) {
    responder(false, 403, ['mensagem' => ERRO_GERAL], $quer_json);
}

/* Leitura e limpeza dos campos */

function cortar(string $texto, int $maximo): string
{
    return function_exists('mb_substr') ? mb_substr($texto, 0, $maximo, 'UTF-8') : substr($texto, 0, $maximo);
}

function comprimento(string $texto): int
{
    return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
}

function linha(string $chave, int $maximo): string
{
    $valor = (string) ($_POST[$chave] ?? '');
    if (!preg_match('//u', $valor)) {
        return '';
    }
    $valor = preg_replace('/\p{C}+/u', ' ', $valor);
    return trim(preg_replace('/\s+/u', ' ', cortar($valor, $maximo)));
}

function texto_longo(string $chave, int $maximo): string
{
    $valor = str_replace("\r\n", "\n", (string) ($_POST[$chave] ?? ''));
    if (!preg_match('//u', $valor)) {
        return '';
    }
    $valor = preg_replace('/[^\P{C}\n\t]+/u', '', $valor);
    return trim(cortar($valor, $maximo));
}

$tipo      = (string) ($_POST['tipo'] ?? '');
$nome      = linha('nome', 100);
$empresa   = linha('empresa', 120);
$email     = linha('email', 160);
$telemovel = linha('telemovel', 20);
$mensagem  = texto_longo('mensagem', 5000);
$servicos  = array_values(array_intersect(array_keys(SERVICOS), (array) ($_POST['servicos'] ?? [])));
$consentimento = ($_POST['consentimento'] ?? '') === 'sim';

// Proteção contra envios automáticos: campo isca preenchido ou envio demasiado rápido
$isca = trim((string) ($_POST['website'] ?? ''));
$aberto_em = (int) ($_POST['t'] ?? 0);
if ($isca !== '' || ($aberto_em > 0 && time() - $aberto_em < TEMPO_MINIMO)) {
    responder(true, 200, [], $quer_json);
}

$erros = [];
if (!isset(TIPOS[$tipo])) {
    $erros['tipo'] = 'Escolha o tipo de pedido.';
}
if (comprimento($nome) < 2) {
    $erros['nome'] = 'Indique o seu nome.';
}
if (comprimento($empresa) < 2) {
    $erros['empresa'] = 'Indique o nome da empresa.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erros['email'] = 'Indique um email válido.';
}
if (!preg_match('/^[0-9 +()\-]{9,20}$/', $telemovel) || preg_match_all('/\d/', $telemovel) < 9) {
    $erros['telemovel'] = 'Indique um número de telemóvel válido.';
}
if (!$servicos) {
    $erros['servicos'] = 'Selecione pelo menos um serviço.';
}
if (comprimento($mensagem) < 10) {
    $erros['mensagem'] = 'Descreva o pedido com pelo menos 10 caracteres.';
}
if (!$consentimento) {
    $erros['consentimento'] = 'É necessário aceitar para podermos responder.';
}
if ($erros) {
    responder(false, 422, ['mensagem' => 'Reveja os campos assinalados.', 'erros' => $erros], $quer_json);
}

/* Limite de envios por origem (código derivado do IP, guardado durante uma hora) */

function excede_limite(string $ip): bool
{
    $pasta = sys_get_temp_dir();
    $agora = time();
    foreach (glob($pasta . '/gm-formulario-*.json') ?: [] as $antigo) {
        if (@filemtime($antigo) < $agora - 3600) {
            @unlink($antigo);
        }
    }
    $ficheiro = $pasta . '/gm-formulario-' . hash('sha256', 'gadgetmetodo|' . $ip) . '.json';
    $registos = is_readable($ficheiro) ? json_decode((string) @file_get_contents($ficheiro), true) : [];
    $registos = is_array($registos) ? $registos : [];
    $registos = array_values(array_filter($registos, function ($t) use ($agora) {
        return is_int($t) && $t > $agora - 3600;
    }));
    if (count($registos) >= LIMITE_POR_HORA) {
        return true;
    }
    $registos[] = $agora;
    @file_put_contents($ficheiro, json_encode($registos), LOCK_EX);
    return false;
}

if (excede_limite((string) ($_SERVER['REMOTE_ADDR'] ?? ''))) {
    responder(false, 429, ['mensagem' => 'Recebemos vários pedidos seguidos a partir desta ligação. Tente mais tarde ou escreva para info@gadgetmetodo.pt.'], $quer_json);
}

/* Envio */

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

$referencia = 'GM-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
$dados = [
    'referencia' => $referencia,
    'tipo'       => TIPOS[$tipo],
    'nome'       => $nome,
    'empresa'    => $empresa,
    'email'      => $email,
    'telemovel'  => $telemovel,
    'servicos'   => array_map(function ($s) { return SERVICOS[$s]; }, $servicos),
    'mensagem'   => $mensagem,
    'data'       => date('d/m/Y, H:i'),
    'site'       => rtrim((string) ($cfg['site'] ?? 'https://www.gadgetmetodo.pt'), '/'),
];

// 1. Pedido para a caixa da empresa, com cópia oculta e resposta direta ao remetente
try {
    $interno = novo_email($cfg);
    $interno->addAddress($cfg['destinatario']);
    if (!empty($cfg['bcc'])) {
        $interno->addBCC($cfg['bcc']);
    }
    $interno->addReplyTo($email, $nome);
    $interno->Subject = sprintf('%s: %s (%s) [%s]', $dados['tipo'], $empresa, $nome, $referencia);
    $interno->isHTML(true);
    $interno->Body = email_interno_html($dados);
    $interno->AltBody = email_interno_texto($dados);
    $interno->send();
} catch (PHPMailerException $e) {
    error_log('Formulário: falha no envio do pedido ' . $referencia . ': ' . $e->getMessage());
    responder(false, 502, ['mensagem' => ERRO_GERAL], $quer_json);
}

// 2. Confirmação ao remetente; uma falha aqui não invalida o pedido já entregue
try {
    $confirmacao = novo_email($cfg);
    $confirmacao->addAddress($email, nome_saudacao($nome));
    $confirmacao->addReplyTo($cfg['remetente_email'], $cfg['remetente_nome']);
    $confirmacao->addCustomHeader('Auto-Submitted', 'auto-generated');
    $confirmacao->Subject = 'Confirmação de receção do seu pedido · Ref. ' . $referencia;
    $confirmacao->isHTML(true);
    $confirmacao->Body = email_confirmacao_html($dados);
    $confirmacao->AltBody = email_confirmacao_texto($dados);
    $confirmacao->send();
} catch (PHPMailerException $e) {
    error_log('Formulário: falha na confirmação do pedido ' . $referencia . ': ' . $e->getMessage());
}

responder(true, 200, ['referencia' => $referencia], $quer_json);
