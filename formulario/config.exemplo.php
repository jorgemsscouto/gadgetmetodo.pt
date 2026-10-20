<?php
// Configuração do formulário de contacto.
// Copiar para config.php e preencher (ou usar ./configurar-formulario.sh).
// O config.php não entra no Git porque contém a palavra-passe do email.

return [
    // Remetente dos emails: deve ser uma caixa do próprio domínio para passar as verificações anti-spam
    'remetente_email' => 'info@gadgetmetodo.pt',
    'remetente_nome'  => 'Gadgetmetodo, Lda.',

    // Caixa que recebe os pedidos
    'destinatario' => 'info@gadgetmetodo.pt',

    // Cópia oculta (BCC) dos pedidos; vazio para não usar
    'bcc' => '',

    // Servidor de envio (SMTP) da caixa remetente.
    // Com 'host' vazio, usa a função mail() do alojamento (menos fiável na entrega).
    'smtp' => [
        'host'       => '',
        'porta'      => 465,
        'seguranca'  => 'ssl', // 'ssl' (porta 465), 'tls' (porta 587) ou '' (sem cifra)
        'utilizador' => 'info@gadgetmetodo.pt',
        'senha'      => '',
    ],

    // Endereços de onde o formulário pode ser submetido
    'origens' => ['https://www.gadgetmetodo.pt', 'https://gadgetmetodo.pt'],

    // Endereço do site, usado nas ligações e no logótipo dos emails
    'site' => 'https://www.gadgetmetodo.pt',
];
