<?php
// Línguas do site e da área reservada: português (pt, a língua de origem de todos os textos) e inglês do Reino Unido (en).
// O texto é escrito uma só vez, em português; em inglês, a página (ou o email, ou o texto do PDF) é traduzida à saída com
// o dicionário clientes/inc/traducoes/en.json ("texto em português": "English text"):
// - o HTML é dividido em blocos de texto (o conteúdo de um parágrafo, item, título, célula, botão…, com os elementos
//   inline, como ligações e negrito, incluídos); cada bloco, com os espaços normalizados, é procurado no dicionário;
// - sem tradução exata, os números, as datas e as horas passam a marcadores ({n}, {d}, {h}) e procura-se a forma
//   ("{n} sessões" → "{n} sessions"); as entradas com {x} aceitam qualquer texto nessa posição (nomes, cursos…), que
//   é traduzido à parte quando estiver no dicionário;
// - atributos (title, alt, aria-label, placeholder, descrições), dados estruturados (JSON-LD) e endereços (/sobre/ →
//   /en/about/, pelas rotas de clientes/inc/traducoes/rotas.json) também são convertidos;
// - o que não estiver no dicionário fica em português (com 'registar_traducoes' => true no config.php, as frases em
//   falta são registadas em dados/traducoes-em-falta.json, para as traduzir);
// - os elementos com translate="no" (nomes, emails e outros dados das pessoas) ficam como estão.

declare(strict_types=1);

const IDIOMAS = ['pt' => 'Português', 'en' => 'English'];
const TRADUCOES_DIR = __DIR__ . '/traducoes';

/* Língua atual */

// A língua definida no pedido (definir_idioma, com_idioma) prevalece sobre a da página (IDIOMA, nas páginas /en/…),
// para um email ou um PDF poderem seguir noutra língua
function idioma(): string
{
    if (isset($GLOBALS['idioma_atual'])) {
        return $GLOBALS['idioma_atual'];
    }
    return defined('IDIOMA') && isset(IDIOMAS[IDIOMA]) ? IDIOMA : 'pt';
}

function definir_idioma(string $l): void
{
    $GLOBALS['idioma_atual'] = isset(IDIOMAS[$l]) ? $l : 'pt';
}

function em_ingles(): bool
{
    return idioma() === 'en';
}

// Executa $f com a língua $l (por exemplo, um email na língua do destinatário) e repõe a anterior
function com_idioma(string $l, callable $f)
{
    $antes = $GLOBALS['idioma_atual'] ?? null;
    definir_idioma($l);
    try {
        return $f();
    } finally {
        if ($antes === null) {
            unset($GLOBALS['idioma_atual']);
        } else {
            $GLOBALS['idioma_atual'] = $antes;
        }
    }
}

