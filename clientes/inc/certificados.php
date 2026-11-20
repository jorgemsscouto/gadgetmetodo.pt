<?php
// Certificados de frequência: ficha do formando para o certificado (nome completo e número e validade do Cartão de
// Cidadão, preenchidos na área do formando ou pela administração), pedido dos dados por email, emissão com número
// sequencial por ano ("001/2026"), PDF no modelo da Gadgetmetodo (A4 horizontal, com o painel do programa à esquerda)
// e envio por email com o PDF em anexo. O formando descarrega os seus certificados na área do formando.

declare(strict_types=1);

const CERTIFICADO_LOCAL = 'Porto';
const CERTIFICADO_FORMADOR = 'Jorge Manuel de Sá Silva Couto';
const CERTIFICADO_FORMADOR_DADOS = 'Formador  |  CCP n.º F625978/2014 (IEFP)';
const CERTIFICADO_ENTIDADE = 'Gadgetmetodo, Lda.';

// Utilizadores que podem ser formadores de uma edição (ativos, perfis Gadgetmetodo e Formador), por id
function formadores(): array
{
    static $lista = null;
    if ($lista === null) {
        $lista = [];
        foreach (consulta("SELECT id, nome, ccp FROM utilizadores WHERE ativo = 1 AND papel IN ('admin', 'formador') ORDER BY nome COLLATE NOCASE") as $u) {
            $lista[(int) $u['id']] = $u;
        }
    }
    return $lista;
}

// [nome, linha "Formador | CCP n.º …"] do formador da edição; sem formador indicado, os de CERTIFICADO_FORMADOR*
function formador_certificado(int $formacao_id): array
{
    $u = linha_bd('SELECT u.nome, u.ccp FROM formacoes f JOIN utilizadores u ON u.id = f.formador_id WHERE f.id = ?', [$formacao_id]);
    if (!$u) {
        return [CERTIFICADO_FORMADOR, CERTIFICADO_FORMADOR_DADOS];
    }
    return [(string) $u['nome'], 'Formador' . (trim((string) $u['ccp']) !== '' ? '  |  CCP n.º ' . trim((string) $u['ccp']) : '')];
}

/* Ficha do formando para o certificado */

function ficha_certificado(string $email): ?array
{
    return trim($email) === '' ? null : linha_bd('SELECT * FROM fichas_certificado WHERE ' . sprintf(SQL_EMAIL_IGUAL, 'email'), [$email]);
}

// Ficha completa: todos os campos obrigatórios (Configuração, Certificados) preenchidos
function ficha_completa(?array $f): bool
{
    if (!$f || trim((string) $f['nome_completo']) === '') {
        return false;
    }
    foreach (config_campos_certificado() as $k => $estado) {
        if ($estado !== 'obrigatorio' || $k === 'nome_completo') {
            continue;
        }
        $valores = $k === 'documento' ? [$f['documento_tipo'] ?? 'cc', $f['cc_numero'] ?? '', $f['cc_validade'] ?? ''] : [$f[$k] ?? ''];
        foreach ($valores as $v) {
            if (trim((string) $v) === '') {
                return false;
            }
        }
    }
    return true;
}

// Campos da ficha em falta (rótulos), para mostrar ao gestor e ao formando
function campos_em_falta(?array $f): array
{
    $r = [];
    foreach (config_campos_certificado() as $k => $estado) {
        if ($estado !== 'obrigatorio') {
            continue;
        }
        $valores = !$f ? [''] : ($k === 'documento' ? [$f['cc_numero'] ?? '', $f['cc_validade'] ?? ''] : [$f[$k] ?? '']);
        if (array_filter($valores, function ($v) { return trim((string) $v) === ''; })) {
            $r[] = CAMPOS_CERTIFICADO[$k]['rotulo'];
        }
    }
    return $r;
}

// Número do documento sem espaços nem separadores, em maiúsculas
function numero_cc_normalizado(string $s): string
{
    return strtoupper((string) preg_replace('/[\s.\-\/]+/', '', $s));
}

// Aceita o número de identificação civil (8 algarismos, com ou sem o algarismo de controlo) ou o número completo do
// documento (12 carateres, como 12345678 9 ZZ1), validado pelo algarismo de controlo
function numero_cc_valido(string $n): bool
{
    if (preg_match('/^\d{8,9}$/', $n)) {
        return true;
    }
    if (!preg_match('/^\d{9}[A-Z0-9]{2}\d$/', $n)) {
        return false;
    }
    $soma = 0;
    $dobrar = false;
    for ($k = strlen($n) - 1; $k >= 0; $k--) {
        $c = $n[$k];
        $v = ctype_digit($c) ? (int) $c : ord($c) - 55;
        if ($dobrar) {
            $v *= 2;
            $v -= $v > 9 ? 9 : 0;
        }
        $soma += $v;
        $dobrar = !$dobrar;
    }
    return $soma % 10 === 0;
}

// "12345678 9 ZZ1" (número completo do documento) ou "12345678 9"
function numero_cc_formatado(string $n): string
{
    if (strlen($n) === 12) {
        return substr($n, 0, 8) . ' ' . $n[8] . ' ' . substr($n, 9);
    }
    return strlen($n) === 9 ? substr($n, 0, 8) . ' ' . $n[8] : $n;
}

function data_curta(string $data): string
{
    return date('d/m/Y', strtotime($data));
}

