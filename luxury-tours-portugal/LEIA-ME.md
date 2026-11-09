# Inquérito Luxury Tours Portugal (protótipo)

Proposta para apresentar à Luxury Tours Portugal by Wine Tourism in Portugal: inquérito privado aos clientes sobre o próximo destino das viagens dedicadas ao vinho fora de Portugal. Projeto autónomo, sem ligação ao resto do site gadgetmetodo.pt; só partilha o alojamento.

- Inquérito: `https://www.gadgetmetodo.pt/luxury-tours-portugal/`
- Painel: `https://www.gadgetmetodo.pt/luxury-tours-portugal/painel/`

As páginas não são indexadas (`noindex`), não estão no `sitemap.xml` nem no `robots.txt`.

## Publicar

1. Copiar `config.exemplo.php` para `config.php` nesta pasta e definir `senha_painel` (palavra-passe de primeiro acesso).
2. Publicar o site como habitualmente (`./publicar.sh`). O `config.php` é enviado; o `config.exemplo.php`, este ficheiro e a pasta `dados/` não.
3. No painel, entrar com o email em branco e a `senha_painel`, e criar a conta de administrador. A partir daí, cada pessoa entra com o seu email e a `senha_painel` deixa de dar acesso.
4. No separador Clientes e convites, "Criar dados de demonstração" mostra o painel com números fictícios. Apagar antes do envio real.

Requisitos do alojamento: PHP 8 com SQLite (PDO). A extensão `intl` é opcional (datas e euros no email e no painel; sem ela, usa um formato simples). A base de dados fica fora da pasta pública quando o alojamento o permite; caso contrário, em `dados/`, protegida por `.htaccess`.

## Como funciona

| Parte | Ficheiros |
|---|---|
| Página do inquérito (por omissão, 6 passos e ordenação de até 3 regiões; configurável no painel) | `index.php`, `assets/js/inquerito.js`, `assets/css/inquerito.css` |
| Textos nos 7 idiomas (pt, en, es, fr, de, el, ar) | `i18n/*.json` (o inglês é a fonte; nenhum texto está no código), com as alterações feitas no painel por cima; `textos.php` entrega-os à página quando se muda de idioma |
| Gravação das respostas | `api.php` |
| Inquérito original (constantes), base de dados e idiomas | `inc/nucleo.php` |
| Estrutura configurável: passos, perguntas, tipos de resposta, opções, textos alterados, validação | `inc/estrutura.php` |
| Editor do inquérito no painel (separador Inquérito) | `inc/editor.php` |
| Painel, exportações e dados de demonstração | `painel/index.php`, `inc/painel.php` |
| Utilizadores internos e perfis | `inc/utilizadores.php` |
| Email de convite (assunto, HTML e texto) | `inc/email.php` |
| Mapas das regiões | `assets/img/mapas/*.svg`, gerados por `ferramentas/gerar-mapas-luxury-tours.mjs` |
| Nomes dos países em português | `inc/paises-pt.php` (ICU/CLDR) |

- As respostas guardam códigos internos neutros (`region_tuscany`, `intent_very_likely`), por isso o ranking e as exportações são iguais em todos os idiomas. O painel e as exportações mostram os rótulos em português.
- Cada resposta fica em JSON na coluna `respostas.dados` (pergunta => valor; nas de escolha múltipla e de ordenação, uma lista; a resposta "Outra" em `<pergunta>_other`). As respostas da versão anterior, em colunas fixas (`regiao_1`, `intencao`...), são convertidas automaticamente no primeiro acesso depois da atualização.
- Cada resposta guarda o idioma em que foi preenchida.
- Ranking ponderado: em cada pergunta de ordenação com N lugares, N pontos pelo 1.º lugar, N-1 pelo 2.º e assim por diante (3-2-1 nas regiões).
- Idioma inicial: escolha manual (`?lang=`), idioma do cliente na lista de convites e, nos restantes casos, inglês. O idioma do navegador não é usado. O seletor no topo muda o idioma sem perder as respostas.
- Árabe da direita para a esquerda (`dir="rtl"`), com propriedades CSS lógicas, setas e botões espelhados, e valores isolados com `<bdi>`.
- Valores em euros e datas formatados com `Intl.NumberFormat` e `Intl.DateTimeFormat` no idioma de quem responde. O árabe usa algarismos latinos (`ar-u-nu-latn` em `i18n/ar.json`); para algarismos árabes orientais, mudar para `ar`.
- Tipografia Noto alojada em `assets/fonts/` (licença OFL), sem pedidos a terceiros.

## Configurar o inquérito (separador Inquérito)

Só para administradores. As alterações aplicam-se de imediato à página do inquérito; a configuração fica na base de dados (tabela `definicoes`: `estrutura` e `textos_<idioma>`), não nos ficheiros.