// Língua da área reservada: a pedida no endereço (?idioma=en, guardada na sessão, num cookie e na conta), a da sessão,
// a preferida na conta, a do cookie ou o português
function idioma_da_area(): string
{
    $pedido = is_string($_GET['idioma'] ?? null) && isset(IDIOMAS[$_GET['idioma']]) ? $_GET['idioma'] : '';
    $u = function_exists('utilizador') ? utilizador() : null;
    if ($pedido !== '') {
        $_SESSION['idioma'] = $pedido;
        if (!headers_sent()) {
            setcookie('gm_idioma', $pedido, ['expires' => time() + 365 * 86400, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
        }
        if ($u && ($u['idioma'] ?? '') !== $pedido) {
            executar('UPDATE utilizadores SET idioma = ? WHERE id = ?', [$pedido, $u['id']]);
        }
        $l = $pedido;
    } else {
        $l = (string) ($_SESSION['idioma'] ?? '');
        if ($l === '' && $u && isset(IDIOMAS[$u['idioma'] ?? ''])) {
            $l = (string) $u['idioma'];
        }
        if ($l === '' && isset(IDIOMAS[$_COOKIE['gm_idioma'] ?? ''])) {
            $l = (string) $_COOKIE['gm_idioma'];
        }
        // Na sessão, mas ainda não na conta (escolhida antes de entrar): passa para a conta
        if ($l !== '' && $u && !isset(IDIOMAS[$u['idioma'] ?? ''])) {
            executar('UPDATE utilizadores SET idioma = ? WHERE id = ?', [$l, $u['id']]);
        }
    }
    definir_idioma($l ?: 'pt');
    return idioma();
}

// Ligação para mudar de língua na página atual da área reservada
function seletor_idioma_area(): string
{
    $outra = em_ingles() ? 'pt' : 'en';
    $q = array_filter($_GET, 'is_string');
    $q['idioma'] = $outra;
    return '<a class="seletor-idioma" href="area.php?' . h_idioma(http_build_query($q)) . '" hreflang="' . ($outra === 'en' ? 'en-GB' : 'pt-PT') . '" lang="' . ($outra === 'en' ? 'en-GB' : 'pt-PT') . '" data-fixo>' . ($outra === 'en' ? 'EN' : 'PT') . '</a>';
}

/* Dicionário e rotas */

function dicionario_en(): array
{
    static $d = null;
    if ($d === null) {
        $d = ['exatos' => [], 'formas' => [], 'livres' => []];
        $bruto = is_file(TRADUCOES_DIR . '/en.json') ? json_decode((string) file_get_contents(TRADUCOES_DIR . '/en.json'), true) : [];
        foreach (is_array($bruto) ? $bruto : [] as $pt => $en) {
            if (!is_string($en) || $en === '' || strpos((string) $pt, '__') === 0) {
                continue;
            }
            $pt = normalizar_espacos((string) $pt);
            if (strpos($pt, '{x}') !== false) {
                $d['livres'][] = [regex_forma($pt), $en];
            } elseif (preg_match('/\{[ndh]\}/', $pt)) {
                $d['formas'][$pt] = $en;
            } else {
                $d['exatos'][$pt] = $en;
            }
        }
        // As formas mais longas primeiro, para as mais específicas ganharem
        usort($d['livres'], function ($a, $b) { return strlen($b[0]) - strlen($a[0]); });
    }
    return $d;
}

// Rotas PT → EN (prefixos de endereços), a mais longa primeiro
function rotas_en(): array
{
    static $r = null;
    if ($r === null) {
        $r = is_file(TRADUCOES_DIR . '/rotas.json') ? (array) json_decode((string) file_get_contents(TRADUCOES_DIR . '/rotas.json'), true) : [];
        uksort($r, function ($a, $b) { return strlen($b) - strlen($a); });
    }
    return $r;
}

// Endereço na outra língua: /sobre/ → /en/about/ (e o inverso com $para_pt); mantém a consulta e a âncora
function endereco_traduzido(string $url, bool $para_pt = false): string
{
    $base = 'https://www.gadgetmetodo.pt';
    $abs = strpos($url, $base . '/') === 0;
    $caminho = $abs ? substr($url, strlen($base)) : $url;
    if ($caminho === '' || $caminho[0] !== '/' || strpos($caminho, '//') === 0) {
        return $url;
    }
    $resto = '';
    if (preg_match('/^([^?#]*)([?#].*)$/', $caminho, $m)) {
        [$caminho, $resto] = [$m[1], $m[2]];
    }
    $rotas = rotas_en();
    if ($para_pt) {
        $rotas = array_flip($rotas);
        uksort($rotas, function ($a, $b) { return strlen($b) - strlen($a); });
    }
    foreach ($rotas as $de => $para) {
        // Os prefixos (terminados em "/", com destino também terminado em "/") valem para as páginas por baixo deles
        if ($caminho === $de || ($de !== '/' && substr($de, -1) === '/' && substr($para, -1) === '/' && strpos($caminho, $de) === 0)) {
            $caminho = $para . substr($caminho, strlen($de));
            break;
        }
    }
    return ($abs ? $base : '') . $caminho . $resto;
}

/* Tradução de um bloco de texto (pode ter HTML inline) */

function normalizar_espacos(string $s): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $s));
}

const MESES_PT = ['janeiro' => 'January', 'fevereiro' => 'February', 'março' => 'March', 'abril' => 'April', 'maio' => 'May', 'junho' => 'June',
    'julho' => 'July', 'agosto' => 'August', 'setembro' => 'September', 'outubro' => 'October', 'novembro' => 'November', 'dezembro' => 'December'];
const DIAS_PT = ['segunda-feira' => 'Monday', 'terça-feira' => 'Tuesday', 'quarta-feira' => 'Wednesday', 'quinta-feira' => 'Thursday',
    'sexta-feira' => 'Friday', 'sábado' => 'Saturday', 'domingo' => 'Sunday'];