// NIF português válido (9 algarismos, com o algarismo de controlo)
function nif_valido(string $nif): bool
{
    if (!preg_match('/^[1-35-9]\d{8}$/', $nif)) {
        return false;
    }
    $soma = 0;
    for ($k = 0; $k < 8; $k++) {
        $soma += (int) $nif[$k] * (9 - $k);
    }
    $controlo = 11 - $soma % 11;
    return ($controlo >= 10 ? 0 : $controlo) === (int) $nif[8];
}

function data_valida(string $d): bool
{
    $t = preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? strtotime($d) : false;
    return $t && date('Y-m-d', $t) === $d;
}

// Dados da ficha a partir do formulário, só com os campos ligados em Configuração: [dados, erros]
function ler_ficha_certificado(): array
{
    $config = config_campos_certificado();
    $d = ['nome_completo' => trim((string) preg_replace('/\s+/u', ' ', texto('nome_completo', 160))), 'documento_tipo' => 'cc', 'cc_numero' => '', 'cc_validade' => '',
        'data_nascimento' => null, 'nif' => null, 'naturalidade' => null, 'nacionalidade' => null];
    $erros = [];
    if (count(explode(' ', $d['nome_completo'])) < 2 || mb_strlen($d['nome_completo']) < 5) {
        $erros['nome_completo'] = 'Indique o nome completo, tal como consta do documento de identificação.';
    }
    $obrigatorio = function (string $k) use ($config) { return ($config[$k] ?? '') === 'obrigatorio'; };
    if ($config['documento'] !== 'desligado') {
        $d['documento_tipo'] = isset(TIPOS_DOCUMENTO[$_POST['documento_tipo'] ?? '']) ? $_POST['documento_tipo'] : 'cc';
        $d['cc_numero'] = numero_cc_normalizado(texto('cc_numero', 30));
        $d['cc_validade'] = texto('cc_validade', 10);
        $nome_doc = TIPOS_DOCUMENTO[$d['documento_tipo']];
        $preenchido = $d['cc_numero'] !== '' || $d['cc_validade'] !== '';
        if ($obrigatorio('documento') || $preenchido) {
            if ($d['documento_tipo'] === 'cc' && !numero_cc_valido($d['cc_numero'])) {
                $erros['cc_numero'] = 'Confirme o número do Cartão de Cidadão: indique os 8 algarismos do número de identificação civil ou o número completo do documento (por exemplo, 12345678 9 ZZ1).';
            } elseif ($d['documento_tipo'] !== 'cc' && !preg_match('/^[A-Z0-9]{5,20}$/', $d['cc_numero'])) {
                $erros['cc_numero'] = 'Indique o número do ' . mb_strtolower($nome_doc) . ' (5 a 20 letras ou algarismos).';
            }
            if (!data_valida($d['cc_validade'])) {
                $erros['cc_validade'] = 'Indique a data de validade do documento.';
            } elseif ($d['cc_validade'] < date('Y-m-d')) {
                $erros['cc_validade'] = 'O documento indicado já expirou. Indique os dados de um documento válido.';
            } elseif (strtotime($d['cc_validade']) > strtotime('+15 years')) {
                $erros['cc_validade'] = 'Confirme a data de validade do documento.';
            }
        }
    }
    if ($config['data_nascimento'] !== 'desligado') {
        $d['data_nascimento'] = texto('data_nascimento', 10) ?: null;
        if (($obrigatorio('data_nascimento') || $d['data_nascimento']) && (!data_valida((string) $d['data_nascimento']) || $d['data_nascimento'] >= date('Y-m-d', strtotime('-14 years')))) {
            $erros['data_nascimento'] = 'Indique a data de nascimento.';
        }
    }
    if ($config['nif'] !== 'desligado') {
        $d['nif'] = preg_replace('/\D/', '', texto('nif', 20)) ?: null;
        if (($obrigatorio('nif') || $d['nif']) && !nif_valido((string) $d['nif'])) {
            $erros['nif'] = 'Confirme o NIF (9 algarismos).';
        }
    }
    foreach (['naturalidade', 'nacionalidade'] as $k) {
        if ($config[$k] !== 'desligado') {
            $d[$k] = texto($k, 80) ?: null;
            if ($obrigatorio($k) && !$d[$k]) {
                $erros[$k] = 'Indique a ' . $k . '.';
            }
        }
    }
    return [$d, $erros];
}

