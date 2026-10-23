<?php
// Ficha do curso em PDF, gerada no servidor com FPDF: A4 com margens fixas, cabeçalho, rodapé e "Página X de Y",
// iguais em qualquer navegador. Usa os dados da ficha em HTML (dados_ficha) e as fontes da marca em Windows-1252.

declare(strict_types=1);

require_once __DIR__ . '/../../formulario/lib/FPDF/fpdf.php';

// Converte UTF-8 para Windows-1252, a codificação das fontes do PDF (cobre o português e a pontuação habitual)
function pdf_txt(?string $s): string
{
    $s = strtr((string) $s, ["\u{2192}" => '->', "\u{2264}" => '<=', "\u{2265}" => '>=', "\u{00A0}" => ' ', "\u{202F}" => ' ']);
    if (function_exists('iconv') && ($r = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s)) !== false) {
        return $r;
    }
    if (function_exists('mb_convert_encoding')) {
        return (string) mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    }
    $especiais = [0x20AC => 0x80, 0x201A => 0x82, 0x201E => 0x84, 0x2026 => 0x85, 0x2018 => 0x91, 0x2019 => 0x92, 0x201C => 0x93, 0x201D => 0x94, 0x2022 => 0x95, 0x2013 => 0x96, 0x2014 => 0x97];
    return (string) preg_replace_callback('/[\xC2-\xDF][\x80-\xBF]|[\xE0-\xEF][\x80-\xBF]{2}/', function ($m) use ($especiais) {
        $b = $m[0];
        $cp = strlen($b) === 2 ? ((ord($b[0]) & 0x1F) << 6) | (ord($b[1]) & 0x3F) : ((ord($b[0]) & 0x0F) << 12) | ((ord($b[1]) & 0x3F) << 6) | (ord($b[2]) & 0x3F);
        return $cp <= 0xFF ? chr($cp) : (isset($especiais[$cp]) ? chr($especiais[$cp]) : '?');
    }, $s);
}

function pdf_maiusculas(string $s): string
{
    return function_exists('mb_strtoupper') ? mb_strtoupper($s, 'UTF-8')
        : strtr(strtoupper($s), ['á' => 'Á', 'à' => 'À', 'â' => 'Â', 'ã' => 'Ã', 'é' => 'É', 'ê' => 'Ê', 'í' => 'Í', 'ó' => 'Ó', 'ô' => 'Ô', 'õ' => 'Õ', 'ú' => 'Ú', 'ç' => 'Ç']);
}

class FichaPdf extends FPDF
{
    const CORES = [
        'marinho' => [10, 15, 38], 'petroleo' => [0, 99, 106], 'profundo' => [0, 63, 70], 'texto' => [43, 47, 54],
        'suave' => [104, 114, 128], 'linha' => [229, 231, 235], 'nevoa' => [245, 247, 248], 'tinta' => [232, 241, 241],
        'claro' => [143, 195, 198], 'cinza' => [201, 206, 216], 'branco' => [255, 255, 255], 'zebra' => [250, 251, 251],
        'seg0' => [107, 143, 148], 'seg1' => [68, 115, 122], 'seg2' => [47, 93, 100],
    ];
    const LARGURA = 170; // largura útil: A4 (210 mm) menos 20 mm de cada lado

    public $titulo_curso = '';
    public $logotipo = '';

    public function __construct()
    {
        parent::__construct('P', 'mm', 'A4');
        $dir = __DIR__ . '/../../formulario/lib/FPDF/fontes/';
        $this->AddFont('inter', '', 'inter-400.json', $dir);
        $this->AddFont('inter', 'B', 'inter-700.json', $dir);
        $this->AddFont('intersemi', '', 'inter-600.json', $dir);
        $this->AddFont('montserrat', '', 'montserrat-600.json', $dir);
        $this->AddFont('montserrat', 'B', 'montserrat-700.json', $dir);
        $this->SetMargins(20, 26, 20);
        $this->SetAutoPageBreak(true, 22);
        $this->cMargin = 0;
        $this->AliasNbPages('{nb}');
    }

