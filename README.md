# gadgetmetodo.pt

Website institucional da **Gadgetmetodo, Lda.**: consultoria em organização e métodos, inteligência artificial aplicada, proteção de dados e estratégia.

Site estático em HTML e CSS, sem dependências nem passo de compilação. Funciona em qualquer alojamento que sirva ficheiros (alojamento partilhado, GitHub Pages, Netlify, Cloudflare Pages ou outro).

## Estrutura

| Caminho | Conteúdo |
|---|---|
| `index.html` | Página inicial |
| `servicos/` | Visão geral e uma página por área de serviço |
| `abordagem/`, `experiencia/`, `sobre/` | Metodologia, exemplos de intervenção, empresa |
| `perguntas-frequentes/`, `contactos/`, `privacidade/` | Perguntas frequentes, contactos, política de privacidade |
| `404.html` | Página de erro |
| `assets/css/site.css` | Estilos (paleta e tipografia do Manual Gráfico) |
| `assets/js/site.js` | Apenas o menu em ecrãs pequenos; o conteúdo não depende de JavaScript |
| `assets/fonts/` | Montserrat e Inter alojadas no próprio site (licença OFL) |
| `assets/marca/` | Logótipo e símbolo em SVG e PNG |
| `robots.txt`, `sitemap.xml`, `llms.txt` | Indexação por motores de pesquisa e assistentes de IA |
| `.htaccess` | Regras para alojamentos Apache ou LiteSpeed |

## Preparação para pesquisa com inteligência artificial

- Todo o conteúdo está no HTML entregue pelo servidor, sem depender de JavaScript, para poder ser lido por qualquer rastreador.
- Cada página começa com um bloco "Em resumo": um parágrafo factual que responde diretamente a "o que é" e "quem faz".
- Dados estruturados schema.org em JSON-LD em todas as páginas: organização (`ProfessionalService`), website, página, migalhas de navegação, serviços, curso, metodologia (`HowTo`), pessoa e perguntas frequentes (`FAQPage`).
- Nome, morada e telefone escritos da mesma forma em todo o site.
- `robots.txt` aberto aos rastreadores de pesquisa e de IA (OpenAI, Anthropic, Perplexity, Google, Microsoft e Apple), com o grupo de treino de modelos separado para poder ser fechado sem perder presença nas respostas.
- `llms.txt` com o resumo da empresa e as ligações principais. É uma convenção proposta e ainda sem adoção garantida pelos grandes motores; não tem custo nem risco.
- `sitemap.xml` com data de atualização e datas visíveis "Atualizado em" nas páginas.
- Sem cookies, sem ferramentas de análise e sem conteúdos de terceiros.

## Publicação

O domínio canónico definido nas páginas é `https://www.gadgetmetodo.pt`. O alojamento deve redirecionar `gadgetmetodo.pt` para `www.gadgetmetodo.pt`, ou o domínio canónico tem de ser alterado em todas as páginas, no `sitemap.xml`, no `robots.txt` e no `llms.txt`.

As ligações internas usam caminhos absolutos (`/servicos/`), por isso o site tem de ser servido na raiz do domínio.

### Publicar por FTP

Com o script incluído (requer `lftp`; no macOS: `brew install lftp`):

```
./publicar.sh
```

O script pede o servidor, o utilizador, a pasta do site (por omissão `public_html`) e a palavra-passe, que não fica guardada. Usa ligação cifrada quando o servidor a suporta, envia apenas ficheiros novos ou alterados, não apaga nada no servidor e exclui `.git`, `README.md`, `.nojekyll` e o próprio script.

Com um programa de FTP (FileZilla, Cyberduck ou o gestor de ficheiros do alojamento), enviar o conteúdo desta pasta para a pasta pública do domínio, exceto `.git`, `README.md`, `.nojekyll` e `publicar.sh`. O ficheiro `.htaccess` está oculto no Finder e tem de ser enviado.

Se a pasta pública tiver uma página por omissão do alojamento (por exemplo `index.php` ou `default.html`), apagá-la.

Depois de publicar:

1. Registar o site no Google Search Console e no Bing Webmaster Tools e submeter `https://www.gadgetmetodo.pt/sitemap.xml`. O índice do Bing é usado por vários assistentes de IA.
2. Criar ou atualizar o perfil da empresa no Google (Google Business Profile) e a página da empresa no LinkedIn, com o mesmo nome, morada e telefone. Acrescentar esses endereços ao campo `sameAs` dos dados estruturados.

## Pendentes antes de publicar

- [ ] **Logótipo oficial.** Os ficheiros em `assets/marca/` são uma reconstrução vetorial provisória feita a partir do Manual Gráfico, que exige a validação do desenho final em vetor. Quando existirem os ficheiros aprovados, substituir os SVG mantendo os nomes e voltar a gerar `favicon.svg`, `favicon.ico`, `apple-touch-icon.png`, os ícones em `assets/img/` e a imagem de partilha `assets/img/partilha.png`.
- [ ] **Identificação legal da sociedade.** O artigo 171.º do Código das Sociedades Comerciais exige que os sítios na Internet indiquem a conservatória do registo comercial, o número de matrícula e de identificação de pessoa coletiva (NIPC) e, nas sociedades por quotas, o capital social. Acrescentar estes dados ao rodapé e à página "Sobre".
- [ ] **Email com domínio próprio** (por exemplo `geral@gadgetmetodo.pt`) em vez de Gmail, por credibilidade e controlo dos dados.
- [ ] **Exemplos de intervenção** (`experiencia/`): confirmar que os quatro casos podem ser publicados, mesmo sem nomes, e que descrevem trabalho realizado.
- [ ] **Perfil de Jorge Sá Couto**: confirmar a função a indicar (por exemplo, sócio-gerente) e, se desejado, acrescentar fotografia.
- [ ] **Política de privacidade**: confirmar o prazo de conservação de 12 meses para pedidos de contacto sem seguimento.

## Manutenção

- O cabeçalho e o rodapé estão repetidos em cada página. Uma alteração ao menu ou aos contactos tem de ser feita em todos os ficheiros `index.html` e no `404.html`.
- Ao alterar conteúdo, atualizar a data "Atualizado em" e o `dateModified` dos dados estruturados da página, o `<lastmod>` no `sitemap.xml` e, se for caso disso, o `llms.txt`.
- As respostas das perguntas frequentes aparecem no texto da página e nos dados estruturados; devem ser alteradas nos dois sítios.

## Licenças de terceiros

- Fontes Montserrat e Inter: SIL Open Font License 1.1 (`assets/fonts/LICENCA-OFL.txt`).
- Ícones Lucide (incorporados no HTML): licença ISC, © Lucide Icons and Contributors.
