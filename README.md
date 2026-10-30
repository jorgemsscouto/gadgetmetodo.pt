# gadgetmetodo.pt

Website institucional da **Gadgetmetodo, Lda.**: consultoria em organização e métodos, inteligência artificial aplicada, proteção de dados e estratégia.

Site estático em HTML e CSS, sem dependências nem passo de compilação. Funciona em qualquer alojamento que sirva ficheiros (alojamento partilhado, GitHub Pages, Netlify, Cloudflare Pages ou outro).

## Estrutura

| Caminho | Conteúdo |
|---|---|
| `index.html` | Página inicial |
| `servicos/` | Visão geral e uma página por área de serviço |
| `abordagem/`, `experiencia/`, `sobre/` | A Metodologia (o endereço `/abordagem/` manteve-se para não partir ligações), exemplos de intervenção, empresa |
| `perguntas-frequentes/`, `contactos/`, `privacidade/` | Perguntas frequentes, contactos, política de privacidade |
| `404.html` | Página de erro |
| `assets/css/site.css` | Estilos (paleta e tipografia do Manual Gráfico) |
| `assets/js/site.js` | Apenas o menu em ecrãs pequenos; o conteúdo não depende de JavaScript |
| `assets/fonts/` | Montserrat e Inter alojadas no próprio site (licença OFL) |
| `assets/marca/` | Logótipo e símbolo em SVG e PNG |
| `robots.txt`, `sitemap.xml`, `llms.txt` | Indexação por motores de pesquisa e assistentes de IA |
| `.htaccess` | Regras para alojamentos Apache ou LiteSpeed |
| `formulario/` | Envio do formulário de contactos (PHP); a configuração `config.php` fica fora do Git |
| `clientes/` | Área reservada para clientes e administração (PHP): calendário de formações, inscrições com diagnóstico de IA e gestão de acessos |
| `formacoes/` | Área de formação: cursos com inscrição e pré-inscrição, calendário, página e ficha de cada curso, inscrição sem conta (PHP, gerado com o cabeçalho e o rodapé do site) e página AI Essentials (`formacoes/ai-essentials/`) |
| `assets/css/area.css` | Estilos próprios da área reservada (só carregados nessas páginas) |
| `ferramentas/` | Geradores das páginas e do catálogo (não publicados) |
| `documentos/` | Catálogo de serviços em PDF e respetiva fonte, e assinaturas de email em `assinatura-email/` (não publicados) |
| `luxury-tours-portugal/` | Protótipo independente do site, para apresentar a um cliente: inquérito multilingue da Luxury Tours Portugal com painel (PHP). Não indexado; ver `luxury-tours-portugal/LEIA-ME.md` |

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

O formulário em `/contactos/` envia os pedidos para `formulario/enviar.php`, que precisa de PHP no alojamento. Cada pedido gera dois emails: o pedido para `info@gadgetmetodo.pt` (com cópia oculta opcional e resposta direta ao remetente) e uma confirmação de receção para quem o enviou. Depois de configurados na área reservada (Configuração, Comunicação), o destinatário, a cópia oculta, o nome do remetente, os assuntos e os textos da confirmação (que pode ser desativada) passam a ser os definidos aí; sem base de dados, valem os do `config.php`. O campo de mensagem aceita ditado por voz nos navegadores que o suportam. O pedido pode ser feito por uma empresa (ou outra organização), a opção por omissão, ou por um particular; neste caso o nome da empresa não é pedido e o pedido aparece como "Particular" no assunto do email, nos registos e na exportação.

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

### Área reservada (clientes e administração)

Endereço: `https://www.gadgetmetodo.pt/clientes/` (ligação no cabeçalho e no rodapé do site). Usa o mesmo `formulario/config.php` e a mesma base de dados SQLite dos registos de pedidos; não precisa de configuração adicional.