    public function cor(string $nome, string $onde = 'T'): void
    {
        [$r, $g, $b] = self::CORES[$nome];
        if ($onde === 'T') {
            $this->SetTextColor($r, $g, $b);
        } elseif ($onde === 'F') {
            $this->SetFillColor($r, $g, $b);
        } else {
            $this->SetDrawColor($r, $g, $b);
        }
    }

    public function fonte(string $familia, string $estilo, float $tamanho, string $cor = 'texto'): void
    {
        $this->SetFont($familia, $estilo, $tamanho);
        $this->cor($cor);
    }

    // Texto em maiúsculas com espaçamento entre letras (rótulos); alinhado à esquerda ou à direita de $x
    public function rotulo(float $x, float $y, string $texto, float $espaco = 0.35, string $alinhar = 'L'): float
    {
        $t = pdf_txt(pdf_maiusculas($texto));
        $largura = $this->GetStringWidth($t) + $espaco * max(0, strlen($t) - 1);
        $inicio = $alinhar === 'R' ? $x - $largura : $x;
        $this->_out(sprintf('%.3F Tc', $espaco * $this->k));
        $this->Text($inicio, $y, $t);
        $this->_out('0 Tc');
        return $largura;
    }

    // Número de linhas que MultiCell ocupa com a fonte atual
    public function linhas(float $w, string $txt): int
    {
        $cw = $this->CurrentFont['cw'];
        $wmax = $w * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] === "\n") {
            $nb--;
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c === "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c === ' ') {
                $sep = $i;
            }
            $l += $cw[$c] ?? 0;
            if ($l > $wmax) {
                if ($sep === -1) {
                    if ($i === $j) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }

    // Bloco de texto composto (partes com fonte, cor, marcador e espaço antes): mede ou escreve numa coluna
    // Cada parte: ['t' => texto, 'f' => [família, estilo, tamanho], 'c' => cor, 'lh' => altura da linha, 'marca' => bool, 'antes' => mm]
    public function bloco(array $partes, float $x, float $y, float $w, bool $escrever = true): float
    {
        $yy = $y;
        foreach ($partes as $p) {
            if (($p['t'] ?? '') === '') {
                continue;
            }
            [$fam, $est, $tam] = $p['f'];
            $this->SetFont($fam, $est, $tam);
            $yy += $p['antes'] ?? 0;
            $recuo = !empty($p['marca']) ? 3.6 : 0;
            $t = pdf_txt($p['t']);
            $h = $this->linhas($w - $recuo, $t) * $p['lh'];
            if ($escrever) {
                $this->cor($p['c'] ?? 'texto');
                if ($recuo) {
                    $this->cor('petroleo');
                    $this->Text($x + 0.4, $yy + $p['lh'] * 0.72, chr(149));
                    $this->cor($p['c'] ?? 'texto');
                }
                $this->SetXY($x + $recuo, $yy);
                $this->MultiCell($w - $recuo, $p['lh'], $t, 0, $p['alinhar'] ?? 'L');
            }
            $yy += $h;
        }
        return $yy - $y;
    }

    public function cabe(float $altura): bool
    {
        return $this->GetY() + $altura <= $this->PageBreakTrigger;
    }

    public function espaco(float $altura): void
    {
        if (!$this->cabe($altura)) {
            $this->AddPage();
        }
    }

    // Título de secção numerado, com filete; $minimo garante que o título não fica sozinho no fim da página
    public function seccao(int $n, string $titulo, float $minimo = 30): void
    {
        $this->espaco($minimo);
        $y = $this->GetY();
        $this->fonte('montserrat', 'B', 10, 'petroleo');
        $num = sprintf('%02d', $n);
        $this->Text($this->lMargin, $y + 5, $num);
        $this->fonte('montserrat', 'B', 12.5, 'marinho');
        $this->Text($this->lMargin + 9, $y + 5, pdf_txt($titulo));
        $this->cor('marinho', 'D');
        $this->SetLineWidth(0.45);
        $this->Line($this->lMargin, $y + 7.6, $this->lMargin + self::LARGURA, $y + 7.6);
        $this->SetLineWidth(0.2);
        $this->SetY($y + 11);
    }

    public function paragrafos(string $texto, float $tamanho = 9.4, float $lh = 4.9): void
    {
        foreach (temas($texto) as $p) {
            $this->fonte('inter', '', $tamanho, 'texto');
            $this->espaco($lh * 2);
            $this->SetX($this->lMargin);
            $this->MultiCell(self::LARGURA, $lh, pdf_txt($p), 0, 'J');
            $this->Ln(1.6);
        }
    }

    public function Header(): void
    {
        if ($this->PageNo() === 1) {
            return;
        }
        if ($this->logotipo !== '') {
            $this->Image($this->logotipo, $this->lMargin, 10, 34);
        }
        $this->fonte('inter', '', 7.5, 'suave');
        $t = pdf_txt('Ficha de curso · ' . $this->titulo_curso);
        while ($this->GetStringWidth($t) > 120 && strlen($t) > 10) {
            $t = substr($t, 0, -2);
        }
        $this->Text($this->lMargin + self::LARGURA - $this->GetStringWidth($t), 14.2, $t);
        $this->cor('linha', 'D');
        $this->SetLineWidth(0.2);
        $this->Line($this->lMargin, 18, $this->lMargin + self::LARGURA, 18);
        $this->SetY(26);
    }

    public function Footer(): void
    {
        $this->cor('linha', 'D');
        $this->SetLineWidth(0.2);
        $this->Line($this->lMargin, 281, $this->lMargin + self::LARGURA, 281);
        $this->fonte('inter', '', 7.5, 'suave');
        $this->Text($this->lMargin, 285.5, pdf_txt('Gadgetmetodo, Lda.  ·  www.gadgetmetodo.pt  ·  info@gadgetmetodo.pt'));
        $p = pdf_txt('Página ' . $this->PageNo() . ' de {nb}');
        // "{nb}" é substituído no fim pelo total de páginas: a largura é calculada com um número de 2 algarismos
        $w = $this->GetStringWidth(str_replace('{nb}', '00', $p));
        $this->Text($this->lMargin + self::LARGURA - $w, 285.5, $p);
    }
}

