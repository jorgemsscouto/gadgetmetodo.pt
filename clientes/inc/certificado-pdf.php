<?php
// Certificado de frequência em PDF (FPDF), no modelo da Gadgetmetodo: A4 horizontal, painel escuro à esquerda com o
// logótipo, o programa e o percurso, e o texto do certificado centrado à direita. Imagens em clientes/inc/certificado/.

declare(strict_types=1);

require_once __DIR__ . '/ficha-pdf.php';

class CertificadoPdf extends FPDF
{
    const CORES = [
        'marinho' => [10, 15, 38], 'texto' => [43, 47, 54], 'cinza' => [85, 85, 85], 'suave' => [102, 102, 102],
        'verde' => [95, 194, 188], 'branco' => [255, 255, 255], 'claro' => [201, 211, 224],
    ];

    public function __construct()
    {
        parent::__construct('L', 'mm', 'A4');
        $dir = __DIR__ . '/../../formulario/lib/FPDF/fontes/';
        $this->AddFont('inter', '', 'inter-400.json', $dir);
        $this->AddFont('inter', 'B', 'inter-700.json', $dir);
        $this->AddFont('montserrat', '', 'montserrat-600.json', $dir);
        $this->AddFont('montserrat', 'B', 'montserrat-700.json', $dir);
        $this->SetMargins(0, 0, 0);
        $this->SetAutoPageBreak(false);
        $this->cMargin = 0;
    }

    public function fonte(string $familia, string $estilo, float $tamanho, string $cor): void
    {
        $this->SetFont($familia, $estilo, $tamanho);
        [$r, $g, $b] = self::CORES[$cor];
        $this->SetTextColor($r, $g, $b);
    }

    // Texto centrado em $centro; reduz o tamanho da letra até caber em $largura
    public function centrado(float $centro, float $y, string $texto, float $largura, string $familia, string $estilo, float $tamanho, string $cor): void
    {
        $t = pdf_txt($texto);
        $this->fonte($familia, $estilo, $tamanho, $cor);
        while ($this->GetStringWidth($t) > $largura && $tamanho > 7) {
            $tamanho -= 0.5;
            $this->SetFontSize($tamanho);
        }
        $this->Text($centro - $this->GetStringWidth($t) / 2, $y, $t);
    }

    // Rótulo em maiúsculas com espaçamento entre letras
    public function rotulo(float $x, float $y, string $texto): void
    {
        $this->_out(sprintf('%.3F Tc', 0.25 * $this->k));
        $this->Text($x, $y, pdf_txt(pdf_maiusculas(function_exists("tr") ? tr($texto) : $texto)));
        $this->_out('0 Tc');
    }
}

