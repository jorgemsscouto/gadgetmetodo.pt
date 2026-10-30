# Inquérito Luxury Tours Portugal (protótipo)

Proposta para apresentar à Luxury Tours Portugal by Wine Tourism in Portugal: inquérito privado aos clientes sobre o próximo destino das viagens dedicadas ao vinho fora de Portugal. Projeto autónomo, sem ligação ao resto do site gadgetmetodo.pt; só partilha o alojamento.

- Inquérito: `https://www.gadgetmetodo.pt/luxury-tours-portugal/`
- Painel: `https://www.gadgetmetodo.pt/luxury-tours-portugal/painel/`

As páginas não são indexadas (`noindex`), não estão no `sitemap.xml` nem no `robots.txt`.

## Publicar

1. Copiar `config.exemplo.php` para `config.php` nesta pasta e definir `senha_painel`.
2. Publicar o site como habitualmente (`./publicar.sh`). O `config.php` é enviado; o `config.exemplo.php`, este ficheiro e a pasta `dados/` não.
3. No painel, separador Clientes e convites, "Criar dados de demonstração" para mostrar o painel com números fictícios. Apagar antes do envio real.

Requisitos do alojamento: PHP 8 com SQLite (PDO). A extensão `intl` é opcional (datas e euros no email e no painel; sem ela, usa um formato simples). A base de dados fica fora da pasta pública quando o alojamento o permite; caso contrário, em `dados/`, protegida por `.htaccess`.

## Como funciona

| Parte | Ficheiros |
|---|---|
| Página do inquérito (6 passos, ordenação de até 3 regiões) | `index.php`, `assets/js/inquerito.js`, `assets/css/inquerito.css` |
| Textos nos 6 idiomas (pt, en, es, fr, el, ar) | `i18n/*.json` (o inglês é a fonte; nenhum texto está no código) |
| Gravação das respostas | `api.php` |
| Estrutura, códigos das opções e base de dados | `inc/nucleo.php` |
| Painel, exportações e dados de demonstração | `painel/index.php`, `inc/painel.php` |
| Email de convite (assunto, HTML e texto) | `inc/email.php` |

- As respostas guardam códigos internos neutros (`region_tuscany`, `intent_very_likely`), por isso o ranking e as exportações são iguais em todos os idiomas. O painel e as exportações mostram os rótulos em português.
- Cada resposta guarda o idioma em que foi preenchida.
- Ranking ponderado: 3 pontos pelo 1.º lugar, 2 pelo 2.º e 1 pelo 3.º.
- Idioma inicial: escolha manual (`?lang=`), idioma do cliente na lista, idioma do navegador, inglês. O seletor no topo muda o idioma sem perder as respostas.
- Árabe da direita para a esquerda (`dir="rtl"`), com propriedades CSS lógicas, setas e botões espelhados, e valores isolados com `<bdi>`.
- Valores em euros e datas formatados com `Intl.NumberFormat` e `Intl.DateTimeFormat` no idioma de quem responde. O árabe usa algarismos latinos (`ar-u-nu-latn` em `i18n/ar.json`); para algarismos árabes orientais, mudar para `ar`.
- Tipografia Noto (Noto Sans, Noto Serif Display, Noto Sans Arabic, Noto Naskh Arabic), alojada em `assets/fonts/` (licença OFL), sem pedidos a terceiros.

## Lista de clientes e envio

CSV com as colunas `name`, `email`, `language` (pt, en, es, fr, el, ar; "gr" é lido como grego), `form` (m ou f, para a saudação; opcional), `country` e `reference`. Aceita ficheiros do Excel com `;` ou `,`, em UTF-8 ou Windows-1252. O email identifica o cliente; uma nova importação atualiza só as colunas presentes no ficheiro.

O painel exporta o CSV de mail merge (`name`, `email`, `language`, `greeting`, `subject`, `link`, `deadline`) e os modelos do email por idioma, com `{{greeting}}` e `{{link}}` como campos de fusão.

## Antes do envio real

- Revisão das traduções por falantes nativos, pelo menos do grego e do árabe. Cada ficheiro `i18n/*.json` está marcado "PENDING NATIVE REVIEW". No painel (separador Idiomas), marcar cada idioma como revisto.
- Em `config.php`, `demo` a `false`: só os idiomas revistos ficam visíveis.
- Definir `privacidade_url` (política de privacidade do cliente) e validar o texto de privacidade com o responsável pelo tratamento. A Gadgetmetodo atua como subcontratante se alojar os dados.
- Confirmar com o cliente a lista de regiões, os escalões de orçamento e a data de fecho.