// Tabela de pares "rótulo: valor" (sessão-tipo, avaliação), medida ou desenhada numa coluna
function pdf_tabela_pares(FichaPdf $pdf, array $linhas, float $x, float $y, float $w, float $w1, array $cab, bool $escrever): float
{
    $yy = $y;
    $pad = 1.6;
    if ($cab) {
        if ($escrever) {
            $pdf->cor('nevoa', 'F');
            $pdf->Rect($x, $yy, $w, 6.4, 'F');
            $pdf->fonte('montserrat', 'B', 7.2, 'marinho');
            $pdf->Text($x + 2, $yy + 4.3, pdf_txt($cab[0]));
            $t = pdf_txt($cab[1]);
            $pdf->Text($cab[2] === 'R' ? $x + $w - 2 - $pdf->GetStringWidth($t) : $x + $w1 + 2, $yy + 4.3, $t);
        }
        $yy += 6.4;
    }
    foreach ($linhas as $l) {
        $pdf->SetFont('inter', $l['negrito'] || !empty($l['destaque']) ? 'B' : '', 8.4);
        $a = $pdf->linhas($w1 - 4, pdf_txt($l[0]));
        $b = $pdf->linhas($w - $w1 - 4, pdf_txt($l[1]));
        $h = max($a, $b) * 4.1 + 2 * $pad;
        if ($escrever) {
            if (!empty($l['destaque'])) {
                $pdf->cor('tinta', 'F');
                $pdf->Rect($x, $yy, $w, $h, 'F');
            }
            $pdf->cor(!empty($l['destaque']) ? 'profundo' : 'texto');
            $pdf->SetXY($x + 2, $yy + $pad);
            $pdf->MultiCell($w1 - 4, 4.1, pdf_txt($l[0]));
            $pdf->SetXY($x + $w1 + 2, $yy + $pad);
            $pdf->MultiCell($w - $w1 - 4, 4.1, pdf_txt($l[1]), 0, $cab && $cab[2] === 'R' ? 'R' : 'L');
            if (!empty($l['total'])) {
                $pdf->cor('marinho', 'D');
                $pdf->SetLineWidth(0.4);
                $pdf->Line($x, $yy, $x + $w, $yy);
                $pdf->SetLineWidth(0.2);
            } else {
                $pdf->cor('linha', 'D');
                $pdf->Line($x, $yy + $h, $x + $w, $yy + $h);
            }
        }
        $yy += $h;
    }
    return $yy - $y;
}