// Expressões de datas em português, da mais longa para a mais curta, e a sua versão em inglês
function datas_pt(): array
{
    $m = implode('|', array_keys(MESES_PT));
    $d = implode('|', array_map('preg_quote', array_keys(DIAS_PT)));
    return [
        '/\b(' . $d . '), (\d{1,2}) de (' . $m . ') de (\d{4})/u' => function ($x) { return DIAS_PT[$x[1]] . ', ' . $x[2] . ' ' . MESES_PT[$x[3]] . ' ' . $x[4]; },
        '/\bde (\d{1,2}) de (' . $m . ') de (\d{4}) a (\d{1,2}) de (' . $m . ') de (\d{4})/u' => function ($x) { return 'from ' . $x[1] . ' ' . MESES_PT[$x[2]] . ' ' . $x[3] . ' to ' . $x[4] . ' ' . MESES_PT[$x[5]] . ' ' . $x[6]; },
        '/\bde (\d{1,2}) de (' . $m . ') a (\d{1,2}) de (' . $m . ') de (\d{4})/u' => function ($x) { return 'from ' . $x[1] . ' ' . MESES_PT[$x[2]] . ' to ' . $x[3] . ' ' . MESES_PT[$x[4]] . ' ' . $x[5]; },
        '/\bde (\d{1,2}) a (\d{1,2}) de (' . $m . ') de (\d{4})/u' => function ($x) { return 'from ' . $x[1] . ' to ' . $x[2] . ' ' . MESES_PT[$x[3]] . ' ' . $x[4]; },
        '/\b(\d{1,2}) de (' . $m . ') de (\d{4})/u' => function ($x) { return $x[1] . ' ' . MESES_PT[$x[2]] . ' ' . $x[3]; },
        '/\b(\d{1,2}) de (' . $m . ')\b/u' => function ($x) { return $x[1] . ' ' . MESES_PT[$x[2]]; },
        '/\b(' . $m . ') de (\d{4})/u' => function ($x) { return ucfirst(MESES_PT[$x[1]]) . ' ' . $x[2]; },
        '/\b(' . $d . ')(?![\w-])/u' => function ($x) { return DIAS_PT[$x[1]]; },
        '/\b\d{2}\/\d{2}\/\d{4}\b/u' => function ($x) { return $x[0]; },
    ];
}

// Forma de um texto: datas, horas e números substituídos por {d}, {h} e {n}; devolve [forma, valores em inglês]
function forma_texto(string $s): array
{
    $valores = [];
    $marcas = [];
    foreach (datas_pt() as $re => $f) {
        $s = (string) preg_replace_callback($re, function ($x) use ($f, &$marcas) {
            $marcas[] = ['d', $f($x)];
            return marca_texto(count($marcas) - 1);
        }, $s);
    }
    $s = (string) preg_replace_callback('/\b\d{1,2}:\d{2}\b/', function ($x) use (&$marcas) {
        $marcas[] = ['h', $x[0]];
        return marca_texto(count($marcas) - 1);
    }, $s);
    $s = (string) preg_replace_callback('/(?<![\w\/.\-#=?&])\d+(?:,\d+)?(?![\w\/\-])/u', function ($x) use (&$marcas) {
        $marcas[] = ['n', str_replace(',', '.', $x[0])];
        return marca_texto(count($marcas) - 1);
    }, $s);
    $forma = (string) preg_replace_callback('/\x01([a-z]+)\x02/', function ($x) use ($marcas, &$valores) {
        $k = 0;
        foreach (str_split($x[1]) as $c) {
            $k = $k * 26 + ord($c) - 97;
        }
        $valores[] = $marcas[$k][1];
        return '{' . $marcas[$k][0] . '}';
    }, $s);
    return [$forma, $valores];
}

// Marca provisória de um valor (só letras, para os números seguintes não a apanharem)
function marca_texto(int $k): string
{
    $l = '';
    do {
        $l = chr(97 + $k % 26) . $l;
        $k = intdiv($k, 26);
    } while ($k > 0);
    return "\x01" . $l . "\x02";
}

