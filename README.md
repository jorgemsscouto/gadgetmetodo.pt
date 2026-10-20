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
| `clientes/` | Área de clientes (PHP): calendário de formações, inscrições com diagnóstico de IA e gestão de acessos |
| `assets/css/area.css` | Estilos próprios da área de clientes (só carregados nessas páginas) |
| `ferramentas/` | Geradores das páginas e do catálogo (não publicados) |
| `documentos/` | Catálogo de serviços em PDF e respetiva fonte (não publicados) |

## Preparação para pesquisa com inteligência artificial

- Todo o conteúdo está no HTML entregue pelo servidor, sem depender de JavaScript, para poder ser lido por qualquer rastreador.
- Cada página começa com um bloco "Em resumo": um parágrafo factual que responde diretamente a "o que é" e "quem faz".
- Dados estruturados schema.org em JSON-LD em todas as páginas: organização (`ProfessionalService`), website, página, migalhas de navegação, serviços, curso, metodologia (`ItemList`), pessoa e perguntas frequentes (`FAQPage`).
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

Na primeira execução, o script pede o servidor, o utilizador, a pasta do site e a palavra-passe, e guarda-os depois de uma publicação bem-sucedida: a palavra-passe nas Chaves (Keychain) do macOS e o resto em `.publicar.conf`. Nas execuções seguintes não pede nada. Estes ficheiros ficam fora do Git e nunca são enviados para o site. Se a palavra-passe mudar, correr `./publicar.sh --esquecer` e publicar de novo.

Os valores propostos correspondem ao alojamento atual na Amen: servidor `gadgf9.ftp.tb-hosting.com` e pasta `www`, que é a pasta pública do domínio principal (a pasta `subsites` destina-se a subdomínios). O script usa ligação cifrada quando o servidor a suporta (a Amen não suporta na porta 21), envia apenas ficheiros novos ou diferentes, não apaga nada no servidor, exclui os ficheiros que não pertencem ao site e, no fim, avisa o Bing das páginas publicadas (IndexNow).

Com um programa de FTP (FileZilla, Cyberduck ou o gestor de ficheiros do alojamento), enviar o conteúdo desta pasta para a pasta pública do domínio (na Amen, `www`), exceto `.git`, `README.md`, `.nojekyll` e `publicar.sh`. O ficheiro `.htaccess` está oculto no Finder e tem de ser enviado.

Se a pasta pública tiver uma página por omissão do alojamento (por exemplo `index.php` ou `default.html`), apagá-la.

### Formulário de contacto

O formulário em `/contactos/` envia os pedidos para `formulario/enviar.php`, que precisa de PHP no alojamento. Cada pedido gera dois emails: o pedido para `info@gadgetmetodo.pt` (com cópia oculta opcional e resposta direta ao remetente) e uma confirmação de receção para quem o enviou. O campo de mensagem aceita ditado por voz nos navegadores que o suportam.

1. Confirmar que o alojamento executa PHP: `curl -s https://www.gadgetmetodo.pt/formulario/enviar.php` deve devolver uma resposta em JSON e não o código do ficheiro (verificado em outubro de 2026).
2. Correr uma vez `./configurar-formulario.sh`. Os dados de envio da Amen já estão no script (servidor `smtp-pt.securemail.pro`, porta 465 com SSL, utilizador `info@gadgetmetodo.pt`, cópia oculta para o email pessoal do gestor). Só pede a palavra-passe da caixa, envia um email de teste para a caixa e para a cópia oculta e, se o envio funcionar, guarda a palavra-passe nas Chaves do macOS.
3. Publicar com `./publicar.sh`. A partir daí, se `formulario/config.php` não existir, o `publicar.sh` gera-o sozinho a partir da palavra-passe guardada.

Se a palavra-passe do email mudar: `./configurar-formulario.sh --esquecer` e voltar ao passo 2.

### Catálogo enviado com os pedidos e registo de aberturas

O email de confirmação de cada pedido inclui um botão para o catálogo. A ligação (`/formulario/catalogo.php?r=REFERÊNCIA&s=ASSINATURA`) regista a data e hora de cada abertura e encaminha para `/catalogo-servicos-2026.pdf`. A assinatura impede que alguém registe aberturas de pedidos que não recebeu; ligações inválidas encaminham para o PDF sem registar nada.

