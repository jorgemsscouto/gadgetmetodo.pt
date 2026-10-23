<?php
// Ficha do curso (/formacoes/ficha.php?c=ID): é sempre o PDF gerado no servidor (clientes/inc/ficha-pdf.php),
// com as margens no próprio documento, para não depender das opções de impressão do navegador.
// Os parâmetros antigos (&pdf=1, &imprimir=1), em ligações já enviadas, são ignorados.

declare(strict_types=1);

require_once __DIR__ . '/publico.php';

const MESES_FICHA = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

// Dados da ficha, lidos da base de dados (os mesmos da página pública do curso)
function dados_ficha(array $c): array
{
    $mods = modulos_curso((int) $c['id']);
    $factos = factos_curso($c, $mods);
    unset($factos['Tipologia']);
    return [
        'mods' => $mods, 'u' => unidade($c), 'min_caso' => minutos_caso($c), 'factos' => $factos,
        'sessoes' => consulta("SELECT * FROM formacoes WHERE curso_id = ? AND inicio >= ? AND estado = 'aberta' ORDER BY inicio LIMIT 8", [$c['id'], date('Y-m-d 00:00:00')]),
        'link' => SITE_CANONICO . link_curso_publico($c),
        'hoje' => date('j') . ' de ' . MESES_FICHA[(int) date('n')] . ' de ' . date('Y'),
    ];
}

function ficha_curso(): void
{
    $c = bd() ? linha_bd('SELECT * FROM cursos WHERE id = ? AND ativo = 1', [(int) ($_GET['c'] ?? 0)]) : null;
    if (!$c) {
        http_response_code(404);
        header('Cache-Control: no-cache');
        header('X-Robots-Tag: noindex');
        echo '<!doctype html><html lang="pt-PT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Ficha não encontrada | Gadgetmetodo, Lda.</title><link rel="stylesheet" href="' . h(versao_ficheiro('/assets/css/site.css')) . '"></head>'
            . '<body><main class="secao"><div class="contentor estreito"><h1>Ficha não encontrada</h1><p>Este curso não existe ou já não está disponível.</p><p><a class="botao" href="/formacoes/">Ver a área de formação</a></p></div></main></body></html>';
        return;
    }
    require_once __DIR__ . '/ficha-pdf.php';
    ficha_pdf($c, dados_ficha($c));
}
