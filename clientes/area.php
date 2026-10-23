<?php
// Área de clientes: entrada, sessão e encaminhamento para as páginas de cada perfil.
// Perfil Gadgetmetodo (admin): vê e gere tudo. Perfil Cliente: vê apenas os dados do seu cliente.

declare(strict_types=1);

require __DIR__ . '/inc/nucleo.php';
require __DIR__ . '/inc/diagnostico.php';
require __DIR__ . '/inc/vistas.php';
require __DIR__ . '/inc/paginas-admin.php';
require __DIR__ . '/inc/paginas-cliente.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'");

iniciar_sessao();

if (!bd()) {
    http_response_code(503);
    pagina_erro('Área indisponível', 'A área de clientes está temporariamente indisponível. Contacte info@gadgetmetodo.pt.');
}

$p = (string) ($_GET['p'] ?? 'painel');
$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

if ($p === 'sair') {
    $_SESSION = [];
    session_regenerate_id(true);
    aviso('Sessão terminada.', 'info');
    header('Location: area.php', true, 303);
    exit;
}

if ($p === 'entrar' && $post) {
    verificar_csrf();
    $erro = entrar(texto('email', 160), is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '');
    if ($erro === null) {
        redirecionar('painel');
    }
    http_response_code(401);
    pagina_entrada($erro);
}

if (!utilizador()) {
    pagina_entrada();
}

if ($post) {
    verificar_csrf();
}

$comuns = ['inscrever' => 'pagina_inscrever', 'inscricao' => 'pagina_inscricao', 'conta' => 'pagina_conta'];
$rotas = eh_admin()
    ? ['painel' => 'admin_painel', 'calendario' => 'admin_calendario', 'formacao' => 'admin_formacao', 'cursos' => 'admin_cursos', 'curso' => 'admin_curso', 'inscricoes' => 'admin_inscricoes',
       'clientes' => 'admin_clientes', 'cliente' => 'admin_cliente', 'utilizadores' => 'admin_utilizadores', 'utilizador' => 'admin_utilizador',
       'pedidos' => 'admin_pedidos'] + $comuns
    : ['painel' => 'cliente_painel', 'calendario' => 'cliente_formacoes', 'inscricoes' => 'cliente_inscricoes'] + $comuns;

if (!isset($rotas[$p])) {
    negar();
}
$rotas[$p]();