// PDF do certificado (conteúdo). $c: linha de certificados; $ctx: contexto_certificado() da inscrição.
function gerar_pdf_certificado(array $c, array $ctx): string
{
    $pdf = new CertificadoPdf();
    $pdf->SetTitle(pdf_txt('Certificado de Frequência n.º ' . numero_certificado($c)));
    $pdf->SetAuthor(pdf_txt(CERTIFICADO_ENTIDADE));
    $pdf->SetCreator('gadgetmetodo.pt');
    $pdf->AddPage();
    $dir = __DIR__ . '/certificado/';

    // Painel à esquerda: logótipo, programa e percurso
    $px = 8.8;
    $pw = 75.9;
    $pdf->Image($dir . 'painel.png', $px, 10.6, $pw, 182.6);
    $pdf->Image($dir . 'logotipo-negativo.png', $px + 8.5, 16, 52);
    $x = $px + 8.5;
    $lw = $pw - 17;
    $y = 42;
    if ($ctx['programa']) {
        $pdf->fonte('inter', 'B', 8, 'verde');
        $pdf->rotulo($x, $y, 'Programa');
        $y += 3;
        foreach ($ctx['programa'] as $n => $tema) {
            $pdf->fonte('inter', 'B', 9, 'verde');
            $pdf->Text($x, $y + 3.6, (string) ($n + 1));
            $pdf->fonte('inter', '', 9, 'branco');
            $pdf->SetXY($x + 5, $y);
            $pdf->MultiCell($lw - 5, 4.6, pdf_txt($tema), 0, 'L');
            $y = $pdf->GetY() + 0.6;
        }
    }
    $y = max($y + 12, 116);
    if ($ctx['percurso'] !== '') {
        $pdf->fonte('inter', 'B', 8, 'verde');
        $pdf->rotulo($x, $y, 'Percurso');
        $pdf->fonte('inter', '', 10.5, 'branco');
        $pdf->SetXY($x, $y + 1.8);
        $pdf->MultiCell($lw - 6, 5, pdf_txt($ctx['percurso']), 0, 'L');
        $y = $pdf->GetY() + 5;
    }
    $pdf->fonte('inter', '', 8, 'claro');
    $linha = implode('  |  ', array_filter([$ctx['posicao'], $c['sessoes'] . ($c['sessoes'] == 1 ? ' sessão' : ' sessões')]));
    $pdf->Text($x, $y, pdf_txt($linha));
    $pdf->Text($x, 185, 'www.gadgetmetodo.pt');

    // Corpo
    $centro = 186;
    $largura = 185;
    $pdf->fonte('inter', '', 7.5, 'suave');
    $t = pdf_txt('Certificado n.º ' . numero_certificado($c));
    $pdf->Text(280 - $pdf->GetStringWidth($t), 19, $t);
    $pdf->centrado($centro, 42, 'Certificado de Frequência', $largura, 'montserrat', 'B', 26, 'marinho');
    $pdf->centrado($centro, 64, (string) $c['nome'], $largura, 'montserrat', 'B', 17, 'marinho');
    if (($documento = linha_documento($c)) !== '') {
        $pdf->centrado($centro, 71, $documento, $largura, 'inter', '', 9.5, 'cinza');
    }
    $pdf->centrado($centro, 88, 'frequentou o curso', $largura, 'inter', '', 12, 'texto');
    $pdf->centrado($centro, 102, $ctx['curso'], $largura, 'montserrat', 'B', 20, 'marinho');
    $n = (int) $c['sessoes'];
    $pdf->centrado($centro, 118, 'promovido pela ' . CERTIFICADO_ENTIDADE . ', composto por ' . $n . ($n === 1 ? ' sessão,' : ' sessões,'), $largura, 'inter', '', 11, 'texto');
    $periodo = $n > 1 && date('Y-m-d', strtotime($ctx['inicio'])) !== date('Y-m-d', strtotime($ctx['fim']))
        ? 'realizado entre ' . data_curta($ctx['inicio']) . ' e ' . data_curta($ctx['fim']) : 'realizado em ' . data_curta($ctx['inicio']);
    $pdf->centrado($centro, 124.5, $periodo . ', com assiduidade de ' . (int) $c['presencas'] . ($n === 1 ? ' da sessão.' : ' das ' . $n . ' sessões.'), $largura, 'inter', '', 11, 'texto');
    $pdf->centrado($centro, 135, CERTIFICADO_LOCAL . ', ' . data_curta((string) $c['emitido']), $largura, 'inter', 'B', 11, 'marinho');

    // Assinatura
    $pdf->centrado($centro, 166, (string) ($c['formador'] ?? '') ?: CERTIFICADO_FORMADOR, $largura, 'inter', 'B', 11, 'marinho');
    $pdf->centrado($centro, 171.5, (string) ($c['formador_dados'] ?? '') ?: CERTIFICADO_FORMADOR_DADOS, $largura, 'inter', '', 9, 'cinza');
    $pdf->centrado($centro, 176.5, CERTIFICADO_ENTIDADE, $largura, 'inter', '', 9, 'cinza');
    return $pdf->Output('S');
}