// Expressão regular de uma forma com {x} (e {n}, {d}, {h} literais, porque se compara com a forma do texto)
function regex_forma(string $forma): string
{
    $partes = preg_split('/(\{x\})/', $forma, -1, PREG_SPLIT_DELIM_CAPTURE);
    $re = '';
    foreach ($partes as $p) {
        $re .= $p === '{x}' ? '(.+?)' : preg_quote($p, '/');
    }
    return '/^' . $re . '$/su';
}

// Repõe os valores nos marcadores da tradução, pela ordem
function repor_valores(string $en, array $valores): string
{
    $i = 0;
    return (string) preg_replace_callback('/\{[ndh]\}/', function () use (&$i, $valores) {
        return (string) ($valores[$i++] ?? '');
    }, $en);
}

// Tradução de um bloco (texto ou HTML inline, com espaços normalizados); null se não houver
function traduzir_bloco(string $s, bool $livre = true): ?string
{
    $d = dicionario_en();
    if (isset($d['exatos'][$s])) {
        return $d['exatos'][$s];
    }
    [$forma, $valores] = forma_texto($s);
    if ($valores && isset($d['formas'][$forma])) {
        return repor_valores($d['formas'][$forma], $valores);
    }
    if (!$livre) {
        return null;
    }
    foreach ($d['livres'] as [$re, $en]) {
        if (preg_match($re, $forma, $m)) {
            // Os valores de {n}, {d} e {h} que caíram dentro de um {x} voltam ao texto do {x}
            $i = 0;
            $restantes = $valores;
            $partes = [];
            foreach (array_slice($m, 1) as $parte) {
                $n = preg_match_all('/\{[ndh]\}/', $parte);
                $texto = repor_valores($parte, array_slice($restantes, 0, $n));
                $restantes = array_slice($restantes, $n);
                $partes[] = traduzir_parte(normalizar_espacos($texto));
            }
            $saida = $en;
            $saida = (string) preg_replace_callback('/\{x\}/', function () use (&$i, $partes) { return $partes[$i++] ?? ''; }, $saida);
            return repor_valores($saida, $restantes);
        }
    }
    return null;
}

// Parte variável ({x}) de uma frase: o texto traduzido, se estiver no dicionário; os temas juntos com " + " (as sessões
// com vários temas) são traduzidos um a um
function traduzir_parte(string $s): string
{
    $t = traduzir_bloco($s, false);
    if ($t !== null) {
        return $t;
    }
    if (strpos($s, ' + ') !== false) {
        return implode(' + ', array_map(function ($p) { return traduzir_bloco($p, false) ?? $p; }, explode(' + ', $s)));
    }
    return $s;
}

// Texto simples (atributos, emails em texto, PDF): tradução ou o próprio texto
function tr(string $s): string
{
    if (!em_ingles() || trim($s) === '') {
        return $s;
    }
    $n = normalizar_espacos($s);
    $t = traduzir_bloco($n);
    if ($t === null) {
        registar_em_falta($n);
        return $s;
    }
    return $t;
}

// Texto com parágrafos (separados por uma linha em branco), como os textos dos emails: cada parágrafo é traduzido à parte.
// Devolve [texto em inglês, true se todos os parágrafos tinham tradução].
function tr_paragrafos(string $texto): array
{
    $completo = true;
    $partes = [];
    foreach (preg_split('/\n[ \t]*\n/u', trim(str_replace("\r\n", "\n", $texto))) ?: [] as $p) {
        if (trim($p) === '') {
            continue;
        }
        $n = normalizar_espacos($p);
        $t = traduzivel($n) ? traduzir_bloco($n) : $n;
        if ($t === null) {
            registar_em_falta($n);
            $completo = false;
            $t = $p;
        }
        $partes[] = $t;
    }
    return [implode("\n\n", $partes), $completo];
}

// Língua de uma pessoa, pelo email: a preferida na conta, senão a da última inscrição ou pré-inscrição feita no site
// em inglês, senão o português
function idioma_do_email(string $email): string
{
    if (trim($email) === '' || !function_exists('bd') || !bd()) {
        return 'pt';
    }
    foreach (['SELECT idioma FROM utilizadores WHERE ' . sprintf(SQL_EMAIL_IGUAL_IDIOMA, 'email'),
        'SELECT idioma FROM inscricoes WHERE idioma IS NOT NULL AND ' . sprintf(SQL_EMAIL_IGUAL_IDIOMA, 'email') . ' ORDER BY id DESC LIMIT 1',
        'SELECT idioma FROM preinscricoes WHERE idioma IS NOT NULL AND ' . sprintf(SQL_EMAIL_IGUAL_IDIOMA, 'email') . ' ORDER BY id DESC LIMIT 1'] as $sql) {
        $l = (string) (linha_bd($sql, [$email])['idioma'] ?? '');
        if (isset(IDIOMAS[$l])) {
            return $l;
        }
    }
    return 'pt';
}