function gravar_ficha_certificado(string $email, array $d, int $por): void
{
    $valores = [$d['nome_completo'], $d['documento_tipo'] ?? 'cc', $d['cc_numero'] ?? '', $d['cc_validade'] ?? '', $d['data_nascimento'] ?? null, $d['nif'] ?? null,
        $d['naturalidade'] ?? null, $d['nacionalidade'] ?? null, agora(), $por];
    $atual = ficha_certificado($email);
    if ($atual) {
        executar('UPDATE fichas_certificado SET nome_completo = ?, documento_tipo = ?, cc_numero = ?, cc_validade = ?, data_nascimento = ?, nif = ?, naturalidade = ?, nacionalidade = ?,
            atualizado = ?, atualizado_por = ? WHERE id = ?', array_merge($valores, [$atual['id']]));
    } else {
        executar('INSERT INTO fichas_certificado (email, nome_completo, documento_tipo, cc_numero, cc_validade, data_nascimento, nif, naturalidade, nacionalidade, atualizado, atualizado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge([trim($email)], $valores));
    }
}

// Campos da ficha, só os ligados em Configuração (Certificados), com os erros de cada campo
function campos_ficha_certificado(array $v, array $erros): string
{
    $config = config_campos_certificado();
    $erro = function (string $c) use ($erros) {
        return isset($erros[$c]) ? '<p class="erro-campo">' . h($erros[$c]) . '</p>' : '';
    };
    $marca = function (string $k) use ($config) { return $config[$k] === 'obrigatorio' ? ' *' : ''; };
    $req = function (string $k) use ($config) { return $config[$k] === 'obrigatorio' ? 'required ' : ''; };
    $html = '<div>' . campo('nome_completo', 'Nome completo *', (string) ($v['nome_completo'] ?? ''), 'text', 'required maxlength="160" autocomplete="name"') . $erro('nome_completo') . '</div>';
    if ($config['documento'] !== 'desligado') {
        $html .= '<div>' . campo_select('documento_tipo', 'Documento de identificação' . $marca('documento'), TIPOS_DOCUMENTO, (string) ($v['documento_tipo'] ?? 'cc')) . '</div>'
            . '<div>' . campo('cc_numero', 'N.º do documento' . $marca('documento'), numero_cc_formatado((string) ($v['cc_numero'] ?? '')), 'text', $req('documento') . 'maxlength="20" autocomplete="off" placeholder="12345678 9 ZZ1"') . $erro('cc_numero') . '</div>'
            . '<div>' . campo('cc_validade', 'Válido até' . $marca('documento'), (string) ($v['cc_validade'] ?? ''), 'date', $req('documento') . 'min="' . date('Y-m-d') . '"') . $erro('cc_validade') . '</div>';
    }
    if ($config['data_nascimento'] !== 'desligado') {
        $html .= '<div>' . campo('data_nascimento', 'Data de nascimento' . $marca('data_nascimento'), (string) ($v['data_nascimento'] ?? ''), 'date', $req('data_nascimento') . 'max="' . date('Y-m-d') . '" autocomplete="bday"') . $erro('data_nascimento') . '</div>';
    }
    if ($config['nif'] !== 'desligado') {
        $html .= '<div>' . campo('nif', 'NIF' . $marca('nif'), (string) ($v['nif'] ?? ''), 'text', $req('nif') . 'inputmode="numeric" maxlength="11"') . $erro('nif') . '</div>';
    }
    foreach (['naturalidade' => 'Naturalidade', 'nacionalidade' => 'Nacionalidade'] as $k => $rotulo) {
        if ($config[$k] !== 'desligado') {
            $html .= '<div>' . campo($k, $rotulo . $marca($k), (string) ($v[$k] ?? ''), 'text', $req($k) . 'maxlength="80"') . $erro($k) . '</div>';
        }
    }
    return '<div class="campos-2">' . $html . '</div>';
}

// "Cartão de Cidadão n.º 12345678 9 ZZ1, válido até 20/05/2031" (ou passaporte, título de residência)
function linha_documento(array $f): string
{
    $tipo = TIPOS_DOCUMENTO[$f['documento_tipo'] ?? 'cc'] ?? TIPOS_DOCUMENTO['cc'];
    return trim((string) ($f['cc_numero'] ?? '')) === '' ? '' : $tipo . ' n.º ' . (($f['documento_tipo'] ?? 'cc') === 'cc' ? numero_cc_formatado((string) $f['cc_numero']) : $f['cc_numero'])
        . ($f['cc_validade'] ? ', válido até ' . data_curta((string) $f['cc_validade']) : '');
}

/* Contexto do certificado de uma inscrição */

// Curso, programa, percurso, sessões e presenças de uma inscrição (linha de SQL_INSCRICAO)
function contexto_certificado(array $i): array
{
    $sessoes = sessoes_formacao_cache((int) $i['formacao_id']);
    $curso = !empty($i['curso_id']) ? linha_bd('SELECT * FROM cursos WHERE id = ?', [(int) $i['curso_id']]) : null;
    // Título do curso no certificado: sem "Curso N:" nem o formato no fim ("(6 sessões)", "(3 × 2 h)"), que o certificado já indica
    $nome = trim((string) preg_replace(['/^Curso\s+\d+\s*[:.\x{2013}-]\s*/u', '/\s*\((\d+ sess(ão|ões)|\d+ × [0-9,]+ ?h|formato [0-9,]+ ?h)\)\s*$/u'], '', (string) ($curso['nome'] ?? $i['titulo'])));
    $posicao = '';
    if ($curso && preg_match('/Curso\s+(\d+)\s+de\s+(\d+)/u', (string) $curso['descricao'], $m)) {
        $posicao = 'Curso ' . $m[1] . ' de ' . $m[2];
    }
    $ultima = $sessoes ? $sessoes[count($sessoes) - 1] : null;
    $fim = $ultima ? strtotime($ultima['fim'] !== '' ? $ultima['fim'] : $ultima['inicio']) : 0;
    return [
        'curso' => $nome,
        'programa' => $curso ? array_column(consulta('SELECT nome FROM modulos WHERE curso_id = ? ORDER BY ordem, id', [(int) $curso['id']]), 'nome') : [],
        'percurso' => trim((string) ($curso['percurso'] ?? '')),
        'posicao' => $posicao,
        'sessoes' => count($sessoes),
        'inicio' => $sessoes ? $sessoes[0]['inicio'] : (string) $i['inicio'],
        'fim' => $ultima ? $ultima['inicio'] : (string) $i['inicio'],
        'terminada' => $fim > 0 && $fim <= time(),
        'presencas' => count(presencas_inscricao((int) $i['id'])),
    ];
}

function certificado_da_inscricao(int $inscricao_id): ?array
{
    return linha_bd('SELECT * FROM certificados WHERE inscricao_id = ?', [$inscricao_id]);
}

function numero_certificado(array $c): string
{
    return sprintf('%03d/%d', (int) $c['numero'], (int) $c['ano']);
}

// Emite (ou volta a emitir, com o mesmo número) o certificado de uma inscrição com os dados atuais da ficha e das presenças
function emitir_certificado(array $i, array $ficha): array
{
    $ctx = contexto_certificado($i);
    $dados = array_merge([$ficha['nome_completo'], (string) $ficha['cc_numero'], (string) $ficha['cc_validade'], $ctx['presencas'], $ctx['sessoes']], formador_certificado((int) $i['formacao_id']), [$ficha['documento_tipo'] ?? 'cc']);
    $atual = certificado_da_inscricao((int) $i['id']);
    if ($atual) {
        if (!empty($atual['assinado_ficheiro'])) {
            apagar_assinado($atual);
        }
        executar('UPDATE certificados SET emitido = ?, nome = ?, cc_numero = ?, cc_validade = ?, presencas = ?, sessoes = ?, formador = ?, formador_dados = ?, documento_tipo = ? WHERE id = ?', array_merge([agora()], $dados, [$atual['id']]));
    } else {
        $pdo = bd();
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $ano = (int) date('Y');
            $n = (int) (linha_bd('SELECT COALESCE(MAX(numero), 0) + 1 AS n FROM certificados WHERE ano = ?', [$ano])['n'] ?? 1);
            executar('INSERT INTO certificados (inscricao_id, ano, numero, emitido, nome, cc_numero, cc_validade, presencas, sessoes, formador, formador_dados, documento_tipo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array_merge([(int) $i['id'], $ano, $n, agora()], $dados));
            $pdo->exec('COMMIT');
        } catch (Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }
    }
    return certificado_da_inscricao((int) $i['id']);
}

function nome_ficheiro_certificado(array $c): string
{
    $nome = iconv('UTF-8', 'ASCII//TRANSLIT', (string) $c['nome']) ?: 'formando';
    $nome = trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $nome), '_');
    return 'Certificado_' . sprintf('%03d-%d', (int) $c['numero'], (int) $c['ano']) . '_' . $nome . '.pdf';
}

