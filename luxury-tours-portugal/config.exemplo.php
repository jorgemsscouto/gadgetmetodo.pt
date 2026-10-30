<?php
// Configuração do inquérito Luxury Tours Portugal.
// Copiar para config.php (na mesma pasta) e preencher. O config.php não entra no Git.

return [
    // Palavra-passe do painel (luxury-tours-portugal/painel/). Sem ela, o painel fica fechado.
    'senha_painel' => '',

    // Modo de demonstração: mostra os 6 idiomas no inquérito (com a indicação "protótipo"),
    // mesmo sem a revisão nativa concluída. Para o envio real, passar a false: só ficam visíveis
    // os idiomas marcados como revistos no painel (separador Idiomas).
    'demo' => true,

    // Último dia em que o inquérito aceita respostas (AAAA-MM-DD). Aparece na página e no email.
    'data_fecho' => '2026-12-15',

    // Endereço público do inquérito, usado nas ligações pessoais e nas imagens do email.
    // Vazio = calculado a partir do pedido (por exemplo https://www.gadgetmetodo.pt/luxury-tours-portugal/).
    'endereco' => '',

    // Política de privacidade do responsável pelo tratamento (o cliente). Vazio = sem ligação.
    'privacidade_url' => '',

    // true: a ligação geral (sem convite) também aceita respostas. false: só ligações pessoais.
    'acesso_publico' => true,

    // Pasta para a base de dados SQLite. Vazio = fora da pasta pública, se o alojamento o permitir;
    // caso contrário, luxury-tours-portugal/dados (protegida por .htaccess).
    'pasta_dados' => '',
];
