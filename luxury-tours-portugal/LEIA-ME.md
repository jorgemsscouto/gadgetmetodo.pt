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
| Página do inquérito (6 passos, ordenação de até 3 regiões) | `index.php`, `assets/js/inquerito.js`, `assets/css/inquerito.css` |
| Textos nos 7 idiomas (pt, en, es, fr, de, el, ar) | `i18n/*.json` (o inglês é a fonte; nenhum texto está no código) |
| Gravação das respostas | `api.php` |
| Estrutura, códigos das opções e base de dados | `inc/nucleo.php` |
| Painel, exportações e dados de demonstração | `painel/index.php`, `inc/painel.php` |
| Utilizadores internos e perfis | `inc/utilizadores.php` |
| Email de convite (assunto, HTML e texto) | `inc/email.php` |
| Mapas das regiões | `assets/img/mapas/*.svg`, gerados por `ferramentas/gerar-mapas-luxury-tours.mjs` |
| Nomes dos países em português | `inc/paises-pt.php` (ICU/CLDR) |

- As respostas guardam códigos internos neutros (`region_tuscany`, `intent_very_likely`), por isso o ranking e as exportações são iguais em todos os idiomas. O painel e as exportações mostram os rótulos em português.
- Cada resposta guarda o idioma em que foi preenchida.
- Ranking ponderado: 3 pontos pelo 1.º lugar, 2 pelo 2.º e 1 pelo 3.º.
- Idioma inicial: escolha manual (`?lang=`), idioma do cliente na lista de convites e, nos restantes casos, inglês. O idioma do navegador não é usado. O seletor no topo muda o idioma sem perder as respostas.
- Árabe da direita para a esquerda (`dir="rtl"`), com propriedades CSS lógicas, setas e botões espelhados, e valores isolados com `<bdi>`.
- Valores em euros e datas formatados com `Intl.NumberFormat` e `Intl.DateTimeFormat` no idioma de quem responde. O árabe usa algarismos latinos (`ar-u-nu-latn` em `i18n/ar.json`); para algarismos árabes orientais, mudar para `ar`.
- Tipografia Noto alojada em `assets/fonts/` (licença OFL), sem pedidos a terceiros.

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
| Administrador | Tudo, incluindo utilizadores, revisão dos idiomas e eliminação de todos os dados |
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
- Confirmar com o cliente a lista de regiões, os escalões de orçamento e a data de fecho.