- **Primeiro acesso:** entrar com `info@gadgetmetodo.pt` e a palavra-passe da área reservada (`./configurar-formulario.sh --acesso`). Fica criada a conta de administração; a partir daí, a palavra-passe muda-se em "A minha conta" e deixa de depender da área reservada.
- **Perfis:** *Gadgetmetodo* vê e gere tudo (calendário, inscrições, clientes, utilizadores, pedidos do site). *Cliente* vê apenas as formações disponíveis e as inscrições do seu cliente; o acesso a páginas de gestão ou a inscrições de outros clientes é recusado no servidor.
- **Clientes particulares ou empresas:** cada cliente tem o tipo "Empresa" (empresas e outras organizações) ou "Particular", indicado na ficha e na lista de clientes. Nos formulários públicos (inscrição, pré-inscrição e pedido de contacto) escolhe-se "Particular" ou "Empresa ou outra organização"; o nome da empresa só aparece e só é obrigatório para empresas (`assets/js/site.js`; sem JavaScript o campo fica visível e a validação é feita no servidor). Nas inscrições de particulares, o cliente é quem faz a inscrição: é assim que aparece nas listas (com a etiqueta "Particular"), nos emails e na exportação para Excel (coluna "Tipo de cliente"), e ao associar a inscrição a um cliente novo este é criado como particular. As inscrições anteriores a esta opção contam como de empresa.
- **Configuração** (menu da administração, com os separadores Visão geral, Utilizadores, Preços e Comunicação): reúne quem tem acesso, a tabela de preços e todos os emails enviados pelo site. A visão geral resume cada área (utilizadores ativos, linhas e modalidades de preços, emails alterados ou desativados e para onde vão os avisos internos). Os endereços antigos (`?p=utilizadores`, `?p=precos`) continuam a funcionar.
- **Acessos:** em Clientes, criar a entidade; em Configuração, Utilizadores, criar o acesso (o email é o nome de utilizador). A palavra-passe gerada é mostrada uma única vez, para comunicar ao cliente; com a opção "Enviar os dados de acesso por email" (desligada por omissão), o utilizador recebe também o endereço, o email e a palavra-passe. Não há recuperação automática de palavra-passe: a Gadgetmetodo gera uma nova na ficha do utilizador, com a mesma opção de envio por email. Enviar palavras-passe por email é prático mas menos seguro do que comunicá-las por outro canal; o email pede a alteração no primeiro acesso, em "A minha conta".
- **Cursos** (separador próprio): nome, tipologia (lista sugerida ou nova), duração, destinatários, descrição, opção de diagnóstico de IA na inscrição e módulos com descrição, horas e temas a abordar (um por linha). Cada curso tem também os campos da ficha (percurso, unidade "Módulo" ou "Sessão", objetivo, pré-requisitos, resultado esperado, estrutura-tipo da sessão e avaliação em linhas "rótulo: valor", requisitos, enquadramento legal e investimento) e cada módulo ou sessão tem o caso prático. Cursos com sessões não podem ser apagados (desativam-se); módulos com sessões também não. Mudar o nome de um curso atualiza as sessões.
- **Catálogo inicial:** os cursos são carregados do ficheiro `clientes/inc/catalogo/ia-generativa.json` (percurso "IA Generativa no Trabalho": 4 cursos de 6 sessões de 60 minutos). Na primeira utilização de uma versão nova do catálogo (campo `versao`), a agenda e os cursos são substituídos automaticamente apenas se não houver inscrições nem pré-inscrições; se houver, a administração mostra um aviso e o botão "Repor o catálogo", com confirmação, que apaga todas as sessões, as respetivas inscrições, as pré-inscrições e os cursos e carrega o catálogo (clientes, utilizadores e pedidos do site não são afetados). Depois de carregados, os cursos editam-se na administração.
- **Ficha em PDF:** `/formacoes/ficha.php?c=ID` gera no servidor (biblioteca FPDF, em `formulario/lib/FPDF/`) um PDF A4 com margens de 20 mm, logótipo, capa com percurso e factos principais, secções numeradas (sobre o curso, objetivo e resultado, programa com casos práticos, sessão-tipo e avaliação, requisitos, enquadramento legal, próximas sessões e contactos), cabeçalho a partir da 2.ª página e rodapé com "Página X de Y". Usa as fontes da marca (Inter e Montserrat, incorporadas) e não depende do navegador nem das opções de impressão: as margens fazem parte do próprio PDF, por isso mantêm-se mesmo que se imprima com "Margens: Nenhumas". Abre no visualizador de PDF do navegador, de onde se guarda ou imprime. Ligações antigas com `&pdf=1` ou `&imprimir=1` continuam a funcionar. Os botões "Exportar PDF" e "Copiar ligação" estão na página pública do curso, nos cartões da área de formação e, na administração, na ficha do curso e na lista de cursos (`assets/js/copiar.js`).
- **Calendário:** cada formação é uma edição do curso completo: a inscrição inclui sempre todas as sessões do curso e não há inscrições em módulos ou sessões avulsas, o que simplifica a gestão (uma inscrição por formando e por edição). Cada edição tem modalidade, local, vagas, estado e, nas sessões online, a ligação do Google Meet. Nos cursos com várias sessões, o formulário mostra a lista das sessões definidas no curso, pela ordem, para indicar a data, o início e o fim de cada uma; em cada linha pode trocar-se a sessão do curso, há duas linhas para sessões extra e as linhas sem data são ignoradas. Ao indicar a data e o horário da 1.ª sessão, o formulário propõe as restantes semanalmente (o que for escrito à mão mantém-se); sem datas nas linhas, o servidor marca-as semanalmente. As datas de cada sessão aparecem no calendário, na página do curso, no formulário de inscrição, nos emails de receção e de confirmação, na ficha em PDF (período da edição) e nos dados estruturados; a ficha da formação tem um atalho para o Google Calendar por sessão. Formações sem inscrições podem ser apagadas; com inscrições, cancelam-se. As formações que tinham sido agendadas sessão a sessão (uma formação por módulo) juntam-se automaticamente, no primeiro acesso depois da atualização, numa edição por curso com a data de cada sessão: as inscrições passam para a edição e, se a mesma pessoa estava inscrita em várias sessões, fica com uma só inscrição (a confirmada ou a mais antiga; as outras ficam canceladas, sem se perderem dados). As edições já terminadas e as formações canceladas ficam como estavam. Uma edição com datas só para parte das sessões mostra um aviso e as sessões em falta na lista, para completar; o formulário só propõe datas quando se altera a data ou o horário da 1.ª sessão.
- **Preços** (Configuração, Preços; só administração), com o modelo da tabela de preços do percurso IA Generativa no Trabalho:
  1. *Preços*: uma linha por curso (designação e detalhe, por exemplo "Iniciação · 6 horas") e um preço sem IVA por modalidade (aceita "1250", "1.250" ou "146,34"; guardado em cêntimos; em branco, "Sob consulta"), com "No PDF", ordem e apagar. "Acrescentar linha", junto de "Guardar alterações", junta linhas em branco sem recarregar a página (sem JavaScript, grava e volta com uma linha em branco).
  2. *Modalidades*: cada uma é uma coluna de preços (nome e detalhe, por exemplo "Particulares" e "por pessoa") e um cartão no PDF (nome, título e texto); no máximo 4 no PDF. Apagar uma modalidade apaga os seus preços.
  3. *Textos do documento*: sobretítulo, título, subtítulo, quatro factos, títulos das secções, cabeçalho da 1.ª coluna, nota da tabela, condições (uma por linha), texto antes do endereço do site, nota final ({data} é a data do documento) e data do documento (em branco, a da exportação).
  Com um erro (preço inválido, designação ou nome em falta, mais de 4 modalidades no PDF) nada é gravado e o formulário mantém o que foi escrito. Na primeira abertura, a tabela começa com as três modalidades (Empresas, Particulares e One-to-one), os cursos ativos, os preços e os textos da tabela de referência de 23 de outubro de 2026; a tabela da primeira versão (lista simples, se existir) fica em "Tabela anterior", só para consulta, e pode ser apagada. "Exportar PDF" gera no servidor (FPDF, com o grafismo da ficha de curso) o documento A4: cabeçalho com "Tabela de preços" e data em todas as páginas, faixa de título com os factos, cartões das modalidades, tabela numerada com um preço por modalidade, condições e a caixa "Informações e inscrições" com os contactos e um código QR para a página de formação (`assets/img/qr-formacoes.png`). O perfil Cliente não vê o separador nem o PDF.