- **Passos e perguntas**: lista dos passos pela ordem do inquérito, com o tipo de resposta, se é obrigatória, o número de opções, as respostas recolhidas e as traduções em falta. Cada pergunta pode subir, descer, mudar de passo, ser desativada (deixa de aparecer; as respostas mantêm-se) ou apagada (com respostas reais, pede confirmação). Os passos sobem, descem e, vazios, apagam-se; passos sem perguntas ativas não aparecem.
- **Nova pergunta**: título em português, tipo de resposta e passo (ou um passo novo no fim). As de escolha ficam desativadas até terem pelo menos 2 opções.
- **Tipos de resposta**: escolha única, escolha múltipla (com máximo opcional), ordenação por preferência (até N lugares, com cartões de nome, país ou detalhe e nota, e ícone do mapa opcional, com a pesquisa no Google Maps; ranking com pontos no painel), escala numérica (de 0 ou 1 até 2 a 10, com rótulos nos extremos; o painel mostra a média), texto curto e texto longo (máximo de caracteres opcional).
- **Em cada pergunta**: obrigatória, ativa, "Outra" com texto livre (nas de escolha), antetítulo com a 1.ª escolha da pergunta de ordenação (`{region}`), nota de privacidade, rótulo para o painel e as exportações, e as opções (ordem, texto, retirar, valores em euros com `{min}` e `{max}` formatados no idioma de quem responde, ou país, nota e pesquisa no mapa nas de ordenação).
- **Textos por idioma**: as abas "Textos em" editam o título, a ajuda, os textos das opções e os restantes textos de cada idioma, com o texto inglês como referência. O que faltar num idioma aparece em inglês (ou, sem inglês, em português) e a pergunta fica marcada "Falta" na lista. Um campo vazio volta ao texto original do ficheiro.
- **Textos gerais**: introdução (sobretítulo, título, texto e duração, por exemplo "Seis passos curtos, cerca de dois minutos."), agradecimento final e título da página, em cada idioma.
- **Repor o inquérito original**: volta aos passos, perguntas, opções e textos da proposta; as respostas mantêm-se.
- Mudar o tipo de uma pergunta ou retirar opções depois de haver respostas reais torna-as difíceis de comparar: o painel avisa e sugere desativar em vez de apagar. As exportações incluem também as perguntas desativadas, para não se perderem respostas.
- A identificação da ligação geral (nome, email, telemóvel e país) aparece sempre no último passo e não é configurável.

## Convite ou ligação geral

- Com a ligação pessoal do convite (`?t=...`), o cliente já está identificado pela lista e não preenche dados pessoais.
- Pela ligação geral, o último passo pede nome, email, telemóvel (com indicativo do país) e país de origem, todos obrigatórios. O país é guardado com o código ISO 3166-1 e mostrado no idioma de quem responde.

## Mapas das regiões

Cada região tem um ícone (i). Com rato, passar por cima mostra o mapa do país com a região em destaque, e clicar abre o Google Maps num separador novo. Em ecrãs tácteis, o primeiro toque mostra o mapa e a ligação "Abrir o mapa" abre o Google Maps.

Limites usados no destaque (dados de domínio público): Natural Earth para os países e para as províncias e departamentos (Toscana e Piemonte pelas províncias da região; Bordéus = Gironde; Borgonha = Côte-d'Or, Saône-et-Loire e Yonne; Champagne = Marne e Aube; Rioja = La Rioja; Mendoza = província de Mendoza) e U.S. Census Bureau para o condado de Napa. Ribera del Duero e Mosel não têm limite administrativo próprio: o destaque é uma faixa ao longo do rio, traçada a partir de localidades ribeirinhas. Stellenbosch é um círculo aproximado de 15 km e Santorini é a própria ilha. São representações aproximadas, adequadas a uma miniatura.

Para gerar de novo: `cd ferramentas && npm install && npm run mapas-luxury-tours`.

## Utilizadores do painel

| Perfil | Acesso |
|---|---|
| Administrador | Tudo, incluindo a configuração do inquérito (separador Inquérito), utilizadores, revisão dos idiomas e eliminação de todos os dados |
| Gestor | Resumo, respostas, clientes e convites (importação, ligações pessoais, envio em série), emails e dados de demonstração |
| Consulta | Só leitura: resumo, respostas e exportações das respostas e do ranking |

Palavras-passe com pelo menos 10 caracteres, guardadas com `password_hash`. Desativar um utilizador corta o acesso de imediato. Existe sempre pelo menos um administrador ativo.

## Lista de clientes e envio

CSV com as colunas `name`, `email`, `language` (pt, en, es, fr, de, el, ar; "gr" é lido como grego), `form` (m ou f, para a saudação; opcional), `country` e `reference`. Aceita ficheiros do Excel com `;` ou `,`, em UTF-8 ou Windows-1252. O email identifica o cliente; uma nova importação atualiza só as colunas presentes no ficheiro. Sem idioma válido, o cliente recebe o email e o inquérito em inglês.

O painel exporta o CSV para envio em série (mail merge) com `name`, `email`, `language`, `greeting`, `subject`, `link` e `deadline`, e os modelos do email por idioma, com `{{greeting}}` e `{{link}}` como campos de fusão.

## Antes do envio real

- Revisão das traduções por falantes nativos, pelo menos do grego e do árabe. Cada ficheiro `i18n/*.json` está marcado "PENDING NATIVE REVIEW". No painel (separador Idiomas), marcar cada idioma como revisto.
- Em `config.php`, `demo` a `false`: só os idiomas revistos ficam visíveis.
- Definir `privacidade_url` (política de privacidade do cliente) e validar os textos de privacidade com o responsável pelo tratamento, sobretudo agora que a ligação geral recolhe email e telemóvel. A Gadgetmetodo atua como subcontratante se alojar os dados.
- Confirmar com o cliente a lista de regiões, os escalões de orçamento e a data de fecho (as regiões, os escalões e as restantes perguntas ajustam-se no separador Inquérito).
- Depois de alterar ou acrescentar perguntas, completar as traduções nos idiomas publicados (a lista do separador Inquérito indica o que falta).