- **Onde ficam os dados:** numa base de dados SQLite (ou em ficheiros CSV, se o alojamento não tiver SQLite), fora da pasta pública sempre que o alojamento o permite; caso contrário, numa pasta com nome não adivinhável dentro de `formulario/`. Os pedidos com mais de 12 meses e as respetivas aberturas são apagados automaticamente.
- **Consulta:** área reservada em `https://www.gadgetmetodo.pt/formulario/registos.php`, com a lista de pedidos, as aberturas e exportação para Excel. A palavra-passe é criada automaticamente pelo `configurar-formulario.sh`; para a ver: `./configurar-formulario.sh --acesso`.
- **Aberturas automáticas:** sistemas de segurança de email de algumas empresas abrem os links para os verificar. Estas aberturas são assinaladas (abertura nos dois minutos seguintes ao pedido ou por um agente conhecido), mas a deteção não é infalível.
- **Proteção de dados:** a política de privacidade e o rodapé do email de confirmação informam sobre este registo (interesse legítimo de acompanhamento comercial).

Proteções incluídas: campo isca para robôs, tempo mínimo de preenchimento, limite de 5 pedidos por hora por origem, validação no servidor, verificação da origem do pedido e bloqueio de injeção de cabeçalhos. Os testes foram feitos com um servidor de email local.

A pasta `documentos/` (catálogo e outros documentos) não é enviada para o site.

### Área de clientes

Endereço: `https://www.gadgetmetodo.pt/clientes/` (ligação no cabeçalho e no rodapé do site). Usa o mesmo `formulario/config.php` e a mesma base de dados SQLite dos registos de pedidos; não precisa de configuração adicional.

- **Primeiro acesso:** entrar com `info@gadgetmetodo.pt` e a palavra-passe da área reservada (`./configurar-formulario.sh --acesso`). Fica criada a conta de administração; a partir daí, a palavra-passe muda-se em "A minha conta" e deixa de depender da área reservada.
- **Perfis:** *Gadgetmetodo* vê e gere tudo (calendário, inscrições, clientes, utilizadores, pedidos do site). *Cliente* vê apenas as formações disponíveis e as inscrições do seu cliente; o acesso a páginas de gestão ou a inscrições de outros clientes é recusado no servidor.
- **Acessos:** em Clientes, criar a entidade; em Utilizadores, criar o acesso (o email é o nome de utilizador). A palavra-passe gerada é mostrada uma única vez, para comunicar ao cliente. Não há recuperação automática de palavra-passe: a Gadgetmetodo gera uma nova na ficha do utilizador.
- **Calendário:** formações com módulo, data, horário, modalidade, local, vagas e estado (inscrições abertas, fechadas ou cancelada). Formações sem inscrições podem ser apagadas; com inscrições, cancelam-se.
- **Inscrição:** dados do formando, diagnóstico de 8 perguntas (0 a 24 pontos, com subtotal de uso responsável de 0 a 9), ferramentas usadas, expectativas e confirmação de que o formando foi informado. O nível (Inicial, Exploratório, Em adoção, Avançado) e a recomendação de módulos são calculados no servidor. O cliente não pode exceder as vagas; a Gadgetmetodo pode.
- **Emails:** cada inscrição gera um aviso para `info@gadgetmetodo.pt` (com a cópia oculta configurada) e uma confirmação de receção para o utilizador do cliente. A Gadgetmetodo confirma ou altera o estado da inscrição e pode avisar o cliente por email. Os cancelamentos feitos pelo cliente geram um aviso interno.
- **Exportação:** inscrições, inscritos por formação e pedidos do site exportam para Excel (CSV com `;`).
- **Segurança:** palavras-passe guardadas com `password_hash`, sessão com cookie `HttpOnly` limitado a `/clientes/` e expiração após 2 horas de inatividade, proteção contra pedidos forjados (CSRF) em todos os formulários, limite de 10 tentativas de entrada em 15 minutos por origem, cabeçalhos `noindex` e `Content-Security-Policy`, e `/clientes/` bloqueado no `robots.txt`.
- **Conservação:** as inscrições e os diagnósticos são apagados automaticamente 2 anos após a data da formação (constante `DIAS_INSCRICOES` em `clientes/inc/nucleo.php`).

A área precisa de SQLite no alojamento. Se não existir, mostra "Área indisponível" (o formulário de contacto continua a funcionar com ficheiros CSV).