- **Google Meet:** a ligação vai no email de confirmação (para quem inscreveu, com cópia ao formando) e na ficha da inscrição confirmada; nunca aparece nas páginas públicas. Na sessão há um atalho que abre o Google Calendar com a data e a hora preenchidas (aí acrescenta-se a videoconferência do Meet e copia-se a ligação), um atalho para uma reunião rápida e um botão para enviar a ligação a todas as inscrições confirmadas. A ligação não é criada automaticamente: isso exigiria ligar a conta Google ao site.
- **Convites a candidatos:** cada curso e cada edição aberta mostram a ligação pública, para copiar, e um formulário para a enviar por email a um candidato (com mensagem própria ou texto padrão; cópia para `info@`).
- **Pré-inscrições:** aparecem em Inscrições, no topo e com o estado "Pré-inscrição" (filtro próprio, também na exportação para Excel), e na página de cada curso (Cursos). Cada uma tem uma ficha (contactos, formandos previstos, modalidade e preferências, diagnóstico e curso indicado) onde se muda o estado (Nova, Convite enviado, Arquivada) e se inscreve o candidato: numa edição já agendada dos cursos do mesmo percurso ou numa edição nova, definindo aí as datas das sessões, o horário, a modalidade, o local, as vagas e a ligação do Google Meet (a edição fica no calendário e no site). A inscrição é criada como Confirmada ou Pendente, com os contactos e o diagnóstico da pré-inscrição; na confirmação, o candidato recebe o email com as datas, o local e a ligação das sessões online. A pré-inscrição passa então a "Inscrita" (estado que só resulta deste passo) e sai da lista, dando lugar à inscrição. Quando o curso tem sessões abertas com vagas, "Enviar convite" envia ao candidato a ligação para as datas (individualmente ou a todas as novas). O painel mostra as pré-inscrições por tratar. Cursos com pré-inscrições não podem ser apagados.
- **Inscrição:** sempre na edição completa do curso (todas as sessões); dados do formando, programa, temas e datas das sessões, diagnóstico (só nos cursos que o têm ativo) de 8 perguntas (0 a 24 pontos, com subtotal de uso responsável de 0 a 9), ferramentas de IA utilizadas (ChatGPT, Google Gemini, Microsoft Copilot, Perplexity, Claude, DeepSeek e "Outra", com campo para indicar qual; a pergunta não conta para a pontuação e é opcional, mas quem escolhe "Outra" tem de indicar qual), expectativas e confirmação de que o formando foi informado. O nível (Inicial, Exploratório, Em adoção, Avançado) e a recomendação de módulos são calculados no servidor. O cliente não pode exceder as vagas; a Gadgetmetodo pode.
- **Diagnóstico de nível:** nos cursos que fazem parte de um percurso, a inscrição e a pré-inscrição acrescentam 3 perguntas (como prepara os pedidos, ligação a outras ferramentas e automação, papel na adoção de IA) e indicam o curso do percurso mais adequado (`colocacao()` em `clientes/inc/diagnostico.php`). A regra segue os pré-requisitos dos cursos: uso ocasional ou só perguntas simples, Curso 1; uso regular sem instruções, contexto e verificação estruturados, Curso 2; uso individual consolidado sem aplicação em processos da equipa, Curso 3; aplicação em processos e responsabilidade pela adoção (equipa, processo ou organização), Curso 4. A indicação aparece a quem inscreve (página de confirmação e email) e à Gadgetmetodo (aviso interno, ficha da inscrição e lista de pré-inscrições, com o aviso "Diferente do curso escolhido"). Não impede a inscrição: a Gadgetmetodo confirma o curso com o candidato.
- **Emails:** cada inscrição gera um aviso para o email da administração (com a cópia oculta configurada) e uma confirmação de receção para o utilizador do cliente. A Gadgetmetodo confirma ou altera o estado da inscrição e pode avisar o cliente por email. Os cancelamentos feitos pelo cliente geram um aviso interno.
- **Comunicação** (Configuração, Comunicação): os 13 emails enviados pelo site e pela área reservada, agrupados por destinatário (administração; clientes, formandos e candidatos; utilizadores da área reservada), estão num catálogo único (`formulario/comunicacao.php`).
  1. *Remetente e destinatários*: nome do remetente, email da administração (recebe os avisos internos e as respostas de clientes e candidatos), cópia oculta dos avisos internos (vários emails separados por vírgulas), sobretítulo do cabeçalho, assinatura dos emails a clientes e candidatos e rodapé. Na primeira utilização valem os valores do `formulario/config.php` (`remetente_nome`, `destinatario`, `bcc`); "Repor os valores iniciais" volta a eles. O endereço de envio e a palavra-passe da caixa continuam no `config.php`, fora da área reservada.
  2. *Cada email*: quando é enviado, assunto, título, texto e fecho, com variáveis (por exemplo `{nome}`, `{formando}`, `{formacao}`, `{data}`, `{horario}`; cada email mostra as suas, com exemplos), parágrafos separados por linha em branco e `**negrito**`, e cópia oculta própria. Os dados de cada envio (tabelas, datas das sessões, diagnóstico, ligações e botões) são acrescentados pelo sistema entre o texto e o fecho. Os emails automáticos (aviso de nova inscrição, de nova pré-inscrição e de cancelamento, inscrição recebida, pré-inscrição recebida e confirmação do pedido de contacto) podem ser desativados; os que resultam de uma ação da administração (confirmação, mudança de estado, ligação das sessões online, convites, dados de acesso) e o próprio pedido de contacto são sempre enviados. No pedido de contacto só o assunto e a cópia são editáveis, porque o corpo é o pedido.
  3. *Verificação*: uma variável desconhecida ou um email inválido impede a gravação e o formulário mantém o que foi escrito. Cada email tem a pré-visualização com dados de exemplo, "Enviar um teste" (para o email de quem está ligado, com o assunto "[Teste]", sem cópias, mesmo que o email esteja desativado) e, se tiver sido alterado, "Repor os textos originais". A configuração fica na tabela `definicoes` (chave `comunicacao`); sem ela, valem os textos do catálogo.
  4. *Convites*: a mensagem escrita no formulário "Enviar a um candidato" substitui o texto do modelo (a saudação mantém-se) e não usa variáveis.
  Uma falha de envio pedida na administração (convite, aviso de estado, dados de acesso) aparece como aviso de erro; os detalhes ficam no registo de erros do alojamento.
