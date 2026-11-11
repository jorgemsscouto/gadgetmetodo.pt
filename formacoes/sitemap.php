<?php
// Mapa do site das páginas públicas dos cursos (/formacoes/curso.php?c=N). Os cursos vêm da base de dados, por isso
// não podem estar no sitemap.xml estático gerado por ferramentas/gerar-site.mjs. Referido no robots.txt e usado pelo
// publicar.sh para avisar os motores de pesquisa (IndexNow).

declare(strict_types=1);

require __DIR__ . '/../clientes/inc/publico.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: no-cache');

$cursos = bd() ? consulta('SELECT id FROM cursos WHERE ativo = 1 ORDER BY id') : [];
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($cursos as $c) {
    echo '  <url>' . "\n" . '    <loc>' . h(SITE_CANONICO . link_curso_publico($c)) . '</loc>' . "\n" . '    <priority>0.7</priority>' . "\n" . '  </url>' . "\n";
}
echo '</urlset>' . "\n";