/* Versão assinada digitalmente: o PDF é descarregado, assinado fora do site (Cartão de Cidadão, Chave Móvel Digital)
   e carregado na turma; fica na pasta de dados e passa a ser o que se envia e descarrega */

const TAMANHO_MAX_ASSINADO = 10 * 1024 * 1024;

function pasta_assinados(): string
{
    $pasta = pasta_dados(cfg());
    if (!$pasta) {
        throw new RuntimeException('Sem pasta de dados para os certificados assinados.');
    }
    $dir = $pasta . '/certificados-assinados';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    return $dir;
}

// Conteúdo do PDF assinado (null se não houver)
function pdf_assinado(array $c): ?string
{
    if (empty($c['assinado_ficheiro'])) {
        return null;
    }
    $f = pasta_assinados() . '/' . basename((string) $c['assinado_ficheiro']);
    $conteudo = is_file($f) ? file_get_contents($f) : false;
    return $conteudo === false ? null : $conteudo;
}

function apagar_assinado(array $c): void
{
    if (!empty($c['assinado_ficheiro'])) {
        @unlink(pasta_assinados() . '/' . basename((string) $c['assinado_ficheiro']));
    }
    executar('UPDATE certificados SET assinado = NULL, assinado_ficheiro = NULL, assinado_por = NULL WHERE id = ?', [(int) $c['id']]);
}

// Um PDF assinado digitalmente tem um campo de assinatura (/Sig) com o intervalo assinado (/ByteRange)
function tem_assinatura_digital(string $pdf): bool
{
    return str_contains($pdf, '/ByteRange') && str_contains($pdf, '/Sig');
}

// Número do certificado no nome do ficheiro ("Certificado_002-2026_Nome.pdf", também com sufixos como "_assinado")
function numero_no_ficheiro(string $nome): ?array
{
    return preg_match('/(?<!\d)(\d{1,4})[-_](20\d{2})(?!\d)/', $nome, $m) ? [(int) $m[1], (int) $m[2]] : null;
}

// Descarga dos PDF por assinar: um só PDF, ou um ZIP com vários. $pares: [[certificado, inscrição], ...]
function descarregar_para_assinar(array $pares, string $nome): void
{
    if (count($pares) === 1) {
        [$c, $i] = $pares[0];
        $conteudo = pdf_certificado($c, $i);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . nome_ficheiro_certificado($c) . '"');
        header('Content-Length: ' . strlen($conteudo));
        echo $conteudo;
        exit;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'cert');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Não foi possível criar o ZIP.');
    }
    foreach ($pares as [$c, $i]) {
        $zip->addFromString(nome_ficheiro_certificado($c), pdf_certificado($c, $i));
    }
    $zip->close();
    $nome = trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $nome) ?: 'turma'), '_');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="Certificados_para_assinar_' . $nome . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