const SQL_EMAIL_IGUAL_IDIOMA = 'TRIM(%s) = TRIM(?) COLLATE NOCASE';

// Língua de uma inscrição feita agora: a da página pública (inglês em /en/…); na área reservada, sem indicação
function idioma_da_pagina_publica(): ?string
{
    return defined('IDIOMA') ? IDIOMA : null;
}

/* Frases em falta */

// Texto que vale a pena traduzir: tem letras e não é só um email, um endereço ou um número
function traduzivel(string $s): bool
{
    $t = trim(strip_tags($s));
    return $t !== '' && preg_match('/\p{L}{2,}/u', $t) && !preg_match('/^[\w.+-]+@[\w.-]+$/u', $t) && !preg_match('/^(https?:\/\/|www\.)\S+$/u', $t);
}

// Só regista com 'registar_traducoes' => true no formulario/config.php (para recolher as frases a traduzir; o registo
// pode incluir nomes e outros dados mostrados nas páginas, por isso fica desligado por omissão)
function registar_em_falta(string $s): void
{
    if (!traduzivel($s) || mb_strlen($s) > 4000 || !function_exists('cfg') || empty(cfg()['registar_traducoes'])) {
        return;
    }
    // Com números, datas ou horas, regista-se a forma ("{n} sessões"), que serve para todos os valores
    [$forma, $valores] = forma_texto($s);
    $GLOBALS['traducoes_em_falta'][$valores ? $forma : $s] = true;
    static $registado = false;
    if (!$registado) {
        $registado = true;
        register_shutdown_function('gravar_em_falta');
    }
}

function ficheiro_em_falta(): ?string
{
    $pasta = function_exists('pasta_dados') && function_exists('cfg') ? pasta_dados(cfg()) : null;
    return $pasta ? $pasta . '/traducoes-em-falta.json' : null;
}

