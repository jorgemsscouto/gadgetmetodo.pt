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
| `formulario/` | Envio do formulário de contactos (PHP); a configuração `config.php` fica fora do Git |
| `ferramentas/` | Geradores das páginas e do catálogo (não publicados) |
| `documentos/` | Catálogo de serviços em PDF e respetiva fonte (não publicados) |

## Preparação para pesquisa com inteligência artificial

- Todo o conteúdo está no HTML entregue pelo servidor, sem depender de JavaScript, para poder ser lido por qualquer rastreador.
- Cada página começa com um bloco "Em resumo": um parágrafo factual que responde diretamente a "o que é" e "quem faz".
- Dados estruturados schema.org em JSON-LD em todas as páginas: organização (`ProfessionalService`), website, página, migalhas de navegação, serviços, curso, metodologia (`HowTo`), pessoa e perguntas frequentes (`FAQPage`).
- Nome, morada e telefone escritos da mesma forma em todo o site.
- `robots.txt` aberto aos rastreadores de pesquisa e de IA (OpenAI, Anthropic, Perplexity, Google, Microsoft e Apple). Os robôs de treino de modelos também estão permitidos, por decisão de visibilidade da marca; estão num grupo separado para poderem ser fechados sem perder presença nas respostas.
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

O script pede o servidor, o utilizador, a pasta do site e a palavra-passe, que não fica guardada. Os valores por omissão correspondem ao alojamento atual na Amen: servidor `gadgf9.ftp.tb-hosting.com` e pasta `www`, que é a pasta pública do domínio principal (a pasta `subsites` destina-se a subdomínios). É necessária uma conta FTP com permissão de escrita. Usa ligação cifrada quando o servidor a suporta, envia apenas ficheiros novos ou alterados, não apaga nada no servidor e exclui `.git`, `README.md`, `.nojekyll` e o próprio script.

Com um programa de FTP (FileZilla, Cyberduck ou o gestor de ficheiros do alojamento), enviar o conteúdo desta pasta para a pasta pública do domínio (na Amen, `www`), exceto `.git`, `README.md`, `.nojekyll` e `publicar.sh`. O ficheiro `.htaccess` está oculto no Finder e tem de ser enviado.

Se a pasta pública tiver uma página por omissão do alojamento (por exemplo `index.php` ou `default.html`), apagá-la.

### Formulário de contacto

O formulário em `/contactos/` envia os pedidos para `formulario/enviar.php`, que precisa de PHP no alojamento. Cada pedido gera dois emails: o pedido para `info@gadgetmetodo.pt` (com cópia oculta opcional e resposta direta ao remetente) e uma confirmação de receção para quem o enviou. O campo de mensagem aceita ditado por voz nos navegadores que o suportam.

1. Confirmar que o alojamento executa PHP: `curl -s https://www.gadgetmetodo.pt/formulario/enviar.php` deve devolver uma resposta em JSON e não o código do ficheiro. Fazer esta verificação antes de enviar a configuração.
2. Criar a configuração com `./configurar-formulario.sh`, que pede os dados da caixa de email (servidor SMTP, porta, utilizador e palavra-passe) e o endereço em cópia oculta. Gera `formulario/config.php`, que fica fora do Git.
3. Publicar com `./publicar.sh`.

Proteções incluídas: campo isca para robôs, tempo mínimo de preenchimento, limite de 5 pedidos por hora por origem, validação no servidor, verificação da origem do pedido e bloqueio de injeção de cabeçalhos. Os testes foram feitos com um servidor de email local.

A pasta `documentos/` (catálogo e outros documentos) não é enviada para o site.

## Catálogo de serviços

`documentos/Gadgetmetodo-Catalogo-de-Servicos-2026.pdf` (16 páginas, A4 horizontal) é gerado a partir de `documentos/catalogo/catalogo.html`, que usa as fontes e o logótipo de `assets/`. Para alterar o texto, editar o HTML e voltar a gerar o PDF no Chrome: Imprimir, Guardar como PDF, margens "Nenhuma" e "Gráficos de fundo" ativado.

Depois de publicar:

1. Registar o site no Google Search Console e no Bing Webmaster Tools e submeter `https://www.gadgetmetodo.pt/sitemap.xml`. O índice do Bing é usado por vários assistentes de IA.
2. Criar ou atualizar o perfil da empresa no Google (Google Business Profile) e a página da empresa no LinkedIn, com o mesmo nome, morada e telefone. Acrescentar esses endereços ao campo `sameAs` dos dados estruturados.

## Pendentes antes de publicar

- [ ] **Logótipo oficial.** Os ficheiros em `assets/marca/` são uma reconstrução vetorial provisória feita a partir do Manual Gráfico, que exige a validação do desenho final em vetor. Quando existirem os ficheiros aprovados, substituir os SVG mantendo os nomes e voltar a gerar `favicon.svg`, `favicon.ico`, `apple-touch-icon.png`, os ícones em `assets/img/` e a imagem de partilha `assets/img/partilha.png`.
- [ ] **Identificação legal da sociedade.** O artigo 171.º do Código das Sociedades Comerciais exige que os sítios na Internet indiquem a conservatória do registo comercial, o número de matrícula e de identificação de pessoa coletiva (NIPC) e, nas sociedades por quotas, o capital social. O NIPC (513 978 011) já está no rodapé, na página "Sobre", nos dados estruturados e no catálogo; faltam a conservatória e o capital social.
- [ ] **Redes sociais**: quando existirem, acrescentar os endereços ao campo `sameAs` da organização em `ferramentas/gerar-site.mjs`.
- [x] **Email com domínio próprio**: o site usa `info@gadgetmetodo.pt`.
- [ ] **Exemplos de intervenção** (`experiencia/`): confirmar que os quatro casos podem ser publicados, mesmo sem nomes, e que descrevem trabalho realizado.
- [ ] **Perfil de Jorge Sá Couto**: confirmar a função a indicar (por exemplo, sócio-gerente) e, se desejado, acrescentar fotografia.
- [ ] **Política de privacidade**: confirmar o prazo de conservação de 12 meses para pedidos de contacto sem seguimento.

## Manutenção

- As páginas HTML, o `sitemap.xml` e os dados estruturados são gerados por `ferramentas/gerar-site.mjs`, que contém o texto de todas as páginas, o menu, o rodapé e os dados da empresa. Para alterar conteúdo: editar o gerador, atualizar as constantes `HOJE` e `HOJE_TXT` e correr `cd ferramentas && npm install && npm run site`. Editar diretamente os `index.html` funciona, mas a alteração perde-se na geração seguinte.
- O catálogo é gerado por `ferramentas/gerar-catalogo.mjs` (`npm run catalogo`), com os estilos em `ferramentas/catalogo.css`.
- As respostas das perguntas frequentes alimentam ao mesmo tempo o texto visível e os dados estruturados, por isso ficam sempre iguais.
- O `llms.txt` e o `robots.txt` são editados à mão.
- O `publicar.sh` avisa o Bing e outros motores aderentes ao IndexNow no fim de cada publicação. A chave está no ficheiro `<chave>.txt` na raiz do site e tem de continuar publicada.

## Licenças de terceiros

- Fontes Montserrat e Inter: SIL Open Font License 1.1 (`assets/fonts/LICENCA-OFL.txt`).
- Ícones Lucide (incorporados no HTML): licença ISC, © Lucide Icons and Contributors.