// Recebe os PDF assinados de uma turma (campo "assinados[]"); cada ficheiro é ligado ao certificado pelo número no nome
function receber_assinados(array $f): void
{
    $ficheiros = [];
    $up = $_FILES['assinados'] ?? null;
    if ($up && is_array($up['name'])) {
        foreach ($up['name'] as $k => $nome) {
            $ficheiros[] = ['nome' => (string) $nome, 'tmp' => (string) $up['tmp_name'][$k], 'erro' => (int) $up['error'][$k], 'tamanho' => (int) $up['size'][$k]];
        }
    }
    $ficheiros = array_filter($ficheiros, fn ($x) => $x['erro'] !== UPLOAD_ERR_NO_FILE);
    if (!$ficheiros) {
        aviso('Escolha os PDF assinados.', 'erro');
        return;
    }
    $ok = [];
    $erros = [];
    foreach ($ficheiros as $x) {
        $nome = $x['nome'];
        if ($x['erro'] !== UPLOAD_ERR_OK || !is_uploaded_file($x['tmp'])) {
            $erros[] = $nome . ' (não foi recebido' . (in_array($x['erro'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? ': demasiado grande' : '') . ')';
            continue;
        }
        if ($x['tamanho'] > TAMANHO_MAX_ASSINADO) {
            $erros[] = $nome . ' (mais de 10 MB)';
            continue;
        }
        $conteudo = (string) file_get_contents($x['tmp']);
        if (!str_starts_with($conteudo, '%PDF')) {
            $erros[] = $nome . ' (não é um PDF)';
            continue;
        }
        $n = numero_no_ficheiro($nome);
        $c = $n ? linha_bd('SELECT c.* FROM certificados c JOIN inscricoes i ON i.id = c.inscricao_id WHERE c.numero = ? AND c.ano = ? AND i.formacao_id = ?', [$n[0], $n[1], (int) $f['id']]) : null;
        if (!$c) {
            $erros[] = $nome . ' (' . ($n ? 'o certificado n.º ' . sprintf('%03d/%d', $n[0], $n[1]) . ' não é desta turma' : 'sem o número do certificado no nome do ficheiro') . ')';
            continue;
        }
        if (!tem_assinatura_digital($conteudo)) {
            $erros[] = $nome . ' (não tem assinatura digital)';
            continue;
        }
        $destino = sprintf('%d-%03d-%d-%s.pdf', (int) $c['id'], (int) $c['numero'], (int) $c['ano'], bin2hex(random_bytes(6)));
        if (file_put_contents(pasta_assinados() . '/' . $destino, $conteudo) === false) {
            $erros[] = $nome . ' (não foi possível guardar)';
            continue;
        }
        if (!empty($c['assinado_ficheiro'])) {
            @unlink(pasta_assinados() . '/' . basename((string) $c['assinado_ficheiro']));
        }
        executar('UPDATE certificados SET assinado = ?, assinado_ficheiro = ?, assinado_por = ? WHERE id = ?', [agora(), $destino, (int) (utilizador()['id'] ?? 0), (int) $c['id']]);
        $ok[] = numero_certificado($c);
    }
    $partes = array_filter([
        $ok ? count($ok) . ' certificado(s) assinado(s) guardado(s): n.º ' . implode(', ', $ok) . '.' : 'Nenhum certificado assinado guardado.',
        $erros ? 'Não guardados: ' . implode('; ', $erros) . '.' : '',
    ]);
    aviso(h(implode(' ', $partes)), $erros ? ($ok ? 'info' : 'erro') : 'ok');
}

/* PDF (clientes/inc/certificado-pdf.php, carregado só quando é preciso) */

// PDF do certificado (conteúdo). $c: linha de certificados; $i: linha de SQL_INSCRICAO.
// O certificado sai na língua do formando (a da conta ou a da inscrição feita no site em inglês)
function pdf_certificado(array $c, array $i): string
{
    require_once __DIR__ . '/certificado-pdf.php';
    return com_idioma(idioma_do_email((string) $i['email']), function () use ($c, $i) {
        return gerar_pdf_certificado($c, contexto_certificado($i));
    });
}

// Envia o PDF para o navegador (descarga)
// PDF a enviar e a descarregar: o assinado digitalmente, se já foi carregado; senão o gerado
function conteudo_certificado(array $c, array $i): string
{
    return pdf_assinado($c) ?? pdf_certificado($c, $i);
}

function descarregar_certificado(array $c, array $i): void
{
    $conteudo = conteudo_certificado($c, $i);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . nome_ficheiro_certificado($c) . '"');
    header('Content-Length: ' . strlen($conteudo));
    echo $conteudo;
    exit;
}

/* Emails */

function variaveis_certificado(array $i, string $nome = ''): array
{
    return ['nome' => nome_curto($nome !== '' ? $nome : (string) $i['formando']), 'formando' => $nome !== '' ? $nome : $i['formando'], 'formacao' => $i['titulo'], 'horario' => horario($i)];
}

// Pede ao formando, por email, que complete a ficha na área do formando (cria o acesso, se ainda não o tiver)
function pedir_dados_certificado(array $i): bool
{
    $email = trim((string) $i['email']);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    garantir_acesso_formando($i);
    $u = utilizador_por_email($email);
    $link = endereco_site() . '/clientes/area.php?' . http_build_query(['p' => ($u['papel'] ?? '') === 'cliente' ? 'presencas' : 'painel']) . '#certificado';
    $ok = enviar_mensagem('dados_certificado', $email, variaveis_certificado($i), [
        '<p style="margin:0 0 4px;"><a href="' . h($link) . '" style="display:inline-block;padding:13px 26px;background:#0A0F26;color:#FFFFFF;text-decoration:none;font-weight:700;border-radius:999px;">Completar a ficha</a></p>',
        'Completar a ficha: ' . $link,
    ]);
    if ($ok) {
        executar('UPDATE inscricoes SET dados_pedidos = ? WHERE id = ?', [agora(), (int) $i['id']]);
    }
    return $ok;
}

function enviar_certificado(array $c, array $i): bool
{
    $email = trim((string) $i['email']);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $ok = enviar_mensagem('certificado', $email, variaveis_certificado($i, (string) $c['nome']), [
        '<p style="margin:0 0 14px;"><strong>Certificado n.º:</strong> ' . h(numero_certificado($c)) . '</p>',
        'Certificado n.º ' . numero_certificado($c),
    ], ['anexos' => [[nome_ficheiro_certificado($c), conteudo_certificado($c, $i), 'application/pdf']]]);
    if ($ok) {
        executar('UPDATE certificados SET enviado = ? WHERE id = ?', [agora(), (int) $c['id']]);
    }
    return $ok;
}

/* Área do formando: ficha e certificados */

// Trata o envio da ficha na área do formando (devolve os erros, ou redireciona depois de gravar)
function tratar_ficha_formando(array $u): array
{
    [$d, $erros] = ler_ficha_certificado();
    if ($erros) {
        return [$d, $erros];
    }
    gravar_ficha_certificado((string) $u['email'], $d, (int) $u['id']);
    aviso('Dados para o certificado guardados. Obrigado.');
    redirecionar(rota_formando(), [], 'certificado');
}

// Bloco da área do formando: ficha para o certificado e certificados emitidos
function bloco_certificado_formando(array $u, array $inscricoes, array $valores = [], array $erros = []): string
{
    if (!$inscricoes) {
        return '';
    }
    $ficha = ficha_certificado((string) $u['email']);
    $v = $valores ?: ($ficha ?: ['nome_completo' => (string) $u['nome']]);
    $completa = ficha_completa($ficha);
    $emitidos = '';
    foreach ($inscricoes as $i) {
        $c = certificado_da_inscricao((int) $i['id']);
        if ($c) {
            $emitidos .= '<li><a href="' . h(url('certificado', ['i' => $i['id']])) . '">' . h($i['titulo']) . '</a> <span class="texto-suave">· n.º ' . h(numero_certificado($c)) . ', emitido a ' . h(data_hora($c['emitido'], false)) . '</span></li>';
        }
    }
    $aberta = !$completa || $erros;
    return '<section class="bloco-formando" id="certificado">
      <h2>Ficha para o certificado ' . ($completa ? etiqueta('Completa', 'ok') : etiqueta('Por completar', 'info')) . '</h2>
      ' . ($emitidos !== '' ? '<p><strong>Certificados emitidos</strong></p><ul class="lista-datas">' . $emitidos . '</ul>' : '') . '
      <details' . ($aberta ? ' open' : '') . '><summary>' . ($completa ? 'Ver ou corrigir os dados (' . h($ficha['nome_completo']) . ', CC n.º ' . h(numero_cc_formatado((string) $ficha['cc_numero'])) . ', válido até ' . h(data_curta((string) $ficha['cc_validade'])) . ')' : 'Preencher os dados para o certificado') . '</summary>
        <p class="texto-suave">Indique o nome completo e os dados do documento de identificação, tal como devem constar do certificado de frequência. Estes dados servem apenas para emitir os seus certificados.</p>
        <form method="post" action="' . h(url(rota_formando())) . '#certificado" class="formulario">' . campo_csrf() . '<input type="hidden" name="acao" value="ficha_certificado">
          ' . campos_ficha_certificado($v, $erros) . '
          <div><button class="botao" type="submit">Guardar os dados</button></div>
        </form>
      </details>
    </section>';
}

// Descarga do certificado: o formando só o das suas inscrições; a administração, qualquer um
function pagina_certificado(): void
{
    $u = utilizador();
    $i = linha_bd(SQL_INSCRICAO, [(int) ($_GET['i'] ?? 0)]);
    $c = $i ? certificado_da_inscricao((int) $i['id']) : null;
    if (!$c || (!eh_admin() && !mesmo_email((string) $i['email'], (string) $u['email']))) {
        http_response_code(404);
        pagina_erro('Certificado não encontrado', 'O certificado pedido não existe ou ainda não foi emitido.');
    }
    descarregar_certificado($c, $i);
}

/* Administração: certificados de uma edição */

function tratar_certificados_edicao(array $f): void
{
    $id = (int) $f['id'];
    $acao = (string) ($_POST['acao'] ?? '');
    if ($acao === 'cert_assinados') {
        receber_assinados($f);
        redirecionar('formacao', ['id' => $id], 'certificados');
    }
    $escolhidas = array_map('intval', array_filter((array) ($_POST['cert'] ?? []), 'is_numeric'));
    $lista = [];
    foreach ($escolhidas as $iid) {
        $i = linha_bd(SQL_INSCRICAO, [$iid]);
        if ($i && (int) $i['formacao_id'] === $id && in_array($i['estado'], ESTADOS_ATIVOS, true)) {
            $lista[] = $i;
        }
    }
    if (!$lista) {
        aviso('Escolha os formandos.', 'erro');
        redirecionar('formacao', ['id' => $id], 'certificados');
    }
    if ($acao === 'cert_assinar') {
        $pares = [];
        foreach ($lista as $i) {
            if ($c = certificado_da_inscricao((int) $i['id'])) {
                $pares[] = [$c, $i];
            }
        }
        if (!$pares) {
            aviso('Nenhum dos formandos escolhidos tem o certificado emitido. Emita os certificados antes de os descarregar para assinar.', 'erro');
            redirecionar('formacao', ['id' => $id], 'certificados');
        }
        descarregar_para_assinar($pares, (string) $f['titulo']);
    }
    if ($acao === 'cert_pedir') {
        $enviados = 0;
        $completas = 0;
        $sem = [];
        foreach ($lista as $i) {
            if (ficha_completa(ficha_certificado((string) $i['email']))) {
                $completas++;
            } elseif (pedir_dados_certificado($i)) {
                $enviados++;
            } else {
                $sem[] = (string) $i['formando'];
            }
        }
        aviso(($enviados ? 'Pedido dos dados enviado a ' . $enviados . ' formando(s).' : 'Nenhum pedido enviado.')
            . ($completas ? ' ' . $completas . ' formando(s) já com a ficha completa (sem pedido).' : '')
            . ($sem ? ' Sem envio (sem email ou falha no envio): ' . h(implode(', ', $sem)) . '.' : ''), $sem ? ($enviados ? 'info' : 'erro') : 'ok');
        redirecionar('formacao', ['id' => $id], 'certificados');
    }
    $enviar = $acao === 'cert_enviar';
    $emitidos = 0;
    $enviados = 0;
    $bloqueados = [];
    $falhados = [];
    $assinados = [];
    foreach ($lista as $i) {
        // Os já assinados não voltam a ser emitidos (perdiam a assinatura): só se enviam
        $atual = certificado_da_inscricao((int) $i['id']);
        if ($atual && !empty($atual['assinado'])) {
            $assinados[] = (string) $i['formando'];
            if ($enviar) {
                if (enviar_certificado($atual, $i)) {
                    $enviados++;
                } else {
                    $falhados[] = (string) $i['formando'];
                }
            }
            continue;
        }
        // As mesmas regras da ficha do formando: dados completos, presenças, assiduidade validada e avaliação "Aprovado"
        $ctx = contexto_inscricao($i);
        if ($b = bloqueios_certificado($i, $ctx)) {
            $bloqueados[] = $i['formando'] . ' (' . implode('; ', $b) . ')';
            continue;
        }
        $c = emitir_certificado($i, $ctx['ficha']);
        executar("UPDATE inscricoes SET estado = 'certificado' WHERE id = ? AND estado IN ('inscrito', 'em_formacao', 'concluido')", [$i['id']]);
        $emitidos++;
        if ($enviar) {
            if (enviar_certificado($c, $i)) {
                $enviados++;
            } else {
                $falhados[] = (string) $i['formando'];
            }
        }
    }
    $partes = array_filter([
        $emitidos ? $emitidos . ' certificado(s) emitido(s).' : 'Nenhum certificado emitido.',
        $assinados ? 'Já assinados, não foram emitidos de novo: ' . implode(', ', $assinados) . '.' : '',
        $enviar ? $enviados . ' enviado(s) por email.' : '',
        $bloqueados ? 'Bloqueados: ' . implode('; ', $bloqueados) . '.' : '',
        $falhados ? 'Email não enviado: ' . implode(', ', $falhados) . '.' : '',
    ]);
    aviso(h(implode(' ', $partes)), $bloqueados || $falhados ? ($emitidos || $enviados ? 'info' : 'erro') : 'ok');
    redirecionar('formacao', ['id' => $id], 'certificados');
}

function certificados_edicao_html(array $f): string
{
    $lista = consulta("SELECT i.id FROM inscricoes i WHERE i.formacao_id = ? AND i.estado IN ('inscrito', 'em_formacao', 'concluido', 'certificado', 'encerrado') ORDER BY i.formando COLLATE NOCASE", [$f['id']]);
    if (!$lista) {
        return '';
    }
    $linhas = '';
    $terminada = false;
    $faltam = 0;
    foreach ($lista as $l) {
        $i = linha_bd(SQL_INSCRICAO, [$l['id']]);
        $ctx = contexto_certificado($i);
        $terminada = $ctx['terminada'];
        $ficha = ficha_certificado((string) $i['email']);
        $c = certificado_da_inscricao((int) $i['id']);
        if (ficha_completa($ficha)) {
            $dados = etiqueta('Completa', 'ok') . '<br><span class="texto-suave">' . h($ficha['nome_completo']) . '<br>' . h(linha_documento($ficha)) . '</span>';
        } else {
            $faltam++;
            $dados = etiqueta('Em falta', 'info') . ((string) $i['email'] === '' ? '<br><span class="texto-suave">Sem email: preencha na ficha do formando</span>'
                : ($i['dados_pedidos'] ? '<br><span class="texto-suave">Pedidos em ' . h(data_hora($i['dados_pedidos'], false)) . '</span>' : ''));
        }
        $cert = $c ? '<a href="' . h(url('certificado', ['i' => $i['id']])) . '" target="_blank" rel="noopener">N.º ' . h(numero_certificado($c)) . '</a> '
            . (!empty($c['assinado']) ? etiqueta('Assinado', 'ok') : etiqueta('Por assinar', 'info')) . '<br><span class="texto-suave">Emitido a ' . h(data_hora($c['emitido'], false))
            . (!empty($c['assinado']) ? '<br>Assinado a ' . h(data_hora($c['assinado'], false)) : '')
            . ($c['enviado'] ? '<br>Enviado a ' . h(data_hora($c['enviado'], false)) : '<br>Por enviar') . '</span>' : '<span class="texto-suave">Por emitir</span>';
        $linhas .= '<tr><td class="c-sessao"><input type="checkbox" name="cert[]" value="' . (int) $i['id'] . '" checked aria-label="' . h($i['formando']) . '"></td>'
            . '<td><a href="' . h(url('formando', ['i' => $i['id']])) . '#certificado">' . h($i['formando']) . '</a></td><td>' . $dados . '</td>'
            . '<td>' . $ctx['presencas'] . ' de ' . $ctx['sessoes'] . '</td><td>' . $cert . '</td></tr>';
    }
    return '<h2 class="espaco-topo" id="certificados">Certificados de frequência</h2>
      <p class="texto-suave">O certificado leva os dados da ficha do formando (nome completo e documento de identificação), preenchida na área do formando ou, na ficha do formando, pela administração, e a assiduidade registada nas presenças. Só é emitido com os dados completos, a assiduidade validada pelo formador e a avaliação "Aprovado" (na ficha do formando).'
        . ($faltam ? ' ' . $faltam . ' formando(s) sem a ficha completa: use "Pedir os dados por email".' : '')
        . ($terminada ? '' : ' A edição ainda não terminou: confirme as presenças antes de emitir.') . ' Voltar a emitir um certificado mantém o número e atualiza os dados.</p>
      <form method="post">' . campo_csrf() . '
        <div class="conteudo tabela-rolante"><table class="tabela-presencas"><thead><tr><th class="c-sessao" scope="col"><span class="visualmente-oculto">Escolher</span></th><th scope="col">Formando</th><th scope="col">Dados para o certificado</th><th scope="col">Presenças</th><th scope="col">Certificado</th></tr></thead><tbody>' . $linhas . '</tbody></table></div>
        <div class="espaco-topo acoes">
          <button class="botao botao-secundario" type="submit" name="acao" value="cert_pedir">Pedir os dados por email</button>
          <button class="botao botao-secundario" type="submit" name="acao" value="cert_emitir">Emitir (sem enviar)</button>
          <button class="botao botao-secundario" type="submit" name="acao" value="cert_assinar">Descarregar para assinar</button>
          <button class="botao" type="submit" name="acao" value="cert_enviar">Emitir e enviar por email</button>
        </div>
        <p class="ajuda">As ações aplicam-se aos formandos escolhidos. Só se emitem certificados com os dados completos, presenças, a assiduidade validada pelo formador e a avaliação "Aprovado"; o email leva o PDF em anexo e o formando pode descarregá-lo na área do formando. Os certificados já assinados não voltam a ser emitidos: "Emitir e enviar" envia a versão assinada.</p>
      </form>
      <h3 class="espaco-topo" id="certificados-assinados">Assinatura digital</h3>
      <ol class="texto-suave">
        <li>Emita os certificados e use "Descarregar para assinar" (um PDF, ou um ZIP com vários).</li>
        <li>Assine cada PDF fora do site, por exemplo com o Cartão de Cidadão ou a Chave Móvel Digital na aplicação Autenticação.gov.</li>
        <li>Carregue aqui os PDF assinados, sem retirar do nome o número do certificado (por exemplo "Certificado_002-2026_Nome_assinado.pdf").</li>
        <li>Envie com "Emitir e enviar por email": segue a versão assinada, que é também a que o formando descarrega.</li>
      </ol>
      <form method="post" enctype="multipart/form-data" class="formulario">' . campo_csrf() . '<input type="hidden" name="acao" value="cert_assinados">
        <div><label for="assinados">PDF assinados</label><input type="file" id="assinados" name="assinados[]" accept="application/pdf,.pdf" multiple required></div>
        <div><button class="botao botao-secundario" type="submit">Carregar os assinados</button></div>
        <p class="ajuda">Até 10 MB por ficheiro. Só são aceites PDF com assinatura digital e de certificados desta turma. Carregar de novo substitui a versão assinada anterior; emitir de novo na ficha do formando retira-a.</p>
      </form>';
}

// Ficha do formando (administração): os dados para o certificado, editáveis
function ficha_certificado_admin_html(string $email, string $acao, array $valores = [], array $erros = []): string
{
    if (trim($email) === '') {
        return '<h2 class="espaco-topo" id="certificado">Dados para o certificado</h2><p class="texto-suave">Sem email: a ficha para o certificado fica associada ao email do formando. Acrescente o email na inscrição para a preencher.</p>';
    }
    $ficha = ficha_certificado($email);
    $v = $valores ?: ($ficha ?: []);
    return '<h2 class="espaco-topo" id="certificado">Dados para o certificado ' . (ficha_completa($ficha) ? etiqueta('Completa', 'ok') : etiqueta('Em falta', 'info')) . '</h2>
      <p class="texto-suave">' . ($ficha ? 'Atualizados em ' . h(data_hora($ficha['atualizado'])) . ((int) $ficha['atualizado_por'] === (int) (utilizador_por_email($email)['id'] ?? -1) ? ' pelo próprio formando.' : ' pela administração.') : 'O formando ainda não preencheu a ficha na área do formando. Pode preenchê-la aqui.') . '</p>
      <form method="post" action="' . h($acao) . '#certificado" class="formulario">' . campo_csrf() . '<input type="hidden" name="acao" value="ficha_certificado">
        ' . campos_ficha_certificado($v, $erros) . '
        <div><button class="botao botao-secundario" type="submit">Guardar os dados</button></div>
      </form>';
}