function gravar_em_falta(): void
{
    $novas = array_keys($GLOBALS['traducoes_em_falta'] ?? []);
    $GLOBALS['traducoes_em_falta'] = [];
    $f = ficheiro_em_falta();
    if (!$novas || !$f) {
        return;
    }
    $h = @fopen($f, 'c+');
    if (!$h || !flock($h, LOCK_EX)) {
        return;
    }
    $atual = json_decode((string) stream_get_contents($h), true);
    $atual = is_array($atual) ? $atual : [];
    foreach ($novas as $s) {
        if (!isset($atual[$s]) && count($atual) < 5000) {
            $atual[$s] = date('Y-m-d H:i:s');
        }
    }
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, (string) json_encode($atual, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    flock($h, LOCK_UN);
    fclose($h);
}

/* Tradução de uma página HTML */

const HTML_VAZIOS = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'];
const HTML_INLINE = ['a', 'abbr', 'b', 'bdi', 'cite', 'code', 'em', 'i', 'kbd', 'mark', 'q', 's', 'small', 'span', 'strong', 'sub', 'sup', 'time', 'u', 'br', 'img', 'svg', 'wbr', 'label', 'data', 'output', 'var', 'del', 'ins'];
const HTML_OPACOS = ['svg', 'img', 'br', 'wbr', 'input', 'select', 'textarea'];

// Divide o HTML em fichas: ['t', texto] | ['a', nome, ficha] (abertura) | ['f', nome, ficha] (fecho) | ['v', nome, ficha] (sem fecho) | ['r', bruto]
function fichas_html(string $html): array
{
    $r = [];
    $n = strlen($html);
    $i = 0;
    while ($i < $n) {
        $lt = strpos($html, '<', $i);
        if ($lt === false) {
            $r[] = ['t', substr($html, $i)];
            break;
        }
        if ($lt > $i) {
            $r[] = ['t', substr($html, $i, $lt - $i)];
        }
        if (substr($html, $lt, 4) === '<!--') {
            $fim = strpos($html, '-->', $lt);
            $fim = $fim === false ? $n : $fim + 3;
            $r[] = ['r', substr($html, $lt, $fim - $lt)];
            $i = $fim;
            continue;
        }
        if (!preg_match('/\G<(\/?)([a-zA-Z][a-zA-Z0-9:-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/', $html, $m, 0, $lt)) {
            if (preg_match('/\G<![^>]*>/', $html, $m2, 0, $lt)) {
                $r[] = ['r', $m2[0]];
                $i = $lt + strlen($m2[0]);
            } else {
                $r[] = ['t', '<'];
                $i = $lt + 1;
            }
            continue;
        }
        $nome = strtolower($m[2]);
        $i = $lt + strlen($m[0]);
        if ($m[1] === '/') {
            $r[] = ['f', $nome, $m[0]];
        } elseif (in_array($nome, HTML_VAZIOS, true) || substr(rtrim($m[3]), -1) === '/') {
            $r[] = ['v', $nome, $m[0]];
        } else {
            $r[] = ['a', $nome, $m[0]];
            // Conteúdo bruto: scripts, estilos e campos de texto (dados escritos pelas pessoas)
            if (in_array($nome, ['script', 'style', 'textarea'], true)) {
                $fim = stripos($html, '</' . $nome, $i);
                $fim = $fim === false ? $n : $fim;
                $r[] = ['raw', $nome, substr($html, $i, $fim - $i), $m[0]];
                $i = $fim;
            }
        }
    }
    return $r;
}

// Árvore de nós: ['tipo' => 'e', 'nome', 'abre', 'filhos', 'fecha'] ou ['tipo' => 't'|'r', 'html']
function arvore_html(array $fichas): array
{
    $raiz = ['tipo' => 'e', 'nome' => '#', 'abre' => '', 'filhos' => [], 'fecha' => ''];
    $pilha = [&$raiz];
    foreach ($fichas as $f) {
        $topo = &$pilha[count($pilha) - 1];
        if ($f[0] === 't') {
            $topo['filhos'][] = ['tipo' => 't', 'html' => $f[1]];
        } elseif ($f[0] === 'r') {
            $topo['filhos'][] = ['tipo' => 'r', 'html' => $f[1]];
        } elseif ($f[0] === 'raw') {
            $topo['filhos'][] = ['tipo' => 'raw', 'nome' => $f[1], 'html' => $f[2]];
        } elseif ($f[0] === 'v') {
            $topo['filhos'][] = ['tipo' => 'e', 'nome' => $f[1], 'abre' => $f[2], 'filhos' => [], 'fecha' => ''];
        } elseif ($f[0] === 'a') {
            $topo['filhos'][] = ['tipo' => 'e', 'nome' => $f[1], 'abre' => $f[2], 'filhos' => [], 'fecha' => null];
            $pilha[] = &$topo['filhos'][count($topo['filhos']) - 1];
        } else {
            // Fecho: sobe até ao elemento aberto com o mesmo nome (os que ficaram abertos fecham-se aí)
            for ($k = count($pilha) - 1; $k > 0; $k--) {
                if ($pilha[$k]['nome'] === $f[1]) {
                    $pilha[$k]['fecha'] = $f[2];
                    array_splice($pilha, $k);
                    break;
                }
            }
        }
        unset($topo);
    }
    return $raiz;
}

function html_no(array $no): string
{
    if ($no['tipo'] !== 'e') {
        return $no['html'];
    }
    return $no['abre'] . implode('', array_map('html_no', $no['filhos'])) . ($no['fecha'] ?? '');
}

function no_inline(array $no): bool
{
    return $no['tipo'] === 't' || ($no['tipo'] === 'e' && in_array($no['nome'], HTML_INLINE, true) && !tem_bloco($no) && !sem_traducao($no));
}

// Elementos marcados com translate="no" (nomes, emails e outros dados das pessoas): ficam como estão
function sem_traducao(array $no): bool
{
    return $no['tipo'] === 'e' && stripos($no['abre'], 'translate="no"') !== false;
}

function tem_bloco(array $no): bool
{
    foreach ($no['filhos'] ?? [] as $f) {
        if ($f['tipo'] === 'e' && (!in_array($f['nome'], HTML_INLINE, true) || tem_bloco($f))) {
            return true;
        }
    }
    return false;
}

function no_vazio(array $no): bool
{
    return ($no['tipo'] === 't' && trim(html_entity_decode($no['html'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\u{00A0}") === '')
        || ($no['tipo'] === 'e' && in_array($no['nome'], HTML_OPACOS, true)) || $no['tipo'] === 'r';
}

// Traduz os filhos de um elemento: sequências de nós inline viram um bloco; os elementos de bloco são percorridos
function traduzir_filhos(array &$no): void
{
    $novos = [];
    $seq = [];
    $fechar = function () use (&$seq, &$novos) {
        if ($seq) {
            foreach (traduzir_sequencia($seq) as $x) {
                $novos[] = $x;
            }
            $seq = [];
        }
    };
    foreach ($no['filhos'] as $f) {
        if (no_inline($f) || $f['tipo'] === 'r') {
            $seq[] = $f;
        } else {
            $fechar();
            if ($f['tipo'] === 'e' && !sem_traducao($f)) {
                traduzir_filhos($f);
            } elseif ($f['tipo'] === 'raw' && $f['nome'] === 'script') {
                $f['html'] = traduzir_jsonld($f['html']);
            }
            $novos[] = $f;
        }
    }
    $fechar();
    $no['filhos'] = $novos;
}

// Uma sequência de nós inline: tira os nós vazios e opacos das pontas; se sobrar um só elemento, desce nele;
// senão traduz o HTML do meio como um bloco
function traduzir_sequencia(array $seq): array
{
    $ini = 0;
    $fim = count($seq) - 1;
    while ($ini <= $fim && no_vazio($seq[$ini])) {
        $ini++;
    }
    while ($fim >= $ini && no_vazio($seq[$fim])) {
        $fim--;
    }
    if ($ini > $fim) {
        return $seq;
    }
    $meio = array_slice($seq, $ini, $fim - $ini + 1);
    // Só elementos, sem texto entre eles (por exemplo, botões lado a lado): cada um é traduzido à parte
    $so_elementos = !array_filter($meio, function ($x) { return !no_vazio($x) && $x['tipo'] !== 'e'; });
    if ($so_elementos) {
        foreach ($meio as &$e) {
            if ($e['tipo'] === 'e' && !sem_traducao($e)) {
                traduzir_filhos($e);
            }
        }
        unset($e);
    } else {
        $html = implode('', array_map('html_no', $meio));
        $chave = normalizar_espacos($html);
        if (traduzivel($chave)) {
            $t = traduzir_bloco($chave);
            if ($t !== null) {
                $meio = [['tipo' => 't', 'html' => $t]];
            } else {
                registar_em_falta($chave);
            }
        }
    }
    return array_merge(array_slice($seq, 0, $ini), $meio, array_slice($seq, $fim + 1));
}

// Dados estruturados: textos traduzidos, endereços convertidos e a língua
function traduzir_jsonld(string $conteudo): string
{
    $dados = json_decode($conteudo, true);
    if (!is_array($dados)) {
        return $conteudo;
    }
    $percorrer = function (&$v, $chave) use (&$percorrer) {
        if (is_array($v)) {
            foreach ($v as $k => &$x) {
                $percorrer($x, (string) $k);
            }
            return;
        }
        if (!is_string($v)) {
            return;
        }
        if ($chave === 'inLanguage') {
            $v = $v === 'pt-PT' ? 'en-GB' : $v;
        } elseif (preg_match('/^(https?:\/\/|\/)/', $v)) {
            $v = endereco_traduzido($v);
        } elseif (!in_array($chave, ['@type', '@context', 'telephone', 'email', 'postalCode', 'addressCountry', 'priceCurrency', 'vatID', 'taxID', 'foundingDate', 'dateModified', 'datePublished', 'startDate', 'endDate', 'duration'], true) && traduzivel($v)) {
            $t = traduzir_bloco(normalizar_espacos($v));
            if ($t === null) {
                registar_em_falta(normalizar_espacos($v));
            } else {
                $v = $t;
            }
        }
    };
    $percorrer($dados, '');
    return "\n" . json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n  ";
}

// Atributos de uma etiqueta: textos (title, alt, aria-label, placeholder, descrições) e endereços (href, action, og:url)
function traduzir_etiqueta(string $tag): string
{
    // Elementos fixos (seletor de língua, hreflang) e os que já estão em inglês ficam como estão
    if (stripos($tag, 'data-fixo') !== false || stripos($tag, 'lang="en-GB"') !== false) {
        return $tag;
    }
    $meta_texto = preg_match('/^<meta\b/i', $tag) && preg_match('/\b(name|property)="(description|og:title|og:description|og:image:alt|twitter:title|twitter:description)"/i', $tag);
    $tag = (string) preg_replace_callback('/\s(title|alt|aria-label|placeholder|content|value|data-copiado|data-confirmar)="([^"]*)"/i', function ($m) use ($tag, $meta_texto) {
        $nome = strtolower($m[1]);
        if ($nome === 'content' && !$meta_texto) {
            return $m[0];
        }
        if ($nome === 'value' && !preg_match('/^<(input[^>]*type="(submit|button)"|button)/i', $tag)) {
            return $m[0];
        }
        $texto = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!traduzivel($texto)) {
            return $m[0];
        }
        return ' ' . $m[1] . '="' . htmlspecialchars(tr($texto), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') . '"';
    }, $tag);
    $tag = (string) preg_replace_callback('/\s(href|action)="([^"]*)"/i', function ($m) {
        return ' ' . $m[1] . '="' . htmlspecialchars(endereco_traduzido(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') . '"';
    }, $tag);
    if (preg_match('/^<meta\b[^>]*property="og:url"/i', $tag)) {
        $tag = (string) preg_replace_callback('/\scontent="([^"]*)"/', function ($m) { return ' content="' . h_idioma(endereco_traduzido($m[1])) . '"'; }, $tag);
    }
    return $tag;
}

function h_idioma(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

// Página completa em inglês: texto, atributos, endereços, língua do documento e seletor de língua
function traduzir_html(string $html): string
{
    if (!em_ingles() || $html === '') {
        return $html;
    }
    $raiz = arvore_html(fichas_html($html));
    traduzir_filhos($raiz);
    $html = html_no($raiz);
    $html = (string) preg_replace_callback('/<[a-zA-Z][^>]*>/', function ($m) { return traduzir_etiqueta($m[0]); }, $html);
    // Seletor de língua do site (gerado nas páginas portuguesas para a versão inglesa): passa a apontar para a portuguesa
    $html = (string) preg_replace_callback('/<a\b([^>]*)data-idioma="en"([^>]*)>EN<\/a>/', function ($m) {
        $atr = $m[1] . $m[2];
        $pt = preg_match('/href="([^"]*)"/', $atr, $x) ? endereco_traduzido(html_entity_decode($x[1]), true) : '/';
        $atr = (string) preg_replace(['/href="[^"]*"/', '/hreflang="[^"]*"/', '/lang="[^"]*"/', '/title="[^"]*"/'], ['href="' . h_idioma($pt) . '"', 'hreflang="pt-PT"', 'lang="pt-PT"', 'title="Ver em português"'], $atr);
        return '<a' . $atr . 'data-idioma="pt">PT</a>';
    }, $html);
    $html = (string) preg_replace('/<html lang="pt-PT"/', '<html lang="en-GB"', $html, 1);
    $html = str_replace('<meta property="og:locale" content="pt_PT">', '<meta property="og:locale" content="en_GB">', $html);
    return $html;
}

// Callback de ob_start: só as respostas em HTML são traduzidas (os PDF, as agendas e os ficheiros ficam como estão)
function traduzir_saida(string $saida): string
{
    foreach (headers_list() as $cab) {
        if (stripos($cab, 'Content-Type:') === 0 && stripos($cab, 'text/html') === false) {
            return $saida;
        }
    }
    $r = traduzir_html($saida);
    // O PHP esvazia os buffers de saída depois das funções de fim de pedido: as frases em falta gravam-se já aqui
    gravar_em_falta();
    return $r;
}

function iniciar_traducao(): void
{
    if (em_ingles()) {
        ob_start('traduzir_saida');
    }
}

// Página estática do site em inglês (/en/…/index.php): a página portuguesa traduzida
function servir_pagina_en(string $ficheiro_pt): void
{
    if (!defined('IDIOMA')) {
        define('IDIOMA', 'en');
    }
    header('Content-Type: text/html; charset=utf-8');
    echo traduzir_html((string) file_get_contents($ficheiro_pt));
}