- **Exportação:** inscrições, inscritos por formação e pedidos do site exportam para Excel (CSV com `;`).
- **Segurança:** palavras-passe guardadas com `password_hash`, sessão com cookie `HttpOnly` limitado a `/clientes/` e expiração após 2 horas de inatividade, proteção contra pedidos forjados (CSRF) em todos os formulários, limite de 10 tentativas de entrada em 15 minutos por origem, cabeçalhos `noindex` e `Content-Security-Policy`, e `/clientes/` bloqueado no `robots.txt`.
- **Conservação:** as inscrições e os diagnósticos são apagados automaticamente 2 anos após a data da formação (constante `DIAS_INSCRICOES` em `clientes/inc/nucleo.php`); as pré-inscrições, 12 meses após o registo (`DIAS_PREINSCRICOES`).

A área precisa de SQLite no alojamento. Se não existir, mostra "Área indisponível" (o formulário de contacto continua a funcionar com ficheiros CSV).

### Área de formação

Endereço: `https://www.gadgetmetodo.pt/formacoes/`, ligado no menu principal ("Formação", a seguir a "Serviços"), na página inicial, na visão geral dos serviços, no `sitemap.xml` e no `llms.txt`. Lê os cursos e as sessões da base de dados da área reservada e reúne toda a formação do site:

- **Cursos abertos por percurso:** os cursos ativos agrupados pelo campo "percurso" (por exemplo, IA Generativa no Trabalho), com um resumo calculado a partir dos dados (número de cursos, horas totais, evolução do tempo de prática, destinatários), um cartão por curso (objetivo, duração, minutos de prática por sessão, pré-requisitos, próxima data), uma tabela que compara a sessão-tipo e a avaliação dos cursos, os requisitos (comuns e específicos de cada curso) e o enquadramento legal. Cursos sem percurso aparecem em "Outros cursos".
- **Botão de inscrição em cada curso:** com uma edição aberta com vagas, "Inscrever-me" leva diretamente à inscrição nessa edição; com várias, leva às datas na página do curso; sem nenhuma, "Fazer pré-inscrição". Cada cartão tem também "Ver o curso" e "Ficha em PDF".
- **Novas formações e calendário** das próximas edições, com inscrição no curso completo.
- **AI Essentials à medida:** a página do programa passou para `/formacoes/ai-essentials/`. A morada antiga (`/servicos/formacao-ai-essentials/`) encaminha para a nova com um `Redirect 301` no `.htaccess` e, se o servidor não o aplicar, com uma página de reencaminhamento `noindex`.

- **Pré-inscrição** (`/formacoes/pre-inscricao.php?c=ID`, com o curso já escolhido quando vem da página ou do cartão do curso; sem `c`, por exemplo a partir do botão "Fazer pré-inscrição" da área de formação, o formulário pede o curso pretendido): curso, particular ou empresa (e, neste caso, o nome da empresa), nome, email, telefone, número previsto de formandos, modalidade preferida, preferências de datas, diagnóstico de nível, ferramentas de IA utilizadas e consentimento. As ferramentas e o tipo de cliente passam para a inscrição criada a partir da pré-inscrição. O candidato recebe logo um email "Pré-inscrição recebida" com os dados enviados e as ligações para a página e para a ficha do curso; a Gadgetmetodo recebe um aviso em `info@` (com cópia oculta e resposta direta ao candidato). Um novo envio com o mesmo email atualiza a pré-inscrição ainda por tratar (os campos deixados em branco mantêm o valor anterior). Tem as mesmas proteções da inscrição sem conta, com limite de 5 pré-inscrições por hora por origem.
- **Página de cada curso:** `/formacoes/curso.php?c=ID`, com descrição, tipologia, duração, destinatários, módulos e temas, próximas edições com inscrição e dados estruturados `Course`. É a ligação a partilhar no site, nas redes sociais ou por email. Cursos inativos ou inexistentes devolvem 404. Como dependem da base de dados, estas páginas não estão no `sitemap.xml`.