## Catálogo de serviços

O catálogo está publicado em `https://www.gadgetmetodo.pt/catalogo-servicos-2026.pdf` (ficheiro `catalogo-servicos-2026.pdf` na raiz) e tem uma página de download própria em `/catalogo/`, ligada no rodapé, na página inicial e na página de Serviços. A imagem da capa usada nessa página é `assets/img/catalogo-capa.jpg`. É uma cópia de `documentos/Gadgetmetodo-Catalogo-de-Servicos-2026.pdf` (16 páginas, A4 horizontal), gerado a partir de `documentos/catalogo/catalogo.html`, que usa as fontes e o logótipo de `assets/`. Para alterar o texto, editar o HTML e voltar a gerar o PDF no Chrome: Imprimir, Guardar como PDF, margens "Nenhuma" e "Gráficos de fundo" ativado. Depois, copiar o PDF para `catalogo-servicos-2026.pdf` na raiz e publicar.

Depois de publicar:

1. Registar o site no Google Search Console e no Bing Webmaster Tools e submeter `https://www.gadgetmetodo.pt/sitemap.xml`. O índice do Bing é usado por vários assistentes de IA.
2. Criar ou atualizar o perfil da empresa no Google (Google Business Profile) e a página da empresa no LinkedIn, com o mesmo nome, morada e telefone. Acrescentar esses endereços ao campo `sameAs` dos dados estruturados.

## Pendentes antes de publicar

- [ ] **Logótipo oficial.** Os ficheiros em `assets/marca/` são uma reconstrução vetorial provisória feita a partir do Manual Gráfico, que exige a validação do desenho final em vetor. Quando existirem os ficheiros aprovados, substituir os SVG mantendo os nomes e voltar a gerar `favicon.svg`, `favicon.ico`, `apple-touch-icon.png`, os ícones em `assets/img/` e a imagem de partilha `assets/img/partilha.png`.
- [ ] **Identificação legal da sociedade.** O artigo 171.º do Código das Sociedades Comerciais exige que os sítios na Internet indiquem a conservatória do registo comercial, o número de matrícula e de identificação de pessoa coletiva (NIPC) e, nas sociedades por quotas, o capital social. O NIPC (513 978 011) já está no rodapé, na página "Sobre", nos dados estruturados e no catálogo; faltam a conservatória e o capital social.
- [ ] **Redes sociais**: quando existirem, acrescentar os endereços ao campo `sameAs` da organização em `ferramentas/gerar-site.mjs`.
- [x] **Email com domínio próprio**: o site usa `info@gadgetmetodo.pt`.
- [x] **Exemplos de intervenção** (`experiencia/` e catálogo): divulgação confirmada.
- [ ] **Perfil de Jorge Sá Couto**: confirmar a função a indicar (por exemplo, sócio-gerente) e, se desejado, acrescentar fotografia.
- [ ] **Política de privacidade**: confirmar o prazo de conservação de 12 meses para pedidos de contacto sem seguimento e o de 2 anos para inscrições em formações e respostas ao diagnóstico.

## Manutenção

- As páginas HTML, o `sitemap.xml` e os dados estruturados são gerados por `ferramentas/gerar-site.mjs`, que contém o texto de todas as páginas, o menu, o rodapé e os dados da empresa. Para alterar conteúdo: editar o gerador, atualizar as constantes `HOJE` e `HOJE_TXT` e correr `cd ferramentas && npm install && npm run site`. Editar diretamente os `index.html` funciona, mas a alteração perde-se na geração seguinte.
- O catálogo é gerado por `ferramentas/gerar-catalogo.mjs` (`npm run catalogo`), com os estilos em `ferramentas/catalogo.css`.
- As respostas das perguntas frequentes alimentam ao mesmo tempo o texto visível e os dados estruturados, por isso ficam sempre iguais.
- O `llms.txt` e o `robots.txt` são editados à mão.
- O `publicar.sh` avisa o Bing e outros motores aderentes ao IndexNow no fim de cada publicação. A chave está no ficheiro `<chave>.txt` na raiz do site e tem de continuar publicada.

## Licenças de terceiros

- Fontes Montserrat e Inter: SIL Open Font License 1.1 (`assets/fonts/LICENCA-OFL.txt`).
- Ícones Lucide (incorporados no HTML): licença ISC, © Lucide Icons and Contributors.