function ficha_pdf(array $c, array $d): void
{
    $pdf = new FichaPdf();
    $pdf->titulo_curso = (string) $c['nome'];
    $pdf->logotipo = __DIR__ . '/../../assets/img/gadgetmetodo-logotipo-pdf.png';
    $pdf->SetTitle('Ficha de curso: ' . $c['nome'], true);
    $pdf->SetAuthor('Gadgetmetodo, Lda.', true);
    $pdf->SetSubject((string) ($c['percurso'] ?: 'Formação'), true);
    $pdf->SetCreator('www.gadgetmetodo.pt', true);
    $pdf->AddPage();
    $L = 20.0;
    $W = FichaPdf::LARGURA;

    // Capa: logótipo, tipo de documento e data, faixa com percurso, título e tipologia, factos principais
    $pdf->Image($pdf->logotipo, $L, 15, 50);
    $pdf->fonte('montserrat', 'B', 7.5, 'petroleo');
    $pdf->rotulo($L + $W, 18.5, 'Ficha de curso', 0.45, 'R');
    $pdf->fonte('inter', '', 8, 'suave');
    $t = pdf_txt($d['hoje']);
    $pdf->Text($L + $W - $pdf->GetStringWidth($t), 23, $t);
    $pdf->cor('linha', 'D');
    $pdf->Line($L, 30, $L + $W, 30);

    $y = 36.0;
    $wt = $W - 3 - 18;
    $pdf->SetFont('montserrat', 'B', 19);
    $h_titulo = $pdf->linhas($wt, pdf_txt($c['nome'])) * 8.2;
    $h_faixa = 8 + ($c['percurso'] ? 6.5 : 0) + $h_titulo + ($c['tipologia'] ? 7 : 0) + 6;
    $pdf->cor('marinho', 'F');
    $pdf->Rect($L, $y, $W, $h_faixa, 'F');
    $pdf->cor('petroleo', 'F');
    $pdf->Rect($L, $y, 3, $h_faixa, 'F');
    $yy = $y + 8;
    if ($c['percurso']) {
        $pdf->fonte('montserrat', 'B', 7.2, 'claro');
        $percurso = (string) $c['percurso'];
        $pdf->SetFont('montserrat', 'B', 7.2);
        if ($pdf->GetStringWidth(pdf_txt(pdf_maiusculas($percurso))) + 0.3 * strlen($percurso) > $wt) {
            $percurso = explode(':', $percurso)[0];
        }
        $pdf->rotulo($L + 12, $yy + 2.6, $percurso, 0.3);
        $yy += 6.5;
    }
    $pdf->fonte('montserrat', 'B', 19, 'branco');
    $pdf->SetXY($L + 12, $yy);
    $pdf->MultiCell($wt, 8.2, pdf_txt($c['nome']));
    $yy += $h_titulo;
    if ($c['tipologia']) {
        $pdf->fonte('inter', '', 9.5, 'cinza');
        $pdf->Text($L + 12, $yy + 4.8, pdf_txt($c['tipologia'] . ($c['duracao'] ? '  ·  ' . $c['duracao'] : '')));
    }
    $y += $h_faixa;

    $factos = $d['factos'];
    if ($factos) {
        // Largura proporcional ao texto, nunca inferior ao rótulo; o excesso sai da coluna mais larga
        $pdf->SetFont('montserrat', 'B', 6.4);
        $minimos = [];
        foreach ($factos as $k => $v) {
            $r = pdf_txt(pdf_maiusculas($k));
            $minimos[$k] = $pdf->GetStringWidth($r) + 0.3 * strlen($r) + 9;
        }
        $pesos = array_map(function ($v) { return strlen($v) > 30 ? 2.3 : 1; }, $factos);
        $soma = array_sum($pesos);
        $larguras = array_map(function ($p) use ($W, $soma) { return $W * $p / $soma; }, $pesos);
        foreach ($larguras as $k => $lw) {
            if ($lw < $minimos[$k]) {
                $maior = array_search(max($larguras), $larguras, true);
                $larguras[$maior] -= $minimos[$k] - $lw;
                $larguras[$k] = $minimos[$k];
            }
        }
        $h = 0;
        foreach ($factos as $k => $v) {
            $pdf->SetFont('intersemi', '', 8.4);
            $h = max($h, $pdf->linhas($larguras[$k] - 8, pdf_txt($v)) * 4.2);
        }
        $h += 13;
        $pdf->cor('linha', 'D');
        $pdf->Rect($L, $y, $W, $h, 'D');
        $x = $L;
        foreach ($factos as $k => $v) {
            if ($x > $L) {
                $pdf->Line($x, $y, $x, $y + $h);
            }
            $pdf->fonte('montserrat', 'B', 6.4, 'petroleo');
            $pdf->rotulo($x + 4, $y + 6, $k, 0.3);
            $pdf->fonte('intersemi', '', 8.4, 'marinho');
            $pdf->SetXY($x + 4, $y + 8);
            $pdf->MultiCell($larguras[$k] - 8, 4.2, pdf_txt($v), 0, 'L');
            $x += $larguras[$k];
        }
        $y += $h;
    }
    $pdf->SetY($y + 9);

    // Secções numeradas: só entram as que têm conteúdo
    $n = 0;
    if ($c['descricao']) {
        $pdf->seccao(++$n, 'Sobre o curso');
        $pdf->paragrafos((string) $c['descricao']);
        $pdf->Ln(4);
    }
    if ($c['objetivo'] || $c['resultado']) {
        $caixas = array_filter(['Objetivo' => (string) $c['objetivo'], 'Resultado esperado' => (string) $c['resultado']]);
        $wc = count($caixas) === 2 ? ($W - 5) / 2 : $W;
        $partes = function ($titulo, $texto) {
            return [['t' => $titulo, 'f' => ['montserrat', 'B', 9.4], 'c' => 'marinho', 'lh' => 5], ['t' => $texto, 'f' => ['inter', '', 8.9], 'c' => 'texto', 'lh' => 4.6, 'antes' => 1.2]];
        };
        $h = 0;
        foreach ($caixas as $k => $v) {
            $h = max($h, $pdf->bloco($partes($k, $v), 0, 0, $wc - 10, false));
        }
        $h += 10;
        $pdf->seccao(++$n, 'Objetivo e resultado esperado', $h + 14);
        $y = $pdf->GetY();
        $x = $L;
        foreach ($caixas as $k => $v) {
            $pdf->cor('nevoa', 'F');
            $pdf->Rect($x, $y, $wc, $h, 'F');
            $pdf->cor('petroleo', 'F');
            $pdf->Rect($x, $y, $wc, 0.7, 'F');
            $pdf->bloco($partes($k, $v), $x + 5, $y + 5, $wc - 10);
            $x += $wc + 5;
        }
        $pdf->SetY($y + $h + 8);
    }

    $mods = $d['mods'];
    if ($mods) {
        $com_caso = (bool) array_filter(array_column($mods, 'caso_pratico'));
        $w1 = 14.0;
        $w3 = $com_caso ? 58.0 : 0.0;
        $w2 = $W - $w1 - $w3;
        $cabecalho = function () use ($pdf, $L, $W, $w1, $w2, $w3, $com_caso, $d) {
            $y = $pdf->GetY();
            $pdf->cor('profundo', 'F');
            $pdf->Rect($L, $y, $W, 7, 'F');
            $pdf->fonte('montserrat', 'B', 7.2, 'branco');
            $t = pdf_txt($d['u']);
            $pdf->Text($L + ($w1 - $pdf->GetStringWidth($t)) / 2, $y + 4.6, $t);
            $pdf->Text($L + $w1 + 3, $y + 4.6, pdf_txt('Tema e conteúdos'));
            if ($com_caso) {
                $pdf->Text($L + $w1 + $w2 + 3, $y + 4.6, pdf_txt('Caso prático' . ($d['min_caso'] ? ' (' . $d['min_caso'] . ' min)' : '')));
            }
            $pdf->SetY($y + 7);
        };
        $partes_modulo = function (array $m) {
            $tema = [
                ['t' => (string) $m['nome'], 'f' => ['inter', 'B', 9], 'c' => 'marinho', 'lh' => 4.5],
                ['t' => (string) $m['descricao'], 'f' => ['inter', '', 8.5], 'c' => 'texto', 'lh' => 4.2, 'antes' => 0.8],
            ];
            foreach (temas($m['temas']) as $k => $tm) {
                $tema[] = ['t' => $tm, 'f' => ['inter', '', 8.3], 'c' => 'texto', 'lh' => 4.1, 'marca' => true, 'antes' => $k === 0 ? 1.2 : 0.4];
            }
            return [$tema, [['t' => (string) $m['caso_pratico'], 'f' => ['inter', '', 8.3], 'c' => 'texto', 'lh' => 4.1]]];
        };
        $altura_modulo = function (array $m) use ($pdf, $partes_modulo, $w2, $w3, $com_caso) {
            [$tema, $caso] = $partes_modulo($m);
            return max($pdf->bloco($tema, 0, 0, $w2 - 6, false), $com_caso ? $pdf->bloco($caso, 0, 0, $w3 - 6, false) : 0, 12) + 6;
        };
        $pdf->seccao(++$n, 'Programa', 11 + 7 + $altura_modulo($mods[0]));
        $cabecalho();
        foreach ($mods as $i => $m) {
            [$tema, $caso] = $partes_modulo($m);
            $h = $altura_modulo($m);
            if (!$pdf->cabe($h)) {
                $pdf->AddPage();
                $cabecalho();
            }
            $y = $pdf->GetY();
            if ($i % 2 === 1) {
                $pdf->cor('zebra', 'F');
                $pdf->Rect($L, $y, $W, $h, 'F');
            }
            $pdf->fonte('montserrat', 'B', 11, 'petroleo');
            $t = (string) ($i + 1);
            $pdf->Text($L + ($w1 - $pdf->GetStringWidth($t)) / 2, $y + 6.6, $t);
            if ($m['horas']) {
                $pdf->fonte('inter', '', 7, 'suave');
                $t = pdf_txt(horas((string) $m['horas']));
                $pdf->Text($L + ($w1 - $pdf->GetStringWidth($t)) / 2, $y + 10.8, $t);
            }
            $pdf->bloco($tema, $L + $w1 + 3, $y + 3, $w2 - 6);
            if ($com_caso) {
                $pdf->bloco($caso, $L + $w1 + $w2 + 3, $y + 3, $w3 - 6);
            }
            $pdf->cor('linha', 'D');
            $pdf->Line($L, $y + $h, $L + $W, $y + $h);
            $pdf->SetY($y + $h);
        }
        $pdf->Ln(8);
    }

    // Sessão-tipo (barra de 60 minutos e tabela) e avaliação, lado a lado
    $estrutura = pares($c['estrutura'] ?? '');
    $avaliacao = pares($c['avaliacao'] ?? '');
    if ($estrutura || $avaliacao) {
        $wc = $estrutura && $avaliacao ? ($W - 6) / 2 : $W;
        $linhas_e = [];
        $total = 0;
        foreach ($estrutura as [$bloco, $min]) {
            $lab = stripos($bloco, 'laborat') !== false || stripos($bloco, 'caso') !== false;
            $linhas_e[] = [$bloco, $min, 'destaque' => $lab, 'negrito' => false];
            $total += (int) $min;
        }
        if ($total) {
            $linhas_e[] = ['Total', (string) $total, 'total' => true, 'negrito' => true];
        }
        $linhas_a = array_map(function ($p) { return [$p[0], $p[1], 'negrito' => false]; }, $avaliacao);
        $h_e = $estrutura ? 8 + 11 + pdf_tabela_pares($pdf, $linhas_e, 0, 0, $wc, $wc - 22, ['Bloco', 'Minutos', 'R'], false) : 0;
        $h_a = $avaliacao ? 8 + pdf_tabela_pares($pdf, $linhas_a, 0, 0, $wc, $wc * 0.5, ['Componente', 'Peso ou critério', 'L'], false) : 0;
        $pdf->seccao(++$n, $estrutura && $avaliacao ? 'Sessão-tipo e avaliação' : ($estrutura ? 'Como funciona uma sessão' : 'Avaliação'), max($h_e, $h_a) + 14);
        $y = $pdf->GetY();
        $x = $L;
        if ($estrutura) {
            $pdf->fonte('montserrat', 'B', 9, 'marinho');
            $pdf->Text($x, $y + 3.5, pdf_txt('Como funciona uma sessão (minutos)'));
            $bx = $x;
            foreach ($estrutura as $i => [$bloco, $min]) {
                if ($total <= 0 || (int) $min <= 0) {
                    continue;
                }
                $lab = stripos($bloco, 'laborat') !== false || stripos($bloco, 'caso') !== false;
                $sw = $wc * (int) $min / $total;
                $pdf->cor($lab ? 'petroleo' : 'seg' . ($i % 3), 'F');
                $pdf->Rect($bx, $y + 7, $sw, 7.5, 'F');
                if ($sw >= 4.5) {
                    $pdf->fonte('inter', 'B', 7.4, 'branco');
                    $t = (string) (int) $min;
                    $pdf->Text($bx + ($sw - $pdf->GetStringWidth($t)) / 2, $y + 12, $t);
                }
                $bx += $sw;
            }
            pdf_tabela_pares($pdf, $linhas_e, $x, $y + 18, $wc, $wc - 22, ['Bloco', 'Minutos', 'R'], true);
            $x += $wc + 6;
        }
        if ($avaliacao) {
            $pdf->fonte('montserrat', 'B', 9, 'marinho');
            $pdf->Text($x, $y + 3.5, pdf_txt('Avaliação'));
            pdf_tabela_pares($pdf, $linhas_a, $x, $y + 7, $wc, $wc * 0.5, ['Componente', 'Peso ou critério', 'L'], true);
        }
        $pdf->SetY($y + max($h_e, $h_a) + 8);
    }

    $requisitos = temas($c['requisitos']);
    if ($requisitos) {
        $partes = array_map(function ($r) { return ['t' => $r, 'f' => ['inter', '', 9], 'c' => 'texto', 'lh' => 4.7, 'marca' => true, 'antes' => 1]; }, $requisitos);
        $h = $pdf->bloco($partes, 0, 0, $W, false);
        $pdf->seccao(++$n, 'Requisitos técnicos e condições', min($h, 60) + 14);
        $pdf->bloco($partes, $L, $pdf->GetY(), $W);
        $pdf->SetY($pdf->GetY() + 8);
    }
    if ($c['investimento']) {
        $pdf->seccao(++$n, 'Investimento');
        $pdf->paragrafos((string) $c['investimento']);
        $pdf->Ln(4);
    }

    if ($c['enquadramento']) {
        $partes = [['t' => 'Enquadramento legal', 'f' => ['montserrat', 'B', 9], 'c' => 'marinho', 'lh' => 5]];
        foreach (temas($c['enquadramento']) as $p) {
            $partes[] = ['t' => $p, 'f' => ['inter', '', 8.2], 'c' => 'texto', 'lh' => 4.2, 'antes' => 1.2, 'alinhar' => 'J'];
        }
        $h = $pdf->bloco($partes, 0, 0, $W - 12, false) + 10;
        $pdf->espaco($h + 4);
        $y = $pdf->GetY();
        $pdf->cor('nevoa', 'F');
        $pdf->Rect($L, $y, $W, $h, 'F');
        $pdf->cor('suave', 'F');
        $pdf->Rect($L, $y, 0.8, $h, 'F');
        $pdf->bloco($partes, $L + 6, $y + 5, $W - 12);
        $pdf->SetY($y + $h + 9);
    }

    // Próximas edições e inscrições, com contactos
    $esq = [];
    foreach ($d['sessoes'] as $f) {
        $esq[] = ['t' => horario($f) . ' · ' . nome_modulo($f) . ' · ' . modalidade_e_local($f), 'f' => ['inter', '', 8.8], 'c' => 'texto', 'lh' => 4.5, 'marca' => true, 'antes' => 1];
    }
    if (!$esq) {
        $esq[] = ['t' => 'Ainda não há datas agendadas. Na página do curso pode fazer a pré-inscrição e receber o convite quando forem marcadas.', 'f' => ['inter', '', 8.8], 'c' => 'texto', 'lh' => 4.5];
    }
    $esq[] = ['t' => 'Programa, datas e inscrição online:', 'f' => ['inter', '', 8.8], 'c' => 'texto', 'lh' => 4.5, 'antes' => 3];
    $wl = 98.0;
    $wr = $W - $wl - 6;
    $contactos = [
        ['t' => 'Gadgetmetodo, Lda.', 'f' => ['inter', 'B', 8.6], 'c' => 'marinho', 'lh' => 4.4],
        ['t' => "Rua das Cruzes, 625, 4100-195 Porto, Portugal\ninfo@gadgetmetodo.pt\n+351 915 416 112 (chamada para a rede móvel nacional)\nwww.gadgetmetodo.pt", 'f' => ['inter', '', 8.4], 'c' => 'texto', 'lh' => 4.4, 'antes' => 0.6],
    ];
    $nota = [['t' => 'Documento informativo gerado em ' . $d['hoje'] . ' a partir da página do curso, onde se encontra sempre a versão atualizada.', 'f' => ['inter', '', 7.4], 'c' => 'suave', 'lh' => 3.8]];
    $h_esq = $pdf->bloco($esq, 0, 0, $wl, false) + 6 + 6 + $pdf->bloco($nota, 0, 0, $wl, false);
    $h_dir = $pdf->bloco($contactos, 0, 0, $wr - 10, false) + 16;
    $pdf->seccao(++$n, 'Próximas edições e inscrições', max($h_esq, $h_dir) + 16);
    $y = $pdf->GetY();
    $fim = $y + $pdf->bloco($esq, $L, $y, $wl);
    $pdf->fonte('inter', 'B', 8.8, 'petroleo');
    $pdf->SetXY($L, $fim + 0.5);
    $pdf->Cell($wl, 4.5, pdf_txt(preg_replace('#^https?://#', '', $d['link'])), 0, 1, 'L', false, $d['link']);
    $fim += 5 + 6;
    $fim += $pdf->bloco($nota, $L, $fim, $wl);
    $x = $L + $wl + 6;
    $pdf->cor('nevoa', 'F');
    $pdf->Rect($x, $y, $wr, $h_dir, 'F');
    $pdf->cor('petroleo', 'F');
    $pdf->Rect($x, $y, $wr, 0.7, 'F');
    $pdf->fonte('montserrat', 'B', 6.8, 'petroleo');
    $pdf->rotulo($x + 5, $y + 7, 'Informações e inscrições', 0.3);
    $pdf->bloco($contactos, $x + 5, $y + 10, $wr - 10);
    $pdf->SetY(max($fim, $y + $h_dir));

    $nome = 'Gadgetmetodo - Ficha de curso - ' . trim((string) preg_replace('/[:\/\\\\|?*"<>]+/', ' -', (string) $c['nome'])) . '.pdf';
    header('Cache-Control: no-cache');
    header('X-Robots-Tag: noindex');
    $pdf->Output('I', $nome, true);
}