- **Novas formações:** secção no topo com as edições abertas agendadas nos últimos 30 dias (constante `DIAS_NOVA`), também assinaladas com a etiqueta "Nova" no calendário, na área reservada e no painel dos clientes.
- **Inscrição sem conta** (`/formacoes/inscricao.php`): particular ou empresa (com o nome da empresa, se for o caso) e NIF para a fatura, quem inscreve (com a opção "o formando sou eu"), formando, diagnóstico de IA e consentimento. Respeita as vagas; não mostra publicamente o número de inscritos, só as vagas disponíveis.
- **Proteções:** campo isca, tempo mínimo de 4 segundos com código assinado, verificação da origem, limite de 6 inscrições por hora por origem, validação no servidor e página `noindex`.
- **Depois da inscrição:** aviso para `info@gadgetmetodo.pt` (com cópia oculta) e confirmação de receção para quem inscreveu. Na área reservada, a inscrição aparece com a etiqueta "Site, por associar" (há um contador no painel e um filtro em Inscrições); na ficha, a Gadgetmetodo associa-a a um cliente existente ou cria o cliente com os dados indicados. Só depois de associada fica visível para os utilizadores desse cliente. A confirmação do estado é enviada por email a quem fez a inscrição.
- **Dados estruturados:** cada edição é publicada como `EducationEvent` (schema.org), com data, modalidade, local e organizador e, nas edições com várias sessões, um subevento por sessão, e a lista dos cursos como `ItemList` de `Course`.
- **Base de dados existente:** se a área reservada já tiver sido usada, a tabela de inscrições é atualizada automaticamente no primeiro acesso, sem perder dados.

A página `/formacoes/` é um `index.php`. O `.htaccess` declara `DirectoryIndex index.html index.php`; depois de publicar, confirmar que `curl -sI https://www.gadgetmetodo.pt/formacoes/` devolve `200`. Se devolver `403` ou `404`, o servidor não está a aplicar essa regra e as ligações devem passar a apontar para `/formacoes/index.php`.

## Catálogo de serviços

O catálogo está publicado em `https://www.gadgetmetodo.pt/catalogo-servicos-2026.pdf` (ficheiro `catalogo-servicos-2026.pdf` na raiz) e tem uma página de download própria em `/catalogo/`, ligada no rodapé, na página inicial e na página de Serviços. A imagem da capa usada nessa página é `assets/img/catalogo-capa.jpg`. É uma cópia de `documentos/Gadgetmetodo-Catalogo-de-Servicos-2026.pdf` (16 páginas, A4 horizontal), gerado a partir de `documentos/catalogo/catalogo.html`, que usa as fontes e o logótipo de `assets/`. Para alterar o texto, editar `ferramentas/gerar-catalogo.mjs`, gerar o HTML (`npm run catalogo`) e convertê-lo em PDF no Chrome: Imprimir, Guardar como PDF, margens "Nenhuma" e "Gráficos de fundo" ativado. Depois, copiar o PDF para `catalogo-servicos-2026.pdf` na raiz e publicar. A página da formação (página 10) lê os cursos do percurso de `clientes/inc/catalogo/ia-generativa.json`, o mesmo ficheiro que alimenta o site e a área reservada: ao mudar os cursos, gerar de novo o catálogo para os manter alinhados.

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
- Os endereços de `site.css` e `site.js` nas páginas levam um código de versão (`?v=`) calculado pelo gerador a partir do conteúdo do ficheiro, para os navegadores não usarem cópias antigas (o `.htaccess` deixa guardar o CSS e o JS durante 7 dias). Depois de alterar o CSS ou o JS, voltar a gerar as páginas. A área reservada usa a data de alteração do ficheiro e não precisa deste passo.
- O catálogo é gerado por `ferramentas/gerar-catalogo.mjs` (`npm run catalogo`), com os estilos em `ferramentas/catalogo.css`.
- As respostas das perguntas frequentes alimentam ao mesmo tempo o texto visível e os dados estruturados, por isso ficam sempre iguais.
- O `llms.txt` e o `robots.txt` são editados à mão.
- O `publicar.sh` avisa o Bing e outros motores aderentes ao IndexNow no fim de cada publicação. A chave está no ficheiro `<chave>.txt` na raiz do site e tem de continuar publicada.

## Assinatura de email

Duas assinaturas para o Outlook em `documentos/assinatura-email/`: `assinatura-completa.html` (mensagens novas) e `assinatura-resposta.html` (respostas e reencaminhamentos). Nos ficheiros, o logótipo é carregado de `https://www.gadgetmetodo.pt/assets/img/assinatura/gadgetmetodo-logotipo.png`; na página de instalação vai incorporado na assinatura copiada, para o Outlook guardar a imagem e a enviar com cada mensagem (evita o logótipo em falta como "image.png" e não depende de o destinatário autorizar imagens externas). Para instalar: abrir `instalar-assinatura.html` no navegador, carregar em "Copiar" e colar no editor de assinaturas do Outlook (o código HTML não pode ser colado como texto). Essa página é gerada a partir das duas assinaturas por `ferramentas/gerar-assinatura.mjs` (`npm run assinatura`); depois de alterar uma assinatura, voltar a gerá-la. No Outlook clássico para Windows, o ficheiro `.html` também pode ser copiado para `%APPDATA%\Microsoft\Signatures` com a extensão `.htm`. Usam apenas tabelas, estilos em linha e a fonte Arial, para aparecerem iguais no Outlook, no Gmail e nos telemóveis.

## Licenças de terceiros

- Fontes Montserrat e Inter: SIL Open Font License 1.1 (`assets/fonts/LICENCA-OFL.txt`); as versões estáticas usadas no PDF estão em `formulario/lib/FPDF/fontes/`, com a mesma licença.
- FPDF 1.9 (geração da ficha em PDF): licença permissiva do autor, Olivier Plathey (`formulario/lib/FPDF/LICENSE`).
- Ícones Lucide (incorporados no HTML): licença ISC, © Lucide Icons and Contributors.
