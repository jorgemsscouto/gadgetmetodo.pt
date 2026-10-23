// Gerador das páginas HTML do site gadgetmetodo.pt.
// Utilização (a partir desta pasta): npm install && node gerar-site.mjs ..
import crypto from 'crypto';
import fs from 'fs';
import path from 'path';

const OUT = process.argv[2];
const URL_BASE = 'https://www.gadgetmetodo.pt';
const HOJE = '2026-10-20';
const HOJE_TXT = '20 de outubro de 2026';
const EMAIL = 'info@gadgetmetodo.pt';
const TEL = '+351 915 416 112';
const TEL_LINK = 'tel:+351915416112';
const TEL_HTML = TEL.replace(/ /g, '&nbsp;');
const MORADA = { rua: 'Rua das Cruzes, 625', cp: '4100-195', local: 'Porto', pais: 'Portugal' };
const MAPA = 'https://www.openstreetmap.org/search?query=Rua%20das%20Cruzes%20625%2C%204100-195%20Porto';
const ICONES = 'node_modules/lucide-static/icons/';

// Versão dos ficheiros de estilo e de script: muda quando o conteúdo muda, para os navegadores não usarem cópias antigas
const versao = ficheiro => crypto.createHash('sha256').update(fs.readFileSync(path.join(OUT, ficheiro))).digest('hex').slice(0, 10);
const CSS = `/assets/css/site.css?v=${versao('assets/css/site.css')}`;
const JS = `/assets/js/site.js?v=${versao('assets/js/site.js')}`;
const CSS_AREA = `/assets/css/area.css?v=${versao('assets/css/area.css')}`;
const JS_COPIAR = `/assets/js/copiar.js?v=${versao('assets/js/copiar.js')}`;

// Ícones Lucide (licença ISC) incorporados inline
function icone(nome, classe = '') {
  let svg = fs.readFileSync(ICONES + nome + '.svg', 'utf8');
  svg = svg.replace(/<!--.*?-->\s*/s, '').replace(/\s*\n\s*/g, ' ').replace(/class="[^"]*"/, `${classe ? `class="${classe}" ` : ''}aria-hidden="true" focusable="false"`).replace(/ width="24" height="24"/, '').trim();
  return svg.replace('> <', '><').replace(/ \/>/g, '/>');
}

const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const semTags = s => s.replace(/<[^>]+>/g, '').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/\s+/g, ' ').trim();

// Navegação
const NAV = [
  ['/servicos/', 'Serviços'],
  ['/formacoes/', 'Formação'],
  ['/abordagem/', 'A Metodologia'],
  ['/experiencia/', 'Experiência'],
  ['/sobre/', 'Sobre'],
  ['/perguntas-frequentes/', 'Perguntas frequentes'],
];

const SERVICOS = [
  { url: '/servicos/organizacao-e-metodos/', nome: 'Organização e Métodos', icone: 'workflow', curto: 'Diagnóstico organizacional, mapeamento e redesenho de processos, normas e procedimentos internos, auditorias e planos de melhoria.' },
  { url: '/servicos/inteligencia-artificial/', nome: 'Inteligência artificial e transformação digital', icone: 'sparkles', curto: 'Estratégia de IA, casos de uso com retorno, arquitetura de sistemas e integrações, automação e painéis de indicadores.' },
  { url: '/servicos/protecao-de-dados-e-conformidade/', nome: 'Proteção de dados e conformidade', icone: 'shield-check', curto: 'Funções de Encarregado de Proteção de Dados, RGPD, NIS2 e governação da IA, com equilíbrio entre conformidade e operação.' },
  { url: '/servicos/estrategia-e-gestao/', nome: 'Estratégia e gestão', icone: 'compass', curto: 'Alinhamento estratégico, objetivos e indicadores, modelos de incentivos e apoio à administração em inovação e tecnologia.' },
  { url: '/formacoes/', chave: 'formacao', nome: 'Formação', icone: 'graduation-cap', curto: 'Percurso IA Generativa no Trabalho, com cursos abertos e inscrição online, e programa AI Essentials à medida para administrações e equipas.' },
];

// Programa à medida para empresas, dentro da área de formação
const AI_ESSENTIALS = { url: '/formacoes/ai-essentials/', nome: 'AI Essentials para empresas', icone: 'graduation-cap' };

// Dados estruturados comuns
const ORG_ID = URL_BASE + '/#organizacao';
const SITE_ID = URL_BASE + '/#website';
const PESSOA_ID = URL_BASE + '/sobre/#jorge-sa-couto';
const ORG = {
  '@type': 'ProfessionalService',
  '@id': ORG_ID,
  name: 'Gadgetmetodo, Lda.',
  alternateName: ['GADGETMETODO, LDA.', 'Gadgetmetodo', 'Gadgetmétodo', 'Gadgetmétodo, Lda.'],
  taxID: '513978011',
  vatID: 'PT513978011',
  disambiguatingDescription: 'Consultora de gestão sediada no Porto, Portugal. Não comercializa gadgets nem equipamentos eletrónicos.',
  legalName: 'Gadgetmetodo, Lda.',
  url: URL_BASE + '/',
  logo: { '@type': 'ImageObject', url: URL_BASE + '/assets/marca/simbolo-512.png', width: 512, height: 512 },
  image: URL_BASE + '/assets/img/partilha.png',
  description: 'Consultora sediada no Porto, especializada em organização e métodos (O&M), inteligência artificial aplicada à gestão, proteção de dados (RGPD, NIS2) e apoio estratégico a administrações.',
  slogan: 'Simplificar. Organizar. Fazer acontecer.',
  address: { '@type': 'PostalAddress', streetAddress: MORADA.rua, postalCode: MORADA.cp, addressLocality: MORADA.local, addressRegion: 'Porto', addressCountry: 'PT' },
  telephone: '+351915416112',
  email: EMAIL,
  contactPoint: { '@type': 'ContactPoint', contactType: 'sales', telephone: '+351915416112', email: EMAIL, availableLanguage: ['pt-PT'] },
  areaServed: { '@type': 'Country', name: 'Portugal' },
  knowsAbout: ['Organização e métodos', 'Diagnóstico organizacional', 'Redesenho de processos', 'Normas e procedimentos internos', 'Auditorias internas de processos e sistemas de gestão', 'Estratégia de inteligência artificial', 'Arquitetura de sistemas e integrações', 'Automação de marketing', 'Análise de dados e painéis de indicadores', 'RGPD', 'Encarregado de Proteção de Dados', 'Diretiva NIS2', 'Governação da inteligência artificial', 'Planeamento estratégico', 'Indicadores-chave de desempenho', 'Formação em inteligência artificial', 'Inteligência artificial generativa no trabalho'],
  hasOfferCatalog: {
    '@type': 'OfferCatalog',
    name: 'Serviços da Gadgetmetodo, Lda.',
    itemListElement: [
      ...SERVICOS.map(s => ({ '@type': 'Offer', itemOffered: { '@type': 'Service', '@id': URL_BASE + s.url + '#servico', name: s.nome } })),
      { '@type': 'Offer', itemOffered: { '@type': 'EducationalOccupationalProgram', '@id': URL_BASE + '/formacoes/#percurso', name: 'IA Generativa no Trabalho: do primeiro uso à liderança da implementação', url: URL_BASE + '/formacoes/', description: 'Percurso de quatro cursos abertos de seis horas sobre inteligência artificial generativa no trabalho, com inscrição online.', timeToComplete: 'PT24H' } },
      { '@type': 'Offer', itemOffered: { '@type': 'Course', '@id': URL_BASE + AI_ESSENTIALS.url + '#curso', name: 'AI Essentials' } },
    ],
  },
};
const WEBSITE = { '@type': 'WebSite', '@id': SITE_ID, url: URL_BASE + '/', name: 'Gadgetmetodo, Lda.', inLanguage: 'pt-PT', publisher: { '@id': ORG_ID } };
const PESSOA = {
  '@type': 'Person',
  '@id': PESSOA_ID,
  name: 'Jorge Sá Couto',
  jobTitle: 'Gestor de projeto e consultor',
  worksFor: { '@id': ORG_ID },
  alumniOf: [
    { '@type': 'CollegeOrUniversity', name: 'Universidade NOVA de Lisboa' },
    { '@type': 'CollegeOrUniversity', name: 'Universidade de Coimbra' },
    { '@type': 'CollegeOrUniversity', name: 'ISCTE - Instituto Universitário de Lisboa' },
    { '@type': 'CollegeOrUniversity', name: 'London Business School' },
  ],
  knowsAbout: ['Engenharia informática', 'Gestão', 'Estratégia de inteligência artificial', 'Organização e métodos', 'Proteção de dados'],
};

function migalhas(lista) {
  return {
    '@type': 'BreadcrumbList',
    itemListElement: lista.map(([url, nome], i) => ({ '@type': 'ListItem', position: i + 1, name: nome, item: URL_BASE + url })),
  };
}

// Componentes
const seta = icone('arrow-right');

function cabecalho(atual) {
  const links = NAV.map(([url, nome]) => `<li><a href="${url}"${atual.startsWith(url) ? ' aria-current="page"' : ''}>${nome}</a></li>`).join('\n          ');
  return `<header class="cabecalho">
    <div class="contentor">
      <a class="marca" href="/" aria-label="Gadgetmetodo, Lda., página inicial">
        <img src="/assets/marca/logotipo.svg" alt="Gadgetmetodo, Lda." width="234" height="42">
      </a>
      <button class="menu-botao" type="button" aria-expanded="false" aria-controls="navegacao">
        <span class="visualmente-oculto">Menu</span>
        ${icone('menu', 'icone-menu')}
        ${icone('x', 'icone-fechar')}
      </button>
      <nav id="navegacao" class="navegacao" aria-label="Principal">
        <ul>
          ${links}
          <li><a class="nav-clientes" href="/clientes/" title="Área reservada a clientes">${icone('circle-user-round').replace('<svg ', '<svg width="18" height="18" ')}<span class="nav-clientes-texto">Área reservada</span></a></li>
          <li><a class="botao" href="/contactos/#formulario"${atual === '/contactos/' ? ' aria-current="page"' : ''}>Pedir proposta</a></li>
        </ul>
      </nav>
    </div>
  </header>`;
}

function rodape() {
  return `<footer class="rodape">
    <div class="contentor">
      <div class="rodape-grelha">
        <div class="rodape-marca">
          <img src="/assets/marca/logotipo-negativo.svg" alt="Gadgetmetodo, Lda." width="260" height="47">
          <p>Consultoria em organização e métodos, inteligência artificial aplicada, proteção de dados e apoio à gestão, e formação em inteligência artificial. Porto, Portugal.</p>
        </div>
        <div>
          <h2>Serviços</h2>
          <ul>
            ${SERVICOS.map(s => `<li><a href="${s.url}">${s.nome}</a></li>`).join('\n            ')}
          </ul>
        </div>
        <div>
          <h2>Empresa</h2>
          <ul>
            <li><a href="/abordagem/">A Metodologia</a></li>
            <li><a href="/experiencia/">Experiência</a></li>
            <li><a href="/sobre/">Sobre nós</a></li>
            <li><a href="/catalogo/">Catálogo de serviços</a></li>
            <li><a href="/perguntas-frequentes/">Perguntas frequentes</a></li>
            <li><a href="/contactos/">Contactos</a></li>
            <li><a href="/clientes/">Área reservada</a></li>
          </ul>
        </div>
        <div>
          <h2>Contactos</h2>
          <address>
            <ul>
              <li>${MORADA.rua}<br>${MORADA.cp} ${MORADA.local}, ${MORADA.pais}</li>
              <li><a href="${TEL_LINK}">${TEL_HTML}</a><br><span class="nota">Chamada para a rede móvel nacional</span></li>
              <li><a href="mailto:${EMAIL}">${EMAIL}</a></li>
            </ul>
          </address>
        </div>
      </div>
      <div class="rodape-base">
        <p class="mb-0">© 2026 Gadgetmetodo, Lda. · NIPC 513&nbsp;978&nbsp;011. Todos os direitos reservados.</p>
        <ul>
          <li><a href="/privacidade/">Política de privacidade</a></li>
          <li><a href="https://www.livroreclamacoes.pt/Inicio/" rel="noopener">Livro de Reclamações</a></li>
        </ul>
      </div>
    </div>
  </footer>`;
}

function topoPagina({ trilho, h1, lead, atualizado = true }) {
  const itens = trilho.map(([url, nome], i) => i === trilho.length - 1 ? `<li aria-current="page">${nome}</li>` : `<li><a href="${url}">${nome}</a></li>`).join('');
  return `<section class="topo-pagina">
      <div class="contentor">
        <nav class="migalhas" aria-label="Localização"><ol>${itens}</ol></nav>
        <h1>${h1}</h1>
        <p class="lead">${lead}</p>
        ${atualizado ? `<p class="atualizado">Atualizado em <time datetime="${HOJE}">${HOJE_TXT}</time></p>` : ''}
      </div>
    </section>`;
}

function resumo(paragrafos, titulo = 'Em resumo') {
  return `<div class="resumo">
          <h2>${titulo}</h2>
          ${paragrafos.map(p => `<p>${p}</p>`).join('\n          ')}
        </div>`;
}

const listaCheck = itens => `<ul class="lista-check">\n${itens.map(i => `            <li>${i}</li>`).join('\n')}\n          </ul>`;

function chamada({ titulo = 'Vamos simplificar o próximo passo da sua organização?', texto = 'Conte-nos o seu desafio. Preparamos uma proposta à medida, com âmbito, entregáveis, calendário e investimento claros.' } = {}) {
  return `<section class="secao fundo-escuro fundo-profundo chamada" aria-labelledby="chamada-titulo">
      <img class="chamada-simbolo" src="/assets/marca/simbolo-negativo.svg" alt="" width="340" height="273" loading="lazy">
      <div class="contentor">
        <div>
          <h2 id="chamada-titulo">${titulo}</h2>
          <p>${texto}</p>
        </div>
        <div class="acoes">
          <a class="botao botao-claro" href="/contactos/#formulario">Pedir proposta ${seta}</a>
          <a class="botao botao-secundario" href="${TEL_LINK}">${icone('phone')} ${TEL_HTML}</a>
        </div>
      </div>
    </section>`;
}

function faqHtml(lista) {
  return `<div class="faq">
${lista.map(([p, r]) => `          <details>
            <summary>${p}</summary>
            <div class="resposta">${r.startsWith('<') ? r : `<p>${r}</p>`}</div>
          </details>`).join('\n')}
        </div>`;
}

const faqSchema = lista => lista.map(([p, r]) => ({ '@type': 'Question', name: semTags(p), acceptedAnswer: { '@type': 'Answer', text: semTags(r) } }));

function servicosRelacionados(atual, nevoa) {
  const outros = SERVICOS.filter(s => s.url !== atual);
  return `<section class="secao${nevoa ? ' secao-nevoa' : ''}" aria-labelledby="relacionados">
      <div class="contentor">
        <div class="cabeca-secao">
          <span class="sobretitulo">Serviços relacionados</span>
          <h2 id="relacionados">Uma abordagem integrada</h2>
          <p>Organização, tecnologia, proteção de dados e estratégia funcionam melhor quando são tratadas em conjunto.</p>
        </div>
        <div class="grelha grelha-4">
          ${outros.map(cartaoServico).join('\n          ')}
        </div>
      </div>
    </section>`;
}

function cartaoServico(s) {
  return `<a class="cartao" href="${s.url}">
            <span class="icone">${icone(s.icone)}</span>
            <h3>${s.nome}</h3>
            <p>${s.curto}</p>
            <span class="mais">Saber mais ${seta}</span>
          </a>`;
}

// Página completa
const paginas = [];
// Páginas com conteúdo dinâmico (formações) são geradas como PHP: "php" é o código inicial,
// "jsonldExtra" acrescenta dados estruturados calculados no servidor e "estilos" folhas de estilo adicionais.
// "cabecaPhp" ({ titulo, descricao, canonical }) substitui esses campos por código PHP, em páginas cujo conteúdo depende da base de dados.
function pagina({ url, titulo, descricao, h1Schema, tipo = 'WebPage', trilho, corpo, schemaExtra = [], prioridade = '0.7', llms, indexar = true, scripts = [], ficheiro = 'index.html', php = '', jsonldExtra = '', estilos = [], cabecaPhp = null }) {
  const canonical = URL_BASE + url;
  const webpage = {
    '@type': tipo,
    '@id': canonical + '#pagina',
    url: canonical,
    name: titulo,
    description: descricao,
    inLanguage: 'pt-PT',
    isPartOf: { '@id': SITE_ID },
    about: { '@id': ORG_ID },
    dateModified: HOJE,
    primaryImageOfPage: { '@type': 'ImageObject', url: URL_BASE + '/assets/img/partilha.png' },
  };
  const grafo = cabecaPhp ? [ORG, WEBSITE] : [ORG, WEBSITE, webpage];
  if (trilho) {
    grafo.push(migalhas(trilho));
    webpage.breadcrumb = { '@id': canonical + '#migalhas' };
    grafo[grafo.length - 1]['@id'] = canonical + '#migalhas';
  }
  for (const n of schemaExtra) {
    if (n.__mainEntity) {
      delete n.__mainEntity;
      webpage.mainEntity = { '@id': n['@id'] };
    }
    grafo.push(n);
  }
  if (tipo === 'FAQPage') webpage.mainEntity = schemaExtra.__faq;
  const jsonld = JSON.stringify({ '@context': 'https://schema.org', '@graph': grafo }, null, 2).replace(/</g, '\\u003c');
  const tituloCompleto = url === '/' ? titulo : `${titulo} | Gadgetmetodo, Lda.`;
  const cabeca = cabecaPhp || { titulo: esc(tituloCompleto), descricao: esc(descricao), canonical };
  const html = `${php}<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${cabeca.titulo}</title>
  <meta name="description" content="${cabeca.descricao}">
  <meta name="robots" content="${url === '/404.html' || !indexar ? 'noindex, follow' : 'index, follow, max-snippet:-1, max-image-preview:large'}">
  ${url === '/404.html' ? '' : `<link rel="canonical" href="${cabeca.canonical}">`}
  <meta name="theme-color" content="#0A0F26">
  <meta name="author" content="Gadgetmetodo, Lda.">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="pt_PT">
  <meta property="og:site_name" content="Gadgetmetodo, Lda.">
  <meta property="og:title" content="${cabeca.titulo}">
  <meta property="og:description" content="${cabeca.descricao}">
  <meta property="og:url" content="${cabeca.canonical}">
  <meta property="og:image" content="${URL_BASE}/assets/img/partilha.png">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:alt" content="Gadgetmetodo, Lda. Simplificar, organizar, fazer acontecer.">
  <meta name="twitter:card" content="summary_large_image">
  <link rel="icon" href="/favicon.ico" sizes="32x32">
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="/apple-touch-icon.png">
  <link rel="manifest" href="/site.webmanifest">
  <link rel="alternate" type="text/plain" href="/llms.txt" title="Resumo do site para modelos de linguagem">
  <link rel="preload" href="/assets/fonts/montserrat-var-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="preload" href="/assets/fonts/inter-var-latin.woff2" as="font" type="font/woff2" crossorigin>
  <link rel="stylesheet" href="${CSS}">
${estilos.map(e => `  <link rel="stylesheet" href="${e}">\n`).join('')}  <script>document.documentElement.classList.add("js")</script>
  <script type="application/ld+json">
${jsonld}
  </script>
${jsonldExtra}</head>
<body>
  <a class="saltar" href="#conteudo">Saltar para o conteúdo</a>
  ${cabecalho(url)}
  <main id="conteudo">
    ${corpo.trim()}
  </main>
  ${rodape()}
  <script src="${JS}" defer></script>
${scripts.map(s => `  <script src="${s}" defer></script>\n`).join('')}</body>
</html>
`;
  const destino = url === '/404.html' ? path.join(OUT, '404.html') : url.endsWith('.php') ? path.join(OUT, url) : path.join(OUT, url, ficheiro);
  fs.mkdirSync(path.dirname(destino), { recursive: true });
  fs.writeFileSync(destino, html);
  // As páginas com cabeçalho dinâmico (uma por curso) não têm endereço fixo para o sitemap
  if (url !== '/404.html' && indexar && !cabecaPhp) paginas.push({ url, titulo, descricao, prioridade, llms });
}

/* ------------------------------------------------------------------ */
/* Conteúdo                                                            */
/* ------------------------------------------------------------------ */

const FAQ_EMPRESA = [
  ['O que faz a Gadgetmetodo, Lda.?', 'A Gadgetmetodo, Lda. é uma consultora com sede no Porto que ajuda empresas a <a href="/servicos/organizacao-e-metodos/">organizar processos</a>, <a href="/servicos/inteligencia-artificial/">adotar inteligência artificial (IA) com critério</a>, <a href="/servicos/protecao-de-dados-e-conformidade/">cumprir o Regulamento Geral sobre a Proteção de Dados (RGPD) e a Diretiva NIS2</a>, e <a href="/servicos/estrategia-e-gestao/">transformar a estratégia em objetivos e indicadores</a>. Trabalha do diagnóstico à implementação e ao acompanhamento.'],
  ['Onde está localizada a Gadgetmetodo?', `A sede fica na ${MORADA.rua}, ${MORADA.cp} ${MORADA.local}, Portugal. A forma de acompanhamento de cada projeto, presencial, remota ou mista, é definida na proposta.`],
  ['Com que tipo de empresas trabalha?', 'Sobretudo com pequenas e médias empresas (PME) e respetivas administrações, em <a href="/experiencia/">setores como a indústria têxtil e de vestuário</a>, a tecnologia e gestão documental, o turismo e o retalho. O ponto comum é a vontade de ganhar eficiência e preparar a organização para a inteligência artificial.'],
];
const FAQ_SERVICOS = [
  ['O que é consultoria em Organização e Métodos (O&amp;M)?', 'É a análise de como uma empresa está organizada e de como o trabalho é feito, para eliminar redundâncias, clarificar responsabilidades e reduzir custos de operação. Inclui <a href="/servicos/organizacao-e-metodos/">diagnóstico organizacional, mapeamento e redesenho de processos</a>, normas e procedimentos internos e planos de melhoria com indicadores.'],
  ['Como pode a inteligência artificial ajudar a minha empresa?', 'A IA é útil em processos com volume, repetição ou decisões apoiadas em dados: personalização da comunicação com clientes, automação de tarefas e fluxos, análise de dados para decisão e apoio ao desenvolvimento de produto. A Gadgetmetodo começa por <a href="/servicos/inteligencia-artificial/">identificar e priorizar os casos de uso</a> pelo retorno esperado e pelo esforço de implementação, antes de qualquer investimento em ferramentas.'],
  ['A Gadgetmetodo pode assumir as funções de DPO da minha empresa?', 'Sim. A Gadgetmetodo assegura <a href="/servicos/protecao-de-dados-e-conformidade/">funções de Encarregado de Proteção de Dados (DPO, Data Protection Officer)</a> em regime externo, incluindo o registo das atividades de tratamento, a revisão de contratos com clientes e subcontratantes, a avaliação de transferências internacionais de dados e o apoio na relação com a Comissão Nacional de Proteção de Dados (CNPD).'],
  ['Que apoio prestam na Diretiva NIS2?', 'Apoio na análise do enquadramento da empresa face à Diretiva (UE) 2022/2555 (NIS2), na identificação dos <a href="/servicos/protecao-de-dados-e-conformidade/">requisitos de segurança da informação aplicáveis</a>, incluindo os exigidos contratualmente por clientes abrangidos, e no alinhamento desses requisitos com a governação da IA e a proteção de dados.'],
];
// Formação: respostas baseadas no catálogo de cursos (clientes/inc/catalogo/ia-generativa.json)
const FAQ_FORMACAO = [
  ['Que formação oferece a Gadgetmetodo?', 'Dois formatos. <a href="/formacoes/">Cursos abertos</a> a particulares e a empresas, com inscrição individual, organizados no percurso IA Generativa no Trabalho: quatro cursos de seis horas, da iniciação à liderança da implementação. E <a href="/formacoes/ai-essentials/">programas à medida</a>, como o AI Essentials, organizados na empresa para administrações e equipas.'],
  ['Como está organizado o percurso IA Generativa no Trabalho?', 'Tem quatro cursos sequenciais, cada um com seis sessões de 60 minutos (24 horas no total): Curso 1, Iniciação à IA Generativa no Trabalho; Curso 2, Produtividade com IA Generativa; Curso 3, IA nos Processos da Organização; Curso 4, Liderança e Implementação de IA. Todas as sessões terminam num Laboratório de Casos Práticos, que passa de 10 para 40 minutos por sessão ao longo do percurso. A ferramenta de referência é o Claude (Anthropic); os conceitos são transferíveis para outros assistentes de IA generativa.'],
  ['Por que curso devo começar?', 'A inscrição e a pré-inscrição incluem um diagnóstico de nível, com 11 perguntas, que indica o curso mais adequado ao ponto de partida de cada formando. Os pré-requisitos são: nenhum no Curso 1; o Curso 1 ou utilização regular demonstrada no Curso 2; o curso anterior nos Cursos 3 e 4. O Curso 4 é recomendado para quadros, chefias, consultores e responsáveis por transformação digital.'],
  ['Como me inscrevo e o que é a pré-inscrição?', 'Na <a href="/formacoes/">área de formação</a>, cada curso tem um botão de inscrição. Pode inscrever-se como particular ou em nome de uma empresa. Se já houver datas, inscreve-se numa edição do curso, sem precisar de conta; a inscrição inclui sempre todas as sessões do curso (não há inscrições em sessões avulsas) e fica pendente até ser confirmada por email. Se ainda não houver datas, faz a <a href="/formacoes/pre-inscricao.php">pré-inscrição</a>: recebe logo uma confirmação por email e, quando as datas forem marcadas, o convite para a inscrição. A pré-inscrição não obriga a inscrever-se.'],
  ['Que requisitos técnicos são necessários?', 'Um computador por formando, com navegador atualizado, e conta própria no Claude. Nos Cursos 1 e 2, o plano gratuito é suficiente para a maioria dos exercícios; nos Cursos 3 e 4 é necessário um plano pago (Pro, Max, Team ou Enterprise), porque as sessões sobre conectores, tarefas agendadas e agentes usam funcionalidades que não estão disponíveis, ou estão limitadas, no plano gratuito. As turmas têm 8 a 14 formandos, em modalidade presencial ou à distância síncrona.'],
  ['Como é feita a avaliação?', 'Combina avaliação prática, teste e participação, e o peso da prática cresce de curso para curso: 40% no Curso 1, 60% no Curso 2, 70% no Curso 3 e 85% no Curso 4, onde inclui um projeto final e não há teste. A aprovação exige 50% e presença em 5 das 6 sessões; no Curso 4, 50% e a entrega do projeto final.'],
  ['Que relação tem esta formação com o Regulamento da IA?', 'O artigo 4.º do Regulamento (UE) 2024/1689 (Regulamento da IA), aplicável desde 2 de fevereiro de 2025 e reescrito pelo Regulamento (UE) 2026/1744, em vigor desde 27 de julho de 2026, exige que as organizações que fornecem ou utilizam sistemas de IA adotem medidas para apoiar o desenvolvimento da literacia em IA do seu pessoal. O percurso pode integrar essas medidas e produz evidência documentada da formação realizada. Não constitui, por si só, garantia de conformidade legal.'],
  ['Onde encontro o programa detalhado de cada curso?', 'Cada curso tem uma página com o objetivo, o programa sessão a sessão, os temas, os casos práticos, a avaliação e as próximas datas, e uma ficha em PDF para descarregar, imprimir ou enviar. Estão todas na <a href="/formacoes/">área de formação</a>.'],
  ['É possível organizar a formação só para a minha empresa?', 'Sim. Qualquer curso do percurso, ou o programa AI Essentials, pode ser organizado na sua empresa, com datas, duração, modalidade e exemplos ajustados ao setor e às funções do grupo. <a href="/contactos/#formulario">Peça uma proposta</a>.'],
  ['Em que consiste a formação AI Essentials?', 'É um <a href="/formacoes/ai-essentials/">programa à medida, em cinco módulos</a>: fundamentos de IA para a administração; casos de uso e integração de IA em sistemas; governação e ética da IA; RGPD, privacidade e IA; NIS2 e segurança da informação. Os conteúdos são adaptados ao setor e ao público, e a formação é ministrada por formador com Certificado de Competências Pedagógicas (CCP).'],
];
const FAQ_FUNCIONAMENTO = [
  ['Como decorre um projeto?', '<a href="/abordagem/">Em quatro fases</a>: diagnóstico da situação atual, desenho da solução e do plano de ação, implementação com as equipas e acompanhamento com indicadores. O âmbito, os entregáveis e o calendário ficam definidos na proposta.'],
  ['Como é definido o investimento?', 'Cada proposta é preparada à medida, depois de uma primeira conversa sobre os objetivos e a situação da empresa. Os projetos podem ter âmbito fechado, com pagamento associado à conclusão ou a marcos, ou assumir a forma de <a href="/abordagem/#modelos">acompanhamento continuado</a> com prestações periódicas, por exemplo trimestrais.'],
  ['Os projetos são confidenciais?', 'Sim. A informação dos clientes é tratada com reserva e de acordo com o RGPD. Por esse motivo, os <a href="/experiencia/">exemplos de intervenção</a> publicados neste site não identificam os clientes.'],
  ['Como posso pedir uma proposta?', `Através do <a href="/contactos/#formulario">formulário na página de contactos</a>, por email para ${EMAIL} ou pelo telefone ${TEL}. Indique a empresa e o setor, o desafio ou objetivo, os prazos pretendidos e a melhor forma de contacto.`],
];

/* Página inicial */
pagina({
  url: '/',
  titulo: 'Gadgetmetodo, Lda. | Organização e Métodos e IA no Porto',
  descricao: 'Consultora do Porto em organização e métodos, inteligência artificial aplicada, proteção de dados (RGPD, NIS2) e estratégia. Do diagnóstico à implementação.',
  prioridade: '1.0',
  llms: 'Página inicial: quem somos, serviços, formação, metodologia e contactos.',
  schemaExtra: [PESSOA],
  corpo: `
    <section class="hero fundo-escuro" aria-labelledby="titulo-principal">
      <img class="hero-simbolo" src="/assets/marca/simbolo-negativo.svg" alt="" width="620" height="497">
      <div class="contentor">
        <div class="hero-texto">
          <ul class="assinatura">
            <li>Simplificar</li>
            <li>Organizar</li>
            <li>Fazer acontecer</li>
          </ul>
          <h1 id="titulo-principal">Consultoria em organização, métodos e inteligência artificial para empresas</h1>
          <p class="lead">A Gadgetmetodo, Lda. é uma consultora do Porto que ajuda empresas a simplificar processos, adotar a inteligência artificial (IA) com critério e cumprir as exigências de proteção de dados, do diagnóstico à implementação.</p>
          <div class="acoes">
            <a class="botao botao-claro" href="/contactos/#formulario">Pedir proposta ${seta}</a>
            <a class="botao botao-secundario" href="/servicos/">Conhecer os serviços</a>
          </div>
        </div>
      </div>
    </section>

    <section class="secao" aria-labelledby="quem-somos">
      <div class="contentor">
        <div class="duas-colunas texto-largo">
          <div>
            <span class="sobretitulo">Quem somos</span>
            <h2 id="quem-somos">Método para organizar, tecnologia para acelerar</h2>
            ${resumo([
              'A Gadgetmetodo, Lda. é uma empresa de consultoria em Organização e Métodos (O&amp;M) com sede no Porto. Apoia administrações e equipas de gestão a simplificar processos, redesenhar a organização, integrar ferramentas de inteligência artificial e garantir conformidade com o Regulamento Geral sobre a Proteção de Dados (RGPD) e a Diretiva NIS2.',
              'Trabalha por projeto ou em acompanhamento continuado, com propostas à medida de cada organização.',
            ], 'O que faz a Gadgetmetodo?')}
          </div>
          <dl class="factos">
            <div><dt>Designação</dt><dd>Gadgetmetodo, Lda.</dd></div>
            <div><dt>Sede</dt><dd>${MORADA.rua}, ${MORADA.cp} ${MORADA.local}</dd></div>
            <div><dt>Áreas</dt><dd>Organização e métodos, IA aplicada, proteção de dados, estratégia e formação</dd></div>
            <div><dt>Para quem</dt><dd>Pequenas e médias empresas (PME) e respetivas administrações</dd></div>
            <div><dt>Formato</dt><dd>Projetos de âmbito fechado ou acompanhamento continuado</dd></div>
            <div class="largo"><dt>Contacto</dt><dd><a href="${TEL_LINK}">${TEL_HTML}</a><br><a href="mailto:${EMAIL}">${EMAIL}</a></dd></div>
          </dl>
        </div>
      </div>
    </section>

    <section class="secao secao-nevoa" aria-labelledby="servicos">
      <div class="contentor">
        <div class="cabeca-secao">
          <span class="sobretitulo">Serviços</span>
          <h2 id="servicos">O que fazemos</h2>
          <p>Cinco áreas complementares, que podem ser contratadas isoladamente ou combinadas num único projeto.</p>
        </div>
        <div class="grelha grelha-3">
          ${SERVICOS.map(cartaoServico).join('\n          ')}
          <a class="cartao" href="/abordagem/">
            <span class="icone">${icone('search')}</span>
            <h3>Não sabe por onde começar?</h3>
            <p>Comece por um diagnóstico. Identificamos prioridades, riscos e oportunidades antes de qualquer investimento.</p>
            <span class="mais">Ver a metodologia ${seta}</span>
          </a>
        </div>
        <p class="espaco-topo mb-0"><a class="botao botao-secundario" href="/catalogo/">${icone('file-text')} Descarregar o catálogo de serviços</a></p>
      </div>
    </section>

    <section class="secao secao-curta" aria-labelledby="formacao-inicio">
      <div class="contentor">
        <div class="destaque-formacoes">
          <div>
            <span class="sobretitulo">Formação</span>
            <h2 id="formacao-inicio">IA Generativa no Trabalho: do primeiro uso à liderança da implementação</h2>
            <p class="mb-0">Quatro cursos sequenciais de seis horas, com casos práticos em todas as sessões, inscrição ou pré-inscrição online e ficha de cada curso em PDF. Também organizamos o programa AI Essentials à medida, na sua empresa.</p>
          </div>
          <a class="botao" href="/formacoes/">${icone('graduation-cap')} Ver a área de formação</a>
        </div>
      </div>
    </section>

    <section class="secao" aria-labelledby="como-trabalhamos">
      <div class="contentor">
        <div class="cabeca-secao">
          <span class="sobretitulo">A Metodologia</span>
          <h2 id="como-trabalhamos">Como trabalhamos</h2>
          <p>Cada projeto segue um percurso claro, com entregáveis definidos e decisões apoiadas em dados.</p>
        </div>
        <ol class="etapas">
          <li><h3>Diagnóstico</h3><p>Levantamento de processos, sistemas, equipas, dados e riscos. Identificação das prioridades com maior retorno.</p></li>
          <li><h3>Desenho</h3><p>Modelo organizacional, processos, arquitetura de sistemas, regras de governação e plano de ação com calendário.</p></li>
          <li><h3>Implementação</h3><p>Execução com as equipas: ferramentas, procedimentos, formação e gestão da mudança.</p></li>
          <li><h3>Acompanhamento</h3><p>Medição de resultados com indicadores, ajustes e apoio regular à administração.</p></li>
        </ol>
        <p class="espaco-topo mb-0"><a class="botao botao-secundario" href="/abordagem/">Conhecer a metodologia ${seta}</a></p>
      </div>
    </section>

    <section class="secao fundo-escuro" aria-labelledby="experiencia">
      <div class="contentor">
        <div class="cabeca-secao">
          <span class="sobretitulo">Experiência</span>
          <h2 id="experiencia">Intervenções em vários setores</h2>
          <p>Da indústria ao turismo, o método é o mesmo: perceber o negócio, simplificar o que existe e aplicar a tecnologia onde gera valor.</p>
        </div>
        <div class="grelha grelha-4">
          <div class="cartao"><span class="icone">${icone('shirt')}</span><h3>Têxtil e vestuário</h3><p>Digitalização do desenvolvimento de produto com prototipagem 3D, formação e acompanhamento.</p></div>
          <div class="cartao"><span class="icone">${icone('file-text')}</span><h3>Tecnologia e gestão documental</h3><p>Estratégia de IA, arquitetura de sistemas e funções de DPO com governação RGPD e NIS2.</p></div>
          <div class="cartao"><span class="icone">${icone('wine')}</span><h3>Turismo premium</h3><p>IA e automação de marketing, painéis de indicadores e formação das equipas comerciais.</p></div>
          <div class="cartao"><span class="icone">${icone('shopping-bag')}</span><h3>Retalho omnicanal</h3><p>Plano estratégico de recuperação de margem, organização por canal e modelo de incentivos.</p></div>
        </div>
        <p class="espaco-topo mb-0"><a class="botao botao-secundario" href="/experiencia/">Ver exemplos de intervenção ${seta}</a></p>
      </div>
    </section>

    <section class="secao" aria-labelledby="principios">
      <div class="contentor">
        <div class="duas-colunas">
          <div>
            <span class="sobretitulo">Princípios</span>
            <h2 id="principios">Simplificar, organizar, fazer acontecer</h2>
            <p class="lead texto-suave">A identidade da Gadgetmetodo nasce de módulos que se ligam para formar uma solução. É também assim que trabalhamos.</p>
          </div>
          <dl class="principios">
            <div><dt>Clareza</dt><dd>Comunicação direta, entregáveis compreensíveis e decisões fundamentadas.</dd></div>
            <div><dt>Método</dt><dd>Diagnóstico rigoroso, processos documentados e indicadores para medir o progresso.</dd></div>
            <div><dt>Integração</dt><dd>Pessoas, processos e tecnologia tratados como um só sistema.</dd></div>
            <div><dt>Confiança</dt><dd>Confidencialidade, proteção de dados e governação com bom senso.</dd></div>
          </dl>
        </div>
      </div>
    </section>

    <section class="secao secao-nevoa" aria-labelledby="responsavel">
      <div class="contentor">
        <div class="duas-colunas texto-largo">
          <div>
            <span class="sobretitulo">Equipa</span>
            <h2 id="responsavel">Quem acompanha os projetos</h2>
            <div class="perfil">
              <div class="monograma" aria-hidden="true">JSC</div>
              <div>
                <h3 class="mb-0">Jorge Sá Couto</h3>
                <p class="funcao">Gestor de projeto e consultor</p>
                <p>Licenciado em Engenharia Informática pela Universidade NOVA de Lisboa, com MBA em Gestão pelo ISCTE e formação em estratégia de inteligência artificial para líderes pela London Business School. Formador com Certificado de Competências Pedagógicas (CCP).</p>
                <p class="mb-0"><a href="/sobre/">Conhecer a empresa e o percurso ${seta.replace('<svg', '<svg style="display:inline;width:16px;height:16px;vertical-align:-3px"')}</a></p>
              </div>
            </div>
          </div>
          <div>
            <h2 class="visualmente-oculto">Perguntas frequentes</h2>
            ${faqHtml([FAQ_EMPRESA[0], FAQ_SERVICOS[2], FAQ_FUNCIONAMENTO[1]])}
            <p class="espaco-topo mb-0"><a href="/perguntas-frequentes/">Ver todas as perguntas frequentes</a></p>
          </div>
        </div>
      </div>
    </section>

    ${chamada()}
  `,
});

/* Serviços: visão geral */
pagina({
  url: '/servicos/',
  titulo: 'Serviços de consultoria',
  descricao: 'Organização e métodos, inteligência artificial e transformação digital, proteção de dados e conformidade, estratégia e gestão, e formação em inteligência artificial.',
  tipo: 'CollectionPage',
  prioridade: '0.9',
  llms: 'Visão geral das cinco áreas de serviço.',
  trilho: [['/', 'Início'], ['/servicos/', 'Serviços']],
  schemaExtra: [{
    '@type': 'ItemList',
    '@id': URL_BASE + '/servicos/#lista',
    name: 'Serviços da Gadgetmetodo, Lda.',
    itemListElement: SERVICOS.map((s, i) => ({ '@type': 'ListItem', position: i + 1, name: s.nome, url: URL_BASE + s.url })),
    __mainEntity: true,
  }],
  corpo: `
    ${topoPagina({ trilho: [['/', 'Início'], ['/servicos/', 'Serviços']], h1: 'Serviços de consultoria', lead: 'Cinco áreas complementares para organizar a empresa, adotar a inteligência artificial com critério, proteger dados e transformar a estratégia em resultados.' })}

    <section class="secao">
      <div class="contentor">
        ${resumo([
          'A Gadgetmetodo, Lda. presta serviços em cinco áreas: Organização e Métodos (O&amp;M); inteligência artificial (IA) e transformação digital; proteção de dados e conformidade (RGPD, NIS2 e governação da IA); estratégia e gestão; e formação, com cursos abertos de IA generativa e o programa AI Essentials à medida. As áreas podem ser contratadas isoladamente ou combinadas num único projeto, com uma equipa e um plano de ação comuns.',
        ], 'Que serviços presta a Gadgetmetodo?')}
        <div class="grelha grelha-3 espaco-topo">
          ${SERVICOS.map(cartaoServico).join('\n          ')}
        </div>
        <p class="espaco-topo mb-0"><a class="botao botao-secundario" href="/catalogo/">${icone('file-text')} Descarregar o catálogo de serviços</a></p>
      </div>
    </section>

    <section class="secao secao-nevoa" aria-labelledby="combinar">
      <div class="contentor">
        <div class="duas-colunas">
          <div>
            <span class="sobretitulo">Porque integrar</span>
            <h2 id="combinar">A tecnologia rende mais numa organização arrumada</h2>
            <p>Um sistema novo aplicado a processos confusos tende a automatizar a confusão. Por isso, os projetos da Gadgetmetodo começam quase sempre pela organização e pelos processos, e só depois avançam para ferramentas e automação.</p>
            <p class="mb-0">A proteção de dados e a segurança da informação entram desde o desenho, e não como correção no fim.</p>
          </div>
          ${listaCheck([
            '<strong>Organização primeiro:</strong> processos simples e responsabilidades claras.',
            '<strong>Tecnologia com propósito:</strong> casos de uso escolhidos pelo retorno esperado.',
            '<strong>Conformidade desde o desenho:</strong> RGPD, NIS2 e governação da IA.',
            '<strong>Pessoas preparadas:</strong> formação e acompanhamento na mudança.',
            '<strong>Resultados medidos:</strong> objetivos e indicadores acompanhados pela gestão.',
          ])}
        </div>
      </div>
    </section>

    ${chamada()}
  `,
});

/* Serviço: Organização e Métodos */
function paginaServico({ s, titulo, descricao, h1, lead, pergunta, resumoTxt, inclui, quando, entregaveis, faq, extra = '', llms, schemaServico, trilho: trilhoProprio }) {
  const trilho = trilhoProprio || [['/', 'Início'], ['/servicos/', 'Serviços'], [s.url, s.nome]];
  const no = schemaServico || {
    '@type': 'Service',
    '@id': URL_BASE + s.url + '#servico',
    name: s.nome,
    serviceType: s.nome,
    description: semTags(resumoTxt[0]),
    provider: { '@id': ORG_ID },
    areaServed: { '@type': 'Country', name: 'Portugal' },
    url: URL_BASE + s.url,
    hasOfferCatalog: { '@type': 'OfferCatalog', name: s.nome, itemListElement: inclui.map(i => ({ '@type': 'Offer', itemOffered: { '@type': 'Service', name: semTags(i) } })) },
  };
  no.__mainEntity = true;
  pagina({
    url: s.url, titulo, descricao, trilho, llms, prioridade: '0.8',
    schemaExtra: [no],
    corpo: `
    ${topoPagina({ trilho, h1, lead })}

    <section class="secao">
      <div class="contentor">
        <div class="duas-colunas texto-largo">
          <div>
            ${resumo(resumoTxt, pergunta)}
            <h2 class="espaco-topo" id="inclui">O que inclui</h2>
            ${listaCheck(inclui)}
          </div>
          <div>
            <div class="cartao">
              <span class="icone">${icone(s.icone)}</span>
              <h2 class="h3" style="font-size:1.2rem">Quando faz sentido</h2>
              ${listaCheck(quando)}
            </div>
          </div>
        </div>
      </div>
    </section>

    ${extra}

    <section class="secao${extra ? '' : ' secao-nevoa'}" aria-labelledby="entregaveis">
      <div class="contentor">
        <div class="duas-colunas">
          <div>
            <span class="sobretitulo">Resultado</span>
            <h2 id="entregaveis">O que recebe</h2>
            <p class="texto-suave">Os entregáveis concretos de cada projeto ficam definidos na proposta. Os mais habituais são:</p>
          </div>
          ${listaCheck(entregaveis)}
        </div>
      </div>
    </section>

    <section class="secao${extra ? ' secao-nevoa' : ''}" aria-labelledby="perguntas">
      <div class="contentor estreito">
        <span class="sobretitulo">Perguntas frequentes</span>
        <h2 id="perguntas">Dúvidas habituais</h2>
        ${faqHtml(faq)}
      </div>
    </section>

    ${servicosRelacionados(s.url, !extra)}

    ${chamada()}
  `,
  });
}

paginaServico({
  s: SERVICOS[0],
  pergunta: 'O que é a consultoria em Organização e Métodos?',
  titulo: 'Consultoria em Organização e Métodos (O&M)',
  descricao: 'Diagnóstico organizacional, mapeamento e redesenho de processos, normas e procedimentos, auditorias internas e planos de melhoria. Consultoria O&M no Porto.',
  h1: 'Consultoria em Organização e Métodos (O&amp;M)',
  lead: 'Organizações mais simples de gerir: estrutura clara, processos documentados e responsabilidades bem definidas.',
  llms: 'Consultoria em Organização e Métodos: diagnóstico, processos, procedimentos, auditorias e planos de melhoria.',
  resumoTxt: [
    'A consultoria em Organização e Métodos (O&amp;M) analisa como uma empresa está organizada e como o trabalho é feito, para eliminar redundâncias, clarificar responsabilidades e reduzir custos de operação.',
    'Na Gadgetmetodo, Lda., cada intervenção começa por um diagnóstico organizacional e termina com processos, procedimentos e indicadores que a equipa consegue aplicar no dia a dia.',
  ],
  inclui: [
    'Diagnóstico organizacional',
    'Arquitetura organizacional: mapeamento, desenho e redesenho da estrutura',
    'Mapeamento e otimização de processos, com base digital',
    'Normas e procedimentos internos',
    'Gestão de atividades e áreas, com definição de funções e responsabilidades',
    'Planos de melhoria: mapeamento, execução e acompanhamento',
    'Auditorias internas de processos e sistemas de gestão',
    'Apoio em licenciamentos, acreditações e certificações',
  ],
  quando: [
    'O crescimento tornou a estrutura atual pesada ou pouco clara',
    'Os processos dependem de pessoas específicas e estão pouco documentados',
    'Há retrabalho, atrasos ou falhas de comunicação entre áreas',
    'A empresa prepara uma certificação, auditoria ou reorganização',
    'Vai ser implementado um novo sistema de gestão (ERP) ou de clientes (CRM)',
  ],
  entregaveis: [
    'Relatório de diagnóstico com prioridades de atuação',
    'Organograma e matriz de funções e responsabilidades',
    'Mapas de processos, na situação atual e na situação proposta',
    'Manual de normas e procedimentos internos',
    'Plano de melhoria com indicadores, responsáveis e calendário',
  ],
  faq: [
    ['O que é a consultoria em Organização e Métodos?', 'É uma área da consultoria de gestão que estuda a estrutura de uma organização e os seus métodos de trabalho para os tornar mais simples, eficientes e controláveis. O resultado típico são processos redesenhados, responsabilidades claras, procedimentos escritos e indicadores de acompanhamento.'],
    ['Qual a diferença entre O&amp;M e uma auditoria?', 'Uma auditoria verifica se os processos cumprem requisitos definidos. A O&amp;M redesenha a forma de trabalhar. As duas abordagens complementam-se: a Gadgetmetodo realiza auditorias internas de processos e sistemas de gestão e usa os resultados para orientar os planos de melhoria.'],
    ['A Gadgetmetodo também implementa as mudanças?', 'Sim, quando o cliente o pretende. A implementação de procedimentos, a formação das equipas e o acompanhamento dos planos de melhoria com indicadores podem fazer parte do âmbito do projeto.'],
  ],
});

paginaServico({
  s: SERVICOS[1],
  pergunta: 'Como ajuda a Gadgetmetodo a adotar a inteligência artificial?',
  titulo: 'Inteligência artificial para empresas',
  descricao: 'Estratégia de IA, casos de uso com maior retorno, arquitetura de sistemas e integrações, automação de marketing e painéis de indicadores para empresas.',
  h1: 'Inteligência artificial (IA) e transformação digital para empresas',
  lead: 'Adotar a IA com ambição estratégica e realismo operacional: começar pelos casos de uso com maior retorno e integrá-los nos sistemas e processos existentes.',
  llms: 'Estratégia e implementação de inteligência artificial, arquitetura de sistemas, automação e análise de dados.',
  resumoTxt: [
    'A Gadgetmetodo, Lda. ajuda empresas a definir e executar a sua estratégia de inteligência artificial (IA): identifica os casos de uso com maior retorno, desenha a arquitetura de sistemas e integrações, implementa soluções digitais e prepara as equipas para as utilizar.',
    'A abordagem combina organização e métodos com tecnologia, para que a IA melhore processos reais e não fique isolada em projetos-piloto.',
  ],
  inclui: [
    'Estratégia de IA alinhada com o negócio e com a oferta da empresa',
    'Identificação e priorização de casos de uso pelo retorno esperado',
    'Roteiro de adoção de IA, com fases e investimento',
    'Diagnóstico de tecnologia, sistemas e integrações',
    'Arquitetura de sistemas e integrações entre aplicações de gestão (ERP), de clientes (CRM) e outras',
    'Fornecimento e implementação de soluções digitais com IA',
    'Automação de marketing e personalização da comunicação com IA',
    'Painéis de indicadores (dashboards) e análise de dados para decisão',
    'Digitalização do desenvolvimento de produto, por exemplo com prototipagem 3D de vestuário',
  ],
  quando: [
    'A administração quer uma estratégia de IA e não apenas ferramentas avulsas',
    'Existem tarefas repetitivas ou decisões que podiam apoiar-se em dados',
    'Os sistemas não comunicam entre si e a informação é copiada à mão',
    'O marketing precisa de personalizar e medir melhor sem perder o toque humano',
    'O desenvolvimento de produto depende de muitas amostras físicas',
  ],
  extra: `<section class="secao secao-nevoa" aria-labelledby="aplicacoes">
      <div class="contentor">
        <div class="cabeca-secao">
          <span class="sobretitulo">Exemplos de aplicação</span>
          <h2 id="aplicacoes">Onde a IA costuma gerar valor</h2>
        </div>
        <div class="grelha grelha-3">
          <div class="cartao"><span class="icone">${icone('users')}</span><h3>Personalização</h3><p>Mensagens, sugestões e propostas ajustadas ao perfil de cada cliente.</p></div>
          <div class="cartao"><span class="icone">${icone('waypoints')}</span><h3>Automação de fluxos</h3><p>Fluxos automáticos ao longo da jornada do cliente: nutrição de contactos, seguimento e recomendações.</p></div>
          <div class="cartao"><span class="icone">${icone('chart-line')}</span><h3>Dados para decisão</h3><p>Painéis com indicadores como o custo de aquisição de cliente, a taxa de conversão e o retorno por canal.</p></div>
          <div class="cartao"><span class="icone">${icone('shirt')}</span><h3>Produto digital</h3><p>Simulações realistas e amostras digitais para reduzir tempo, custo e desperdício no desenvolvimento.</p></div>
          <div class="cartao"><span class="icone">${icone('network')}</span><h3>Integração de sistemas</h3><p>Ligação entre aplicações de gestão, clientes e documentos, com menos trabalho manual.</p></div>
          <div class="cartao"><span class="icone">${icone('layers')}</span><h3>Produtos e serviços</h3><p>Integração de funcionalidades de IA na oferta que a empresa já vende aos seus clientes.</p></div>
        </div>
      </div>
    </section>`,
  entregaveis: [
    'Mapa de casos de uso priorizados por impacto e esforço',
    'Roteiro de adoção e arquitetura de sistemas',
    'Soluções implementadas e parametrizadas',
    'Painéis de indicadores para acompanhamento',
    'Formação das equipas que vão utilizar as soluções',
  ],
  faq: [
    ['A Gadgetmetodo também dá formação em IA às equipas?', 'Sim. Na <a href="/formacoes/">área de formação</a> estão os cursos abertos do percurso IA Generativa no Trabalho, da iniciação à liderança da implementação, com inscrição online e um diagnóstico que indica o curso adequado a cada pessoa. Para administrações e equipas, organizamos também o <a href="/formacoes/ai-essentials/">programa AI Essentials</a> à medida, que pode ser integrado num projeto de IA.'],
    ['Por onde deve uma PME começar a usar inteligência artificial?', 'Pelos processos onde há volume, repetição ou decisões apoiadas em dados. Um diagnóstico inicial identifica e prioriza os casos de uso pelo retorno esperado e pelo esforço de implementação, antes de qualquer investimento em ferramentas.'],
    ['Como é garantida a proteção de dados nos projetos de IA?', 'Cada projeto avalia o impacto no RGPD, incluindo transferências internacionais de dados e contratos com fornecedores, e alinha a solução com os requisitos de segurança da informação, como os da Diretiva NIS2. A governação da IA é definida desde o desenho.'],
    ['A Gadgetmetodo fornece as ferramentas ou só aconselha?', 'Pode fazer as duas coisas. Para além da estratégia e da arquitetura, a Gadgetmetodo fornece e implementa soluções digitais, incluindo licenciamento, instalação, parametrização, formação e acompanhamento.'],
  ],
});

paginaServico({
  s: SERVICOS[2],
  pergunta: 'Que apoio presta a Gadgetmetodo em proteção de dados?',
  titulo: 'Proteção de dados, RGPD, NIS2 e DPO externo',
  descricao: 'Encarregado de Proteção de Dados (DPO) externo, RGPD, Diretiva NIS2, contratos com subcontratantes e governação da inteligência artificial.',
  h1: 'Proteção de dados, RGPD, NIS2 e governação da IA',
  lead: 'Conformidade com bom senso: proteger clientes, parceiros e titulares dos dados sem bloquear a operação nem a inovação.',
  llms: 'Funções de DPO externo, RGPD, NIS2, contratos de subcontratação e governação da IA.',
  resumoTxt: [
    'A Gadgetmetodo, Lda. assegura funções de Encarregado de Proteção de Dados (DPO, Data Protection Officer) e apoia empresas na aplicação do Regulamento Geral sobre a Proteção de Dados (RGPD), da Diretiva NIS2 sobre segurança das redes e da informação e na governação da inteligência artificial.',
    'O objetivo é um quadro de conformidade sólido, proporcional ao risco e compatível com o crescimento do negócio.',
  ],
  inclui: [
    'Funções de Encarregado de Proteção de Dados (DPO) em regime externo',
    'Registo das atividades de tratamento',
    'Revisão de contratos com clientes e subcontratantes: proteção de dados, auditorias e assistência',
    'Avaliação de transferências internacionais de dados',
    'Avaliação do impacto da inteligência artificial no RGPD',
    'Requisitos da Diretiva NIS2 e segurança da informação',
    'Governação e ética da IA: regras de utilização, responsabilidades e controlo',
    'Formação das equipas em privacidade e segurança',
  ],
  quando: [
    'A empresa trata dados pessoais de clientes ou colaboradores em volume',
    'Presta serviços a outras empresas e atua como subcontratante',
    'Clientes ou parceiros exigem garantias contratuais de proteção de dados e segurança',
    'Está a adotar ferramentas de IA que utilizam dados pessoais',
    'Precisa de designar um DPO ou de reforçar a função existente',
  ],
  extra: `<section class="secao secao-nevoa" aria-labelledby="enquadramento">
      <div class="contentor">
        <div class="duas-colunas">
          <div>
            <span class="sobretitulo">Enquadramento</span>
            <h2 id="enquadramento">Três referências que convergem</h2>
            <p>O RGPD, a NIS2 e a governação da IA tratam riscos diferentes, mas pedem às empresas o mesmo tipo de disciplina: saber que dados e sistemas têm, quem é responsável e que controlos existem.</p>
            <p class="mb-0">A Gadgetmetodo trata estes temas em conjunto, com clareza estratégica, realismo operacional e governação com bom senso.</p>
          </div>
          <div class="grelha">
            <div class="cartao"><h3>RGPD</h3><p>Regulamento (UE) 2016/679, sobre a proteção das pessoas singulares no tratamento de dados pessoais.</p></div>
            <div class="cartao"><h3>NIS2</h3><p>Diretiva (UE) 2022/2555, sobre medidas para um elevado nível comum de cibersegurança na União.</p></div>
            <div class="cartao"><h3>Governação da IA</h3><p>Regras internas sobre que ferramentas usar, com que dados, com que responsabilidades e com que controlo.</p></div>
          </div>
        </div>
      </div>
    </section>`,
  entregaveis: [
    'Registo das atividades de tratamento atualizado',
    'Cláusulas e contratos de subcontratação revistos',
    'Análise das transferências internacionais de dados',
    'Política de utilização de IA e matriz de responsabilidades',
    'Plano de ação de segurança da informação alinhado com a NIS2',
  ],
  faq: [
    ['O que faz um Encarregado de Proteção de Dados (DPO)?', 'Informa e aconselha a organização sobre as obrigações do RGPD, controla a conformidade, coopera com a autoridade de controlo, que em Portugal é a Comissão Nacional de Proteção de Dados (CNPD), e é o ponto de contacto para os titulares dos dados.'],
    ['A NIS2 aplica-se à minha empresa?', 'Depende do setor, da dimensão e da posição na cadeia de fornecimento. Mesmo empresas fora do âmbito direto podem ter de cumprir requisitos de segurança impostos contratualmente por clientes abrangidos. Uma análise inicial esclarece o enquadramento de cada caso.'],
    ['A conformidade tem de travar a operação?', 'Não deve. A Gadgetmetodo procura soluções proporcionais ao risco, que protegem clientes, parceiros e titulares dos dados e continuam a permitir que a empresa cresça e inove.'],
  ],
});

paginaServico({
  s: SERVICOS[3],
  pergunta: 'Como apoia a Gadgetmetodo a estratégia e a gestão?',
  titulo: 'Estratégia e apoio à gestão',
  descricao: 'Alinhamento estratégico, missão, visão e valores, planos estratégicos, objetivos e indicadores (KPI), modelos de incentivos e board advisory em inovação.',
  h1: 'Estratégia e apoio à gestão',
  lead: 'Da definição do negócio aos objetivos de cada equipa: estratégia que se traduz em decisões, indicadores e resultados.',
  llms: 'Planeamento estratégico, objetivos e indicadores, incentivos e apoio à administração.',
  resumoTxt: [
    'A Gadgetmetodo, Lda. apoia administrações na definição e execução da estratégia: alinhamento do negócio, missão, visão e valores, planos estratégicos, objetivos e indicadores-chave de desempenho (KPI, Key Performance Indicators) e modelos de incentivos ligados a resultados.',
    'Atua também como conselheira da administração (board advisor) para inovação e tecnologia.',
  ],
  inclui: [
    'Alinhamento estratégico: definição de negócio, missão, visão e valores',
    'Planos estratégicos, incluindo planos de recuperação de margem',
    'Análise de concorrência e de desempenho com base em dados públicos e internos',
    'Objetivos e indicadores (KPI) por área, canal ou equipa',
    'Modelos de incentivos e gratificações indexados a resultados',
    'Desenho de equipas e modelos de operação, incluindo operação omnicanal',
    'Apoio à criação de novas unidades de negócio',
    'Conselho à administração para inovação e tecnologia',
  ],
  quando: [
    'A margem está a cair e é preciso perceber porquê e onde agir',
    'A estratégia existe, mas não chega aos objetivos de cada equipa',
    'Os incentivos não estão ligados aos resultados que interessam à empresa',
    'A empresa vai abrir um novo canal ou uma nova unidade de negócio',
    'A administração quer apoio regular em decisões de inovação e tecnologia',
  ],
  entregaveis: [
    'Plano estratégico com objetivos, iniciativas e responsáveis',
    'Análise de concorrência e de vendas e margens por canal e categoria',
    'Sistema de objetivos e indicadores por equipa',
    'Regulamento de incentivos com critérios de elegibilidade e cálculo',
    'Relatórios de acompanhamento para a administração',
  ],
  faq: [
    ['Como se ligam os incentivos aos objetivos estratégicos?', 'Definindo regras transparentes: critérios de elegibilidade, proporcionalidade do pagamento, tetos máximos, base de cálculo, período de avaliação e aprovação pela administração, todos associados aos indicadores que a empresa quer melhorar, como a margem.'],
    ['O que faz um board advisor para inovação e tecnologia?', 'Apoia a administração a decidir sobre digitalização, tecnologias emergentes e inteligência artificial, alinhando essas decisões com a estratégia, a arquitetura de sistemas e a evolução dos produtos e serviços da empresa.'],
  ],
});

paginaServico({
  s: AI_ESSENTIALS,
  trilho: [['/', 'Início'], ['/formacoes/', 'Formação'], [AI_ESSENTIALS.url, 'AI Essentials para empresas']],
  pergunta: 'O que é a formação AI Essentials?',
  titulo: 'Formação AI Essentials em IA',
  descricao: 'Formação AI Essentials: cinco módulos sobre IA para administrações e equipas, incluindo casos de uso, governação, RGPD e NIS2. Formador com CCP.',
  h1: 'Formação AI Essentials: inteligência artificial para administrações e equipas',
  lead: 'Programa à medida, na sua empresa, em cinco módulos para decidir, utilizar e governar a inteligência artificial com conhecimento de causa.',
  llms: 'Programa de formação AI Essentials em cinco módulos.',
  resumoTxt: [
    'AI Essentials é o programa de formação da Gadgetmetodo, Lda. sobre inteligência artificial (IA) aplicada à gestão. Organiza-se em cinco módulos, dos fundamentos à segurança da informação, e é adaptado ao setor e ao nível de cada grupo, da administração às equipas operacionais.',
    'É ministrado por formador com Certificado de Competências Pedagógicas (CCP).',
  ],
  schemaServico: {
    '@type': 'Course',
    '@id': URL_BASE + AI_ESSENTIALS.url + '#curso',
    name: 'AI Essentials',
    description: 'Programa de formação em inteligência artificial aplicada à gestão, em cinco módulos, para administrações e equipas.',
    provider: { '@id': ORG_ID },
    inLanguage: 'pt-PT',
    url: URL_BASE + AI_ESSENTIALS.url,
    teaches: ['Fundamentos de IA para a administração', 'Casos de uso e integração de IA em sistemas', 'Governação e ética da IA', 'RGPD, privacidade e IA', 'NIS2 e segurança da informação'],
  },
  inclui: [
    'Módulo 1: Fundamentos de IA para a administração',
    'Módulo 2: Casos de uso e integração de IA em sistemas',
    'Módulo 3: Governação e ética da IA',
    'Módulo 4: RGPD, privacidade e IA',
    'Módulo 5: NIS2 e segurança da informação',
  ],
  quando: [
    'A administração precisa de bases sólidas para decidir sobre IA',
    'As equipas já usam ferramentas de IA sem regras comuns',
    'A empresa vai iniciar um projeto de IA e quer preparar as pessoas',
    'É necessário sensibilizar para privacidade e segurança na utilização de IA',
  ],
  extra: `<section class="secao" aria-labelledby="proximas-sessoes">
      <div class="contentor">
        <div class="destaque-formacoes">
          <div>
            <span class="sobretitulo">Cursos abertos</span>
            <h2 id="proximas-sessoes">Prefere um curso aberto, com inscrição individual?</h2>
            <p class="mb-0">O percurso IA Generativa no Trabalho tem quatro cursos sequenciais, da iniciação à liderança da implementação, com inscrição online e um diagnóstico curto de utilização de IA.</p>
          </div>
          <a class="botao" href="/formacoes/">${icone('calendar-days')} Ver a área de formação</a>
        </div>
      </div>
    </section>

    <section class="secao secao-nevoa" aria-labelledby="modulos">
      <div class="contentor">
        <div class="cabeca-secao">
          <span class="sobretitulo">Programa</span>
          <h2 id="modulos">Os cinco módulos</h2>
          <p>A duração, a modalidade e os exemplos práticos são ajustados a cada grupo e definidos na proposta.</p>
        </div>
        <div class="conteudo tabela-rolante">
          <table>
            <thead><tr><th scope="col">Módulo</th><th scope="col">Temas</th></tr></thead>
            <tbody>
              <tr><th scope="row">1. Fundamentos de IA para a administração</th><td>O que a IA faz e não faz, tipos de ferramentas, impacto no negócio e critérios de decisão.</td></tr>
              <tr><th scope="row">2. Casos de uso e integração em sistemas</th><td>Como identificar oportunidades, priorizar pelo retorno e integrar a IA em processos e aplicações existentes.</td></tr>
              <tr><th scope="row">3. Governação e ética da IA</th><td>Regras de utilização, responsabilidades, riscos e controlo.</td></tr>
              <tr><th scope="row">4. RGPD, privacidade e IA</th><td>Dados pessoais em ferramentas de IA, transferências internacionais e contratos com fornecedores.</td></tr>
              <tr><th scope="row">5. NIS2 e segurança da informação</th><td>Requisitos de cibersegurança, boas práticas e o papel de cada pessoa.</td></tr>
            </tbody>
          </table>
        </div>
        <p class="mb-0">O programa pode ser adaptado a áreas específicas. Por exemplo, na versão para marketing e turismo, os módulos tratam automação de marketing, análise de dados para decisões comerciais, gestão inteligente de clientes (CRM) e qualificação de contactos.</p>
      </div>
    </section>`,
  entregaveis: [
    'Sessões de formação adaptadas ao setor e ao público',
    'Materiais de apoio com exemplos práticos',
    'Orientações para a utilização responsável de IA na empresa',
    'Identificação de primeiros casos de uso a explorar',
  ],
  faq: [
    ['A quem se destina a formação AI Essentials?', 'A administrações, direções e equipas que vão decidir sobre IA, utilizá-la no dia a dia ou garantir a sua conformidade. O nível técnico é ajustado a cada grupo.'],
    ['A formação pode ser feita na empresa?', 'Sim. A modalidade, presencial, remota ou mista, e a duração são definidas na proposta, de acordo com a disponibilidade e os objetivos da empresa.'],
    ['A formação pode ser integrada num projeto de IA?', 'Sim. É frequente combinar a formação com um projeto de estratégia ou implementação de IA, para que as equipas aprendam com os casos de uso reais da empresa.'],
  ],
});

// Endereço antigo da página AI Essentials: encaminha para a nova (o .htaccess faz o mesmo com um 301)
fs.mkdirSync(path.join(OUT, 'servicos/formacao-ai-essentials'), { recursive: true });
fs.writeFileSync(path.join(OUT, 'servicos/formacao-ai-essentials/index.html'), `<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <title>AI Essentials para empresas | Gadgetmetodo, Lda.</title>
  <meta name="robots" content="noindex, follow">
  <link rel="canonical" href="${URL_BASE}${AI_ESSENTIALS.url}">
  <meta http-equiv="refresh" content="0; url=${AI_ESSENTIALS.url}">
</head>
<body>
  <p>Esta página mudou para <a href="${AI_ESSENTIALS.url}">${URL_BASE}${AI_ESSENTIALS.url}</a>.</p>
</body>
</html>
`);

const FASES_ABORDAGEM = [
  ['Diagnóstico', 'Levantamento da situação atual: processos, sistemas, equipas, dados e riscos. Identificação das prioridades e oportunidades com maior retorno, com base em informação da própria empresa e, quando útil, em dados públicos do setor.'],
  ['Desenho', 'Definição da solução: modelo organizacional, processos, arquitetura de sistemas, regras de governação e plano de ação com responsáveis, calendário e indicadores.'],
  ['Implementação', 'Execução com as equipas: parametrização de ferramentas, procedimentos, formação e gestão da mudança, com especial cuidado na forma como a mudança é introduzida.'],
  ['Acompanhamento', 'Medição de resultados com indicadores, ajustes ao plano e apoio regular à administração para consolidar a mudança.'],
];

/* A Metodologia (endereço /abordagem/, mantido para não partir ligações) */
pagina({
  url: '/abordagem/',
  titulo: 'A Metodologia',
  descricao: 'Como a Gadgetmetodo trabalha: diagnóstico, desenho, implementação e acompanhamento, com propostas à medida e modelos de colaboração por projeto ou continuados.',
  llms: 'Metodologia em quatro fases, princípios e modelos de colaboração.',
  trilho: [['/', 'Início'], ['/abordagem/', 'A Metodologia']],
  schemaExtra: [{
    '@type': 'ItemList',
    '@id': URL_BASE + '/abordagem/#metodo',
    name: 'Metodologia de projeto da Gadgetmetodo, Lda.',
    description: 'Quatro fases de um projeto de consultoria em organização, IA e proteção de dados.',
    itemListOrder: 'https://schema.org/ItemListOrderAscending',
    numberOfItems: FASES_ABORDAGEM.length,
    itemListElement: FASES_ABORDAGEM.map(([n, t], i) => ({ '@type': 'ListItem', position: i + 1, name: n, description: t })),
    __mainEntity: true,
  }],
  corpo: `
    ${topoPagina({ trilho: [['/', 'Início'], ['/abordagem/', 'A Metodologia']], h1: 'A Metodologia: do diagnóstico aos resultados', lead: 'Um método claro, aplicado com proximidade às pessoas e à realidade de cada empresa.' })}

    <section class="secao">
      <div class="contentor">
        ${resumo([
          'A Gadgetmetodo, Lda. trabalha em quatro fases: diagnóstico, desenho, implementação e acompanhamento. Combina organização e métodos com ferramentas de inteligência artificial e trata a proteção de dados desde o início.',
          'Cada proposta define âmbito, entregáveis, calendário e investimento, e pode assumir a forma de projeto de âmbito fechado ou de acompanhamento continuado.',
        ], 'Como trabalha a Gadgetmetodo?')}
      </div>
    </section>

    <section class="secao secao-nevoa" aria-labelledby="fases">
      <div class="contentor">
        <div class="cabeca-secao">
          <span class="sobretitulo">Método</span>
          <h2 id="fases">Quatro fases</h2>
        </div>
        <ol class="etapas">
          ${FASES_ABORDAGEM.map(([n, t]) => `<li><h3>${n}</h3><p>${t}</p></li>`).join('\n          ')}
        </ol>
      </div>
    </section>

    <section class="secao" aria-labelledby="modelos">
      <div class="contentor">
        <div class="cabeca-secao">
          <span class="sobretitulo">Colaboração</span>
          <h2 id="modelos">Modelos de colaboração</h2>
          <p>O formato adapta-se ao objetivo. Em todos os casos, as condições ficam escritas na proposta.</p>
        </div>
        <div class="grelha grelha-3">
          <div class="cartao"><span class="icone">${icone('target')}</span><h3>Projeto de âmbito fechado</h3><p>Diagnóstico, implementação de uma solução ou <a href="/servicos/organizacao-e-metodos/">reorganização de uma área</a>, com entregáveis definidos e pagamento associado à conclusão ou a marcos do projeto.</p></div>
          <div class="cartao"><span class="icone">${icone('handshake')}</span><h3>Acompanhamento continuado</h3><p>Apoio regular à administração, por exemplo como <a href="/servicos/estrategia-e-gestao/">board advisor</a> ou <a href="/servicos/protecao-de-dados-e-conformidade/">DPO externo</a>, com prestações periódicas, como trimestrais, ao longo de um ou mais anos.</p></div>
          <div class="cartao"><span class="icone">${icone('graduation-cap')}</span><h3>Formação</h3><p><a href="/formacoes/">Cursos abertos de IA generativa</a> ou o <a href="/formacoes/ai-essentials/">programa AI Essentials</a> à medida, isolado ou integrado num projeto.</p></div>
        </div>
      </div>
    </section>

    <section class="secao fundo-escuro" aria-labelledby="valores">
      <div class="contentor">
        <div class="cabeca-secao">
          <span class="sobretitulo">O que nos move</span>
          <h2 id="valores">Três convicções</h2>
        </div>
        <div class="grelha grelha-3">
          <div class="cartao"><span class="icone">${icone('lightbulb')}</span><h3>Acreditamos</h3><p>A consultoria em organização e métodos, apoiada em ferramentas de IA, melhora a eficiência e o custo dos processos e leva as empresas a usar os recursos de forma mais racional. Vemos a criatividade, também com recurso à IA, como um caminho para o futuro.</p></div>
          <div class="cartao"><span class="icone">${icone('rocket')}</span><h3>Inovar</h3><p>Queremos desenvolver soluções simples de utilizar, com abordagens inovadoras e de qualidade, e com especial sensibilidade na gestão da mudança: muitos métodos clássicos já não servem a empresa de hoje.</p></div>
          <div class="cartao"><span class="icone">${icone('users')}</span><h3>Pessoas primeiro</h3><p>O sucesso não se alcança sem relações próximas. Clientes, parceiros e colaboradores estão no centro de cada projeto e a sua personalidade marca tudo o que fazemos.</p></div>
        </div>
      </div>
    </section>

    <section class="secao" aria-labelledby="principios-trabalho">
      <div class="contentor">
        <div class="duas-colunas">
          <div>
            <span class="sobretitulo">Princípios</span>
            <h2 id="principios-trabalho">Como decidimos</h2>
            <p class="lead texto-suave">Clareza estratégica, realismo operacional e governação com bom senso, equilibrando inovação, crescimento do negócio e proteção de clientes, parceiros e titulares dos dados.</p>
          </div>
          <dl class="principios">
            <div><dt>Clareza</dt><dd>Comunicação direta, entregáveis compreensíveis e decisões fundamentadas.</dd></div>
            <div><dt>Método</dt><dd>Diagnóstico rigoroso, processos documentados e indicadores para medir o progresso.</dd></div>
            <div><dt>Integração</dt><dd>Pessoas, processos e tecnologia tratados como um só sistema.</dd></div>
            <div><dt>Confiança</dt><dd>Confidencialidade, proteção de dados e governação com bom senso.</dd></div>
          </dl>
        </div>
      </div>
    </section>

    ${chamada()}
  `,
});

/* Experiência */
const CASOS = [
  {
    id: 'textil', icone: 'shirt', setor: 'Indústria têxtil e vestuário',
    titulo: 'Digitalização do desenvolvimento de produto numa confeção de malhas circulares',
    contexto: 'Empresa de confeção de vestuário em malhas circulares, a investir na digitalização e na eficiência operacional.',
    ambito: ['Licenciamento anual de software de prototipagem 3D de vestuário (CLO3D), na versão empresarial', 'Instalação e parametrização de bibliotecas de tecidos e avatares', 'Integração com moldes digitais', 'Formação das equipas e acompanhamento', 'Suporte técnico'],
    objetivos: ['Reduzir o tempo e o custo de desenvolvimento de amostras', 'Melhorar a comunicação entre as áreas comercial, desenvolvimento, amostras e produção', 'Aumentar a conversão de pedidos em encomendas', 'Reduzir o desperdício físico e preparar a integração futura com o ERP'],
    servicos: [1, 0],
  },
  {
    id: 'tecnologia', icone: 'file-text', setor: 'Tecnologia e gestão documental',
    titulo: 'Estratégia de IA e proteção de dados numa empresa de serviços tecnológicos',
    contexto: 'Empresa de tecnologia que pretende adotar a IA com ambição estratégica, dentro de um quadro sólido de proteção de dados, privacidade e conformidade regulatória.',
    ambito: ['Conselho à administração para inovação e tecnologia (board advisor)', 'Funções de Encarregado de Proteção de Dados (DPO)', 'Estratégia de IA alinhada com a oferta da empresa e roteiro de adoção', 'Diagnóstico de tecnologia, sistemas e integrações', 'Governação da IA com RGPD e NIS2, incluindo contratos com clientes e subcontratantes', 'Formação AI Essentials em cinco módulos', 'Acompanhamento regular ao longo de dois anos'],
    objetivos: ['Identificar os casos de uso de IA com maior retorno', 'Integrar a IA nos produtos e serviços da empresa', 'Avaliar o impacto da IA no RGPD e nas transferências internacionais de dados', 'Alinhar a IA com a NIS2 e a segurança da informação'],
    servicos: [1, 2, 3, 4],
  },
  {
    id: 'turismo', icone: 'wine', setor: 'Turismo premium',
    titulo: 'Inteligência artificial e automação de marketing numa agência de enoturismo',
    contexto: 'Agência de enoturismo especializada em itinerários personalizados e de gama alta.',
    ambito: ['Diagnóstico de campanhas, gestão de clientes (CRM) e website', 'Mapeamento da jornada do cliente, da inspiração à reserva', 'Fluxos automáticos com IA: nutrição de contactos, seguimento e recomendações', 'Comunicação multicanal automatizada', 'Definição de indicadores e painéis em tempo real', 'Formação prática da equipa'],
    objetivos: ['Escalar a personalização mantendo o toque humano', 'Otimizar campanhas e investimento digital', 'Medir com rigor o impacto das ações de marketing', 'Preparar um roteiro tecnológico alinhado com o posicionamento da marca'],
    servicos: [1, 4],
  },
  {
    id: 'retalho', icone: 'shopping-bag', setor: 'Retalho especializado',
    titulo: 'Plano estratégico de recuperação de margem num retalhista omnicanal de tecnologia',
    contexto: 'Retalhista com loja online, lojas físicas, vendas a empresas e assistência técnica, confrontado com uma tendência de perda de margem.',
    ambito: ['Análise da concorrência com base na Informação Empresarial Simplificada (IES)', 'Análise de vendas e margens por canal e categoria a partir do ERP', 'Organização das equipas por canal: comércio eletrónico e marketplaces, lojas físicas, vendas a empresas e assistência técnica', 'Objetivos de vendas e margem por canal', 'Modelo de gratificação indexado à margem, com regras de elegibilidade e cálculo', 'Criação de uma unidade de negócio de assistência técnica'],
    objetivos: ['Inverter a tendência de perda de margem', 'Ligar os incentivos das equipas aos resultados', 'Garantir uma estratégia omnicanal coerente entre equipas', 'Criar uma nova fonte de receita com margem elevada'],
    servicos: [3, 0],
  },
];

pagina({
  url: '/experiencia/',
  titulo: 'Experiência e exemplos de intervenção',
  descricao: 'Exemplos de intervenção da Gadgetmetodo na indústria têxtil, tecnologia, turismo e retalho: IA, organização, proteção de dados e estratégia.',
  tipo: 'CollectionPage',
  llms: 'Exemplos de intervenção por setor, sem identificação de clientes.',
  trilho: [['/', 'Início'], ['/experiencia/', 'Experiência']],
  corpo: `
    ${topoPagina({ trilho: [['/', 'Início'], ['/experiencia/', 'Experiência']], h1: 'Experiência e exemplos de intervenção', lead: 'Quatro exemplos do tipo de trabalho que a Gadgetmetodo desenvolve. Por confidencialidade, os clientes não são identificados.' })}

    <section class="secao">
      <div class="contentor">
        ${resumo([
          'A Gadgetmetodo, Lda. tem experiência na indústria têxtil e de vestuário, na tecnologia e gestão documental, no turismo premium e no retalho especializado. As intervenções vão da digitalização do desenvolvimento de produto à estratégia de IA, às funções de DPO, à automação de marketing e aos planos estratégicos de recuperação de margem.',
        ], 'Em que setores tem experiência a Gadgetmetodo?')}
        <div class="espaco-topo">
          ${CASOS.map(c => `<article class="caso" id="${c.id}">
            <div>
              <span class="icone">${icone(c.icone)}</span>
              <p class="setor mb-0">${c.setor}</p>
              <h2>${c.titulo}</h2>
              <p>${c.contexto}</p>
              <p class="mb-0 nota">Serviços: ${c.servicos.map(i => `<a href="${SERVICOS[i].url}">${SERVICOS[i].nome}</a>`).join(', ')}</p>
            </div>
            <div>
              <h3>Âmbito</h3>
              ${listaCheck(c.ambito)}
              <h3>Objetivos</h3>
              ${listaCheck(c.objetivos)}
            </div>
          </article>`).join('\n          ')}
        </div>
      </div>
    </section>

    ${chamada({ titulo: 'Tem um desafio semelhante?', texto: 'Falemos sobre o seu caso. Cada proposta é preparada à medida, depois de uma primeira conversa sobre objetivos e prioridades.' })}
  `,
});

/* Sobre */
pagina({
  url: '/sobre/',
  titulo: 'Sobre nós: consultora de gestão no Porto',
  descricao: 'A Gadgetmetodo, Lda. é uma consultora do Porto em organização e métodos, IA aplicada e proteção de dados. Conheça a empresa e quem acompanha os projetos.',
  tipo: 'AboutPage',
  llms: 'Quem somos, identidade e perfil do responsável pelos projetos.',
  trilho: [['/', 'Início'], ['/sobre/', 'Sobre']],
  schemaExtra: [PESSOA],
  corpo: `
    ${topoPagina({ trilho: [['/', 'Início'], ['/sobre/', 'Sobre']], h1: 'Sobre a Gadgetmetodo, Lda.', lead: 'Uma consultora do Porto que liga organização, tecnologia e proteção de dados para ajudar as empresas a fazer acontecer.' })}

    <section class="secao">
      <div class="contentor">
        <div class="duas-colunas texto-largo">
          <div>
            ${resumo([
              `A Gadgetmetodo, Lda. é uma sociedade por quotas portuguesa, com sede na ${MORADA.rua}, no Porto. Dedica-se à consultoria em Organização e Métodos (O&amp;M), à inteligência artificial aplicada à gestão, à proteção de dados e conformidade e ao apoio estratégico a administrações, e desenvolve formação em inteligência artificial: o percurso IA Generativa no Trabalho e o programa AI Essentials.`,
            ], 'Quem é a Gadgetmetodo?')}
            <div class="conteudo espaco-topo">
              <h2>Quem somos</h2>
              <p>A Gadgetmetodo parte de uma convicção: a eficiência de uma empresa depende, antes de mais, da forma como está organizada. A tecnologia, e hoje em particular a inteligência artificial, acelera o que já funciona bem, mas raramente resolve o que está mal organizado.</p>
              <p>Por isso, cada projeto junta três dimensões: <a href="/servicos/organizacao-e-metodos/">o método</a>, para simplificar e organizar; <a href="/servicos/inteligencia-artificial/">a tecnologia</a>, para automatizar e medir; e <a href="/servicos/protecao-de-dados-e-conformidade/">a conformidade</a>, para proteger clientes, parceiros e titulares dos dados. O <a href="/servicos/estrategia-e-gestao/">apoio à estratégia</a> e a <a href="/formacoes/">formação</a>, com cursos abertos de IA generativa e o programa AI Essentials à medida, completam a oferta.</p>
              <h2>A nossa marca</h2>
              <p>O símbolo da Gadgetmetodo é formado por módulos geométricos que se interligam para formar uma solução. O azul-petróleo e o azul-marinho transmitem solidez e confiança. A assinatura <strong>Simplificar · Organizar · Fazer acontecer</strong> resume a forma como trabalhamos.</p>
            </div>
          </div>
          <dl class="factos" style="grid-template-columns:1fr">
            <div><dt>Designação</dt><dd>Gadgetmetodo, Lda.</dd></div>
            <div><dt>Forma jurídica</dt><dd>Sociedade por quotas</dd></div>
            <div><dt>NIPC</dt><dd>513 978 011</dd></div>
            <div><dt>Sede</dt><dd>${MORADA.rua}<br>${MORADA.cp} ${MORADA.local}, ${MORADA.pais}</dd></div>
            <div><dt>Telefone</dt><dd><a href="${TEL_LINK}">${TEL_HTML}</a></dd></div>
            <div><dt>Email</dt><dd><a href="mailto:${EMAIL}">${EMAIL}</a></dd></div>
            <div><dt>Website</dt><dd>www.gadgetmetodo.pt</dd></div>
          </dl>
        </div>
      </div>
    </section>

    <section class="secao secao-nevoa" aria-labelledby="responsavel" id="jorge-sa-couto">
      <div class="contentor">
        <span class="sobretitulo">Equipa</span>
        <h2 id="responsavel">Quem acompanha os projetos</h2>
        <div class="duas-colunas texto-largo">
          <div class="perfil">
            <div class="monograma" aria-hidden="true">JSC</div>
            <div>
              <h3 class="mb-0">Jorge Sá Couto</h3>
              <p class="funcao">Gestor de projeto e consultor</p>
              <p>Licenciado em Engenharia Informática, com formação complementar em economia, gestão e estratégia de inteligência artificial. Acompanha os projetos da Gadgetmetodo da fase de diagnóstico à implementação, com especial foco na ligação entre organização, tecnologia e proteção de dados.</p>
            </div>
          </div>
          <div class="cartao">
            <h3>Formação académica e certificações</h3>
            ${listaCheck([
              'Licenciatura em Engenharia Informática, Universidade NOVA de Lisboa',
              'Frequência do curso de Economia, Universidade de Coimbra',
              'MBA em Gestão, ISCTE - Instituto Universitário de Lisboa',
              'Certificado The Business of AI: Strategies for Leaders, London Business School',
              'Certificado de Competências Pedagógicas (CCP)',
            ])}
          </div>
        </div>
      </div>
    </section>

    ${chamada()}
  `,
});

/* Perguntas frequentes */
const TODAS_FAQ = [...FAQ_EMPRESA, ...FAQ_SERVICOS, ...FAQ_FORMACAO, ...FAQ_FUNCIONAMENTO];
const faqExtra = [];
faqExtra.__faq = faqSchema(TODAS_FAQ);
pagina({
  url: '/perguntas-frequentes/',
  titulo: 'Perguntas frequentes',
  descricao: 'Respostas sobre a Gadgetmetodo, Lda.: organização e métodos, inteligência artificial, DPO, RGPD, NIS2, cursos de IA e inscrições, propostas e funcionamento dos projetos.',
  tipo: 'FAQPage',
  prioridade: '0.8',
  llms: 'Perguntas e respostas sobre a empresa, os serviços, a formação (cursos, nível, inscrição e pré-inscrição, requisitos, avaliação) e o funcionamento dos projetos.',
  trilho: [['/', 'Início'], ['/perguntas-frequentes/', 'Perguntas frequentes']],
  schemaExtra: faqExtra,
  corpo: `
    ${topoPagina({ trilho: [['/', 'Início'], ['/perguntas-frequentes/', 'Perguntas frequentes']], h1: 'Perguntas frequentes', lead: 'Respostas diretas sobre a empresa, os serviços, a formação e a forma como trabalhamos.' })}

    <section class="secao">
      <div class="contentor estreito">
        <div class="faq-grupo">
          <h2>Sobre a empresa</h2>
          ${faqHtml(FAQ_EMPRESA)}
        </div>
        <div class="faq-grupo">
          <h2>Serviços</h2>
          ${faqHtml(FAQ_SERVICOS)}
        </div>
        <div class="faq-grupo">
          <h2 id="formacao">Formação</h2>
          ${faqHtml(FAQ_FORMACAO)}
        </div>
        <div class="faq-grupo">
          <h2>Propostas e funcionamento</h2>
          ${faqHtml(FAQ_FUNCIONAMENTO)}
        </div>
      </div>
    </section>

    ${chamada({ titulo: 'Não encontrou a resposta?', texto: 'Envie-nos a sua pergunta. Respondemos com a informação de que precisa para decidir.' })}
  `,
});

/* Contactos e formulário */
const OPCOES_SERVICOS = [
  ...SERVICOS.map(s => [s.chave || s.url.split('/')[2], s.nome]),
  ['outro', 'Outro assunto ou ainda não sei'],
];
pagina({
  url: '/contactos/',
  titulo: 'Contactos e pedidos de orçamento',
  descricao: `Peça um orçamento ou informações à Gadgetmetodo, Lda., consultora no Porto: formulário online, telefone ${TEL} ou email ${EMAIL}.`,
  tipo: 'ContactPage',
  prioridade: '0.8',
  llms: 'Formulário de pedido de orçamento ou informações, morada, telefone e email.',
  trilho: [['/', 'Início'], ['/contactos/', 'Contactos']],
  scripts: ['/assets/js/formulario.js'],
  corpo: `
    ${topoPagina({ trilho: [['/', 'Início'], ['/contactos/', 'Contactos']], h1: 'Contactos e pedidos de orçamento', lead: 'Peça um orçamento ou informações sobre os nossos serviços. Respondemos com uma proposta à medida.', atualizado: false })}

    <section class="secao">
      <div class="contentor">
        <div class="duas-colunas texto-largo">
          <div>
            <h2 id="formulario">Pedido de orçamento ou informações</h2>
            <p class="texto-suave">Os campos assinalados com * são obrigatórios. Recebe uma confirmação por email logo após o envio.</p>
            <form class="formulario" id="pedido" action="/formulario/enviar.php" method="post" accept-charset="utf-8">
              <fieldset class="campo-grupo">
                <legend>Tipo de pedido *</legend>
                <div class="opcoes opcoes-linha">
                  <label class="opcao"><input type="radio" name="tipo" value="orcamento" checked required> Pedido de orçamento</label>
                  <label class="opcao"><input type="radio" name="tipo" value="informacoes"> Pedido de informações</label>
                </div>
              </fieldset>

              <fieldset class="campo-grupo" data-tipo-cliente>
                <legend>Faz o pedido como *</legend>
                <div class="opcoes opcoes-linha">
                  <label class="opcao"><input type="radio" name="tipo_cliente" value="empresa" checked required> Empresa ou outra organização</label>
                  <label class="opcao"><input type="radio" name="tipo_cliente" value="particular"> Particular</label>
                </div>
              </fieldset>

              <div class="campos-2">
                <div class="campo">
                  <label for="f-nome">Nome *</label>
                  <input id="f-nome" name="nome" type="text" autocomplete="name" required maxlength="100">
                  <p class="erro-campo" id="e-nome" hidden></p>
                </div>
                <div class="campo" data-so-empresa>
                  <label for="f-empresa">Empresa ou organização *</label>
                  <input id="f-empresa" name="empresa" type="text" autocomplete="organization" required maxlength="120">
                  <p class="erro-campo" id="e-empresa" hidden></p>
                </div>
                <div class="campo">
                  <label for="f-email">Email *</label>
                  <input id="f-email" name="email" type="email" autocomplete="email" required maxlength="160">
                  <p class="erro-campo" id="e-email" hidden></p>
                </div>
                <div class="campo">
                  <label for="f-telemovel">Telemóvel *</label>
                  <input id="f-telemovel" name="telemovel" type="tel" autocomplete="tel" inputmode="tel" required maxlength="20" pattern="[0-9 +\\(\\)\\-]{9,20}" placeholder="+351 912 345 678">
                  <p class="erro-campo" id="e-telemovel" hidden></p>
                </div>
              </div>

              <fieldset class="campo-grupo" id="g-servicos">
                <legend>Serviços de interesse *</legend>
                <p class="ajuda">Selecione um ou mais.</p>
                <div class="opcoes opcoes-grelha">
                  ${OPCOES_SERVICOS.map(([v, n]) => `<label class="opcao"><input type="checkbox" name="servicos[]" value="${v}"> ${n}</label>`).join('\n                  ')}
                </div>
                <p class="erro-campo" id="e-servicos" hidden></p>
              </fieldset>

              <div class="campo">
                <div class="campo-cabeca">
                  <label for="f-mensagem">Mensagem *</label>
                  <button class="botao-ditado" type="button" aria-pressed="false" hidden>${icone('mic')}<span>Ditar</span></button>
                </div>
                <textarea id="f-mensagem" name="mensagem" rows="7" required maxlength="5000" aria-describedby="ajuda-mensagem" placeholder="Descreva o desafio, o objetivo ou a informação de que precisa."></textarea>
                <p class="ajuda" id="ajuda-mensagem">Pode escrever ou ditar a mensagem: o texto falado é transcrito para o campo e pode ser corrigido antes do envio. Em alternativa, use o ditado do teclado do seu dispositivo.</p>
                <p class="ajuda estado-ditado" id="estado-ditado" aria-live="polite"></p>
                <p class="erro-campo" id="e-mensagem" hidden></p>
              </div>

              <div class="campo-oculto" aria-hidden="true">
                <label for="f-website">Não preencher</label>
                <input id="f-website" name="website" type="text" tabindex="-1" autocomplete="off">
              </div>
              <input type="hidden" name="t" value="">

              <div class="campo">
                <label class="opcao consentimento"><input type="checkbox" name="consentimento" value="sim" required> <span>Li a <a href="/privacidade/">política de privacidade</a> e autorizo o tratamento dos meus dados para resposta a este pedido. *</span></label>
                <p class="erro-campo" id="e-consentimento" hidden></p>
              </div>

              <div class="formulario-acoes">
                <button class="botao" type="submit">Enviar pedido ${seta}</button>
              </div>
              <div class="formulario-estado" role="status" aria-live="polite"></div>
            </form>
          </div>

          <aside aria-labelledby="contactos-diretos">
            <h2 id="contactos-diretos" style="font-size:1.4rem">Contactos diretos</h2>
            <ul class="contactos-lista">
              <li><span class="icone">${icone('map-pin')}</span><div><strong>Morada</strong>${MORADA.rua}<br>${MORADA.cp} ${MORADA.local}, ${MORADA.pais}<br><a href="${MAPA}" rel="noopener">Ver no mapa</a></div></li>
              <li><span class="icone">${icone('phone')}</span><div><strong>Telefone</strong><a href="${TEL_LINK}">${TEL_HTML}</a><br><span class="nota">Chamada para a rede móvel nacional</span></div></li>
              <li><span class="icone">${icone('mail')}</span><div><strong>Email</strong><a href="mailto:${EMAIL}">${EMAIL}</a></div></li>
            </ul>
            <p class="nota espaco-topo mb-0">Os dados enviados são usados apenas para responder ao seu pedido. Saiba mais na <a href="/privacidade/">política de privacidade</a>.</p>
          </aside>
        </div>
      </div>
    </section>
  `,
});

/* Confirmação de envio (sem JavaScript) */
pagina({
  url: '/contactos/obrigado/',
  titulo: 'Pedido enviado',
  descricao: 'Confirmação de envio do pedido de orçamento ou informações.',
  indexar: false,
  trilho: [['/', 'Início'], ['/contactos/', 'Contactos'], ['/contactos/obrigado/', 'Pedido enviado']],
  corpo: `
    <section class="secao">
      <div class="contentor estreito">
        <span class="sobretitulo">Pedido enviado</span>
        <h1>Obrigado pelo seu contacto</h1>
        <p class="lead">Recebemos o seu pedido e enviámos uma confirmação para o seu email. Um membro da nossa equipa entrará em contacto consigo brevemente.</p>
        <div class="acoes">
          <a class="botao" href="/">Voltar à página inicial ${seta}</a>
          <a class="botao botao-secundario" href="/servicos/">Ver serviços</a>
        </div>
      </div>
    </section>
  `,
});

/* Catálogo */
const CATALOGO_PDF = '/catalogo-servicos-2026.pdf';
pagina({
  url: '/catalogo/',
  titulo: 'Catálogo de Serviços 2026 (PDF)',
  descricao: 'Descarregue o Catálogo de Serviços 2026 da Gadgetmetodo, Lda.: áreas de serviço, formação, metodologia, exemplos de intervenção e contactos, em 16 páginas (PDF).',
  prioridade: '0.7',
  llms: 'Página de download do Catálogo de Serviços 2026 em PDF.',
  trilho: [['/', 'Início'], ['/catalogo/', 'Catálogo de serviços']],
  schemaExtra: [{
    '@type': 'DigitalDocument',
    '@id': URL_BASE + '/catalogo/#catalogo',
    name: 'Catálogo de Serviços 2026',
    description: 'Catálogo da Gadgetmetodo, Lda. com as cinco áreas de serviço (incluindo a formação: percurso IA Generativa no Trabalho e programa AI Essentials à medida), a metodologia em quatro fases, os modelos de colaboração, exemplos de intervenção e contactos.',
    url: URL_BASE + CATALOGO_PDF,
    encodingFormat: 'application/pdf',
    inLanguage: 'pt-PT',
    datePublished: HOJE,
    publisher: { '@id': ORG_ID },
    image: URL_BASE + '/assets/img/catalogo-capa.jpg',
    __mainEntity: true,
  }],
  corpo: `
    ${topoPagina({ trilho: [['/', 'Início'], ['/catalogo/', 'Catálogo de serviços']], h1: 'Catálogo de Serviços 2026', lead: 'As cinco áreas de serviço da Gadgetmetodo, a forma como trabalhamos e exemplos de intervenção, num documento de 16 páginas.' })}

    <section class="secao">
      <div class="contentor">
        <div class="duas-colunas">
          <div>
            ${resumo([
              'O Catálogo de Serviços 2026 da Gadgetmetodo, Lda. apresenta, em 16 páginas, as cinco áreas de serviço (Organização e Métodos, inteligência artificial e transformação digital, proteção de dados e conformidade, estratégia e gestão, e formação), as questões a que cada uma responde, o que inclui e os entregáveis habituais. Na formação, resume o percurso IA Generativa no Trabalho, com quatro cursos abertos, e o programa AI Essentials à medida.',
              'Inclui também a metodologia em quatro fases, os modelos de colaboração, exemplos de intervenção por setor e os contactos.',
            ], 'O que contém o catálogo?')}
            <div class="acoes" style="margin-top:24px">
              <a class="botao" href="${CATALOGO_PDF}">${icone('file-text')} Descarregar o catálogo (PDF, 1,1 MB)</a>
              <a class="botao botao-secundario" href="/contactos/#formulario">Pedir proposta</a>
            </div>
            <h2 class="espaco-topo" style="font-size:1.3rem">Conteúdo</h2>
            ${listaCheck([
              'Síntese e ponto de vista sobre organização, tecnologia e conformidade',
              'Mapa das cinco áreas de serviço',
              'Uma página por área: questões, o que inclui e entregáveis',
              'A Metodologia em quatro fases e modelos de colaboração',
              'Exemplos de intervenção na indústria, tecnologia, turismo e retalho',
              'Equipa, como iniciar um projeto e contactos',
            ])}
          </div>
          <a href="${CATALOGO_PDF}" aria-label="Abrir o Catálogo de Serviços 2026 (PDF)">
            <img src="/assets/img/catalogo-capa.jpg" alt="Capa do Catálogo de Serviços 2026 da Gadgetmetodo, Lda." width="960" height="679" loading="lazy" style="width:100%;height:auto;border:1px solid var(--cinzento-claro);border-radius:var(--raio);box-shadow:var(--sombra)">
          </a>
        </div>
      </div>
    </section>

    ${chamada()}
  `,
});

/* Formação: área de formação (PHP; cursos e sessões lidos da base de dados da área reservada) */
pagina({
  url: '/formacoes/',
  ficheiro: 'index.php',
  php: `<?php require __DIR__ . '/../clientes/inc/publico.php'; $pagina = formacoes_publico(); ?>\n`,
  jsonldExtra: '<?= $pagina[\'jsonld\'] ?>',
  estilos: [CSS_AREA],
  tipo: 'CollectionPage',
  titulo: 'Formação em inteligência artificial',
  descricao: 'Formação da Gadgetmetodo, Lda.: percurso IA Generativa no Trabalho, com quatro cursos abertos e inscrição online, e programa AI Essentials à medida para administrações e equipas.',
  prioridade: '0.9',
  llms: 'Área de formação: percurso IA Generativa no Trabalho (4 cursos com página, ficha em PDF, inscrição e pré-inscrição online), calendário e programa AI Essentials à medida.',
  trilho: [['/', 'Início'], ['/formacoes/', 'Formação']],
  corpo: `
    ${topoPagina({ trilho: [['/', 'Início'], ['/formacoes/', 'Formação']], h1: 'Formação', lead: 'Cursos abertos de inteligência artificial generativa, com inscrição online, e programas à medida para administrações e equipas.', atualizado: false })}

    <section class="secao">
      <div class="contentor">
        <div class="duas-colunas texto-largo">
          ${resumo([
            'A Gadgetmetodo, Lda. dá formação em inteligência artificial (IA) a empresas e profissionais em dois formatos: cursos abertos, com inscrição individual, organizados no percurso IA Generativa no Trabalho (quatro cursos de seis horas, da iniciação à liderança da implementação); e programas à medida, como o <a href="/formacoes/ai-essentials/">AI Essentials</a>, para administrações e equipas.',
            'Cada curso tem uma página com o programa, os temas e os casos práticos de cada sessão, uma ficha para descarregar em PDF e a inscrição online. Sem datas agendadas, a pré-inscrição garante o convite quando forem marcadas.',
          ], 'Que formação oferece a Gadgetmetodo?')}
          <nav class="atalhos-formacao" aria-label="Nesta página">
            <a href="#percurso">${icone('route')}<span><strong>Cursos abertos</strong>Percurso IA Generativa no Trabalho</span></a>
            <a href="#calendario">${icone('calendar-days')}<span><strong>Calendário</strong>Próximas sessões e inscrições</span></a>
            <a href="#a-medida">${icone('building-2')}<span><strong>Formação à medida</strong>AI Essentials para administrações e equipas</span></a>
          </nav>
        </div>
      </div>
    </section>

    <?= $pagina['conteudo'] ?>

    <section class="secao secao-nevoa" aria-labelledby="a-medida">
      <div class="contentor">
        <div class="destaque-formacoes">
          <div>
            <span class="sobretitulo">Formação à medida</span>
            <h2 id="a-medida">AI Essentials: inteligência artificial para administrações e equipas</h2>
            <p class="mb-0">Programa em cinco módulos, dos fundamentos à segurança da informação, organizado na sua empresa, com duração, modalidade e exemplos ajustados ao setor e a cada grupo. Ministrado por formador com Certificado de Competências Pedagógicas (CCP).</p>
          </div>
          <a class="botao" href="/formacoes/ai-essentials/">${icone('graduation-cap')} Conhecer o AI Essentials</a>
        </div>
      </div>
    </section>

    <section class="secao" aria-labelledby="como-inscrever">
      <div class="contentor">
        <div class="duas-colunas">
          <div>
            ${resumo([
              'Qualquer empresa ou profissional pode inscrever-se nos cursos abertos nesta página, sem conta. A inscrição fica pendente até ser confirmada por email pela Gadgetmetodo.',
              'Os clientes com acesso à área reservada podem também inscrever formandos e acompanhar as inscrições da sua equipa.',
            ], 'Quem se pode inscrever?')}
          </div>
          <div>
            <h2 id="como-inscrever" style="font-size:1.3rem">Como se inscrever</h2>
            <ol class="etapas etapas-compactas">
              <li><h3>Escolha o curso</h3><p>Veja o programa, os temas, os casos práticos e a ficha em PDF.</p></li>
              <li><h3>Inscreva-se ou faça a pré-inscrição</h3><p>Com datas agendadas, inscreve-se numa edição do curso, que inclui todas as sessões. Sem datas, a <a href="/formacoes/pre-inscricao.php">pré-inscrição</a> garante que recebe o convite quando forem marcadas.</p></li>
              <li><h3>Receba a confirmação</h3><p>Recebe um email logo a seguir e a confirmação da Gadgetmetodo nos dias úteis seguintes.</p></li>
            </ol>
            <p class="espaco-topo-curto mb-0"><a class="botao" href="/formacoes/pre-inscricao.php">${icone('clipboard-list')} Fazer pré-inscrição</a></p>
          </div>
        </div>
      </div>
    </section>

    <section class="secao secao-nevoa" aria-labelledby="perguntas-formacao">
      <div class="contentor estreito">
        <div class="cabeca-secao"><span class="sobretitulo">Perguntas frequentes</span><h2 id="perguntas-formacao">Perguntas sobre a formação</h2></div>
        ${faqHtml(FAQ_FORMACAO.slice(1, 8))}
        <p class="espaco-topo mb-0"><a href="/perguntas-frequentes/#formacao">Ver todas as perguntas frequentes</a></p>
      </div>
    </section>

    ${chamada({ titulo: 'Prefere uma formação só para a sua equipa?', texto: 'Organizamos qualquer curso, ou o programa AI Essentials, na sua empresa, com datas, duração e exemplos ajustados ao setor e às funções do grupo.' })}
  `,
});

pagina({
  url: '/formacoes/inscricao.php',
  php: `<?php require __DIR__ . '/../clientes/inc/publico.php'; $pagina = inscricao_publica(); ?>\n`,
  estilos: [CSS_AREA],
  indexar: false,
  titulo: 'Inscrição em formação',
  descricao: 'Inscrição numa edição de um curso da Gadgetmetodo, Lda., com as datas de todas as sessões e o programa do curso.',
  trilho: [['/', 'Início'], ['/formacoes/', 'Formação'], ['/formacoes/inscricao.php', 'Inscrição']],
  corpo: `
    ${topoPagina({ trilho: [['/', 'Início'], ['/formacoes/', 'Formação'], ['/formacoes/inscricao.php', 'Inscrição']], h1: 'Inscrição em formação', lead: 'Preencha os dados da empresa e do formando. Nos cursos de inteligência artificial, responda também ao diagnóstico de utilização de IA.', atualizado: false })}

    <?= $pagina['conteudo'] ?>
  `,
});

pagina({
  url: '/formacoes/pre-inscricao.php',
  php: `<?php require __DIR__ . '/../clientes/inc/publico.php'; $pagina = pre_inscricao_publica(); ?>
`,
  estilos: [CSS_AREA],
  indexar: false,
  titulo: 'Pré-inscrição em curso',
  descricao: 'Pré-inscrição num curso da Gadgetmetodo, Lda.: receba o convite para a inscrição quando forem marcadas as datas.',
  trilho: [['/', 'Início'], ['/formacoes/', 'Formação'], ['/formacoes/pre-inscricao.php', 'Pré-inscrição']],
  corpo: `
    ${topoPagina({ trilho: [['/', 'Início'], ['/formacoes/', 'Formação'], ['/formacoes/pre-inscricao.php', 'Pré-inscrição']], h1: 'Pré-inscrição', lead: 'Indique o curso que pretende e deixe os seus dados: enviamos-lhe o convite para a inscrição assim que houver datas. Não fica obrigado a inscrever-se.', atualizado: false })}

    <?= $pagina['conteudo'] ?>
  `,
});

pagina({
  url: '/formacoes/curso.php',
  php: `<?php require __DIR__ . '/../clientes/inc/publico.php'; $pagina = curso_publico(); ?>\n`,
  cabecaPhp: { titulo: "<?= h($pagina['titulo']) ?>", descricao: "<?= h($pagina['descricao']) ?>", canonical: "<?= h($pagina['canonical']) ?>" },
  jsonldExtra: '<?= $pagina[\'jsonld\'] ?>',
  estilos: [CSS_AREA],
  scripts: [JS_COPIAR],
  titulo: 'Curso de formação',
  descricao: 'Programa, módulos, temas e próximas sessões de um curso da Gadgetmetodo, Lda.',
  corpo: `
    <?= $pagina['conteudo'] ?>

    ${chamada({ titulo: 'Quer este curso para a sua equipa?', texto: 'Organizamos qualquer curso na sua empresa, com datas, duração e exemplos ajustados ao setor e às funções do grupo.' })}
  `,
});

/* Privacidade */
pagina({
  url: '/privacidade/',
  titulo: 'Política de privacidade',
  descricao: 'Como a Gadgetmetodo, Lda. trata os dados pessoais de quem a contacta e visita este site. Sem cookies, sem ferramentas de análise e sem conteúdos de terceiros.',
  prioridade: '0.3',
  llms: 'Política de privacidade do site.',
  trilho: [['/', 'Início'], ['/privacidade/', 'Política de privacidade']],
  corpo: `
    ${topoPagina({ trilho: [['/', 'Início'], ['/privacidade/', 'Política de privacidade']], h1: 'Política de privacidade', lead: 'Como tratamos os dados pessoais de quem nos contacta e visita este site.' })}

    <section class="secao">
      <div class="contentor estreito conteudo">
        <h2>1. Responsável pelo tratamento</h2>
        <p>Gadgetmetodo, Lda., com sede na ${MORADA.rua}, ${MORADA.cp} ${MORADA.local}, Portugal. Contacto para questões de proteção de dados: <a href="mailto:${EMAIL}">${EMAIL}</a>.</p>

        <h2>2. Que dados tratamos</h2>
        <ul>
          <li><strong>Dados do formulário de contacto:</strong> nome, indicação de pedido particular ou de empresa e, neste caso, o nome da empresa, email, telemóvel, tipo de pedido, serviços de interesse e mensagem.</li>
          <li><strong>Dados que nos envia</strong> por email ou telefone: nome, contactos, empresa e o conteúdo da mensagem.</li>
          <li><strong>Dados técnicos de acesso</strong> registados pelo serviço de alojamento do site, como o endereço IP, a data e hora do pedido, a página pedida e o tipo de navegador, para garantir o funcionamento e a segurança do site.</li>
          <li><strong>Acesso ao catálogo enviado por email:</strong> quando abre o catálogo através da ligação incluída na confirmação do pedido, registamos a data e hora do acesso e o tipo de navegador, associados à referência do pedido.</li>
          <li><strong>Área reservada:</strong> nome, email profissional e entidade dos utilizadores com acesso, e data do último acesso.</li>
          <li><strong>Inscrições em formações:</strong> nome, email e função do formando, respostas ao diagnóstico de utilização de inteligência artificial, ferramentas de IA usadas e expectativas sobre a formação. Nas inscrições feitas na área de formação do site, também a indicação de inscrição particular ou de empresa, o nome da empresa (se for o caso), o NIF e o nome, o email e o telefone de quem faz a inscrição.</li>
          <li><strong>Pré-inscrições em cursos:</strong> nome, email e telefone, indicação de pré-inscrição particular ou de empresa e, neste caso, o nome da empresa, número previsto de formandos, modalidade preferida, preferências de datas e horários, respostas ao diagnóstico de nível e ferramentas de IA usadas.</li>
          <li><strong>Prevenção de abusos do formulário:</strong> o servidor guarda, durante uma hora, um código derivado do endereço IP, que não revela o endereço, para limitar envios repetidos.</li>
        </ul>

        <h2>3. Finalidades e fundamentos</h2>
        <div class="tabela-rolante">
          <table>
            <thead><tr><th scope="col">Finalidade</th><th scope="col">Fundamento (RGPD)</th></tr></thead>
            <tbody>
              <tr><td>Responder a pedidos de orçamento ou de informação e preparar propostas</td><td>Diligências pré-contratuais a pedido do titular, artigo 6.º, n.º 1, alínea b)</td></tr>
              <tr><td>Enviar a confirmação de receção do pedido</td><td>Diligências pré-contratuais a pedido do titular, artigo 6.º, n.º 1, alínea b)</td></tr>
              <tr><td>Acompanhamento comercial do pedido, incluindo o registo do acesso ao catálogo enviado por email</td><td>Interesse legítimo, artigo 6.º, n.º 1, alínea f)</td></tr>
              <tr><td>Organizar as formações, gerir as inscrições e adaptar os conteúdos ao nível dos formandos</td><td>Execução do contrato com a entidade cliente, artigo 6.º, n.º 1, alínea b), e interesse legítimo na adequação da formação, artigo 6.º, n.º 1, alínea f)</td></tr>
              <tr><td>Registar pré-inscrições, confirmar a receção e enviar o convite para a inscrição quando houver datas</td><td>Diligências pré-contratuais a pedido do titular, artigo 6.º, n.º 1, alínea b)</td></tr>
              <tr><td>Gestão da relação contratual com clientes</td><td>Execução de contrato, artigo 6.º, n.º 1, alínea b)</td></tr>
              <tr><td>Cumprimento de obrigações legais, como as fiscais e contabilísticas</td><td>Obrigação jurídica, artigo 6.º, n.º 1, alínea c)</td></tr>
              <tr><td>Funcionamento e segurança do site e prevenção de abusos do formulário</td><td>Interesse legítimo, artigo 6.º, n.º 1, alínea f)</td></tr>
            </tbody>
          </table>
        </div>

        <h2>4. Ditado por voz no formulário</h2>
        <p>O ditado é opcional e só é ativado quando carrega no botão "Ditar". Usa o serviço de reconhecimento de voz do seu navegador: o áudio é tratado pelo fornecedor do navegador (por exemplo, a Google no Chrome ou a Apple no Safari), de acordo com as condições desse fornecedor. Este site recebe apenas o texto que decidir enviar e não grava nem guarda áudio.</p>

        <h2>5. Área reservada e diagnóstico de utilização de IA</h2>
        <p>A área reservada destina-se apenas a pessoas autorizadas: utilizadores das entidades clientes e a Gadgetmetodo. Cada entidade cliente vê apenas as formações disponíveis e as inscrições dos seus próprios formandos; a Gadgetmetodo vê todas. As inscrições feitas na <a href="/formacoes/">área de formação</a> do site ficam visíveis apenas para a Gadgetmetodo até serem associadas à entidade cliente correspondente. O diagnóstico de utilização de IA é uma autoavaliação curta, usada apenas para ajustar os exemplos e os exercícios ao ponto de partida do grupo e, nos cursos de um percurso, para indicar o curso mais adequado; não se destina a avaliar o desempenho profissional do formando. Quem inscreve outra pessoa deve informá-la deste tratamento. Quando a Gadgetmetodo envia a um candidato o convite para uma formação, o nome e o email são usados apenas para esse envio.</p>

        <h2>6. Cookies e tecnologias semelhantes</h2>
        <p>Este site não utiliza cookies, ferramentas de análise de tráfego ou de publicidade, nem incorpora conteúdos de terceiros, como mapas ou vídeos. As fontes tipográficas são alojadas no próprio site. A área reservada e a página interna de registos da empresa, usadas apenas por pessoas autorizadas, utilizam um cookie de sessão estritamente necessário, que expira ao fechar o navegador.</p>

        <h2>7. Destinatários</h2>
        <p>Os dados podem ser tratados por prestadores de serviços de alojamento e de correio eletrónico, que atuam como subcontratantes. As sessões de formação online decorrem no Google Meet: ao participar, os dados de ligação e de imagem e som são tratados pela Google, de acordo com as condições desse serviço. Quando algum destes prestadores trate dados fora do Espaço Económico Europeu, a transferência baseia-se nas garantias previstas no RGPD, como decisões de adequação ou cláusulas contratuais-tipo. Os dados não são vendidos nem cedidos para fins de marketing de terceiros.</p>

        <h2>8. Conservação</h2>
        <p>Os pedidos de contacto que não deem origem a uma relação contratual são conservados até 12 meses após o último contacto. Os registos de pedidos e de acessos ao catálogo guardados no site são apagados automaticamente 12 meses após o pedido. Os dados das inscrições em formações, incluindo as respostas ao diagnóstico, são apagados automaticamente 2 anos após a data da formação. As pré-inscrições são apagadas automaticamente 12 meses após o registo, ou antes, a pedido do titular. As contas de acesso à área reservada são mantidas enquanto durar a relação com o cliente. Os dados de clientes são conservados durante a relação contratual e, depois dela, pelos prazos exigidos por lei.</p>

        <h2>9. Os seus direitos</h2>
        <p>Pode pedir o acesso, a retificação, o apagamento, a limitação do tratamento, a portabilidade dos seus dados e opor-se ao tratamento baseado em interesse legítimo, através de <a href="mailto:${EMAIL}">${EMAIL}</a>. Tem também o direito de apresentar reclamação à Comissão Nacional de Proteção de Dados (CNPD), em <a href="https://www.cnpd.pt" rel="noopener">www.cnpd.pt</a>.</p>

        <h2>10. Segurança</h2>
        <p>Aplicamos medidas técnicas e organizativas adequadas para proteger os dados contra acesso não autorizado, perda ou divulgação indevida.</p>

        <h2>11. Alterações</h2>
        <p class="mb-0">Esta política pode ser atualizada. A data da versão em vigor está indicada no topo desta página.</p>
      </div>
    </section>
  `,
});

/* 404 */
pagina({
  url: '/404.html',
  titulo: 'Página não encontrada',
  descricao: 'A página que procura não existe ou mudou de endereço.',
  corpo: `
    <section class="secao">
      <div class="contentor estreito">
        <span class="sobretitulo">Erro 404</span>
        <h1>Página não encontrada</h1>
        <p class="lead">A página que procura não existe ou mudou de endereço.</p>
        <div class="acoes">
          <a class="botao" href="/">Ir para a página inicial ${seta}</a>
          <a class="botao botao-secundario" href="/servicos/">Ver serviços</a>
        </div>
      </div>
    </section>
  `,
});

/* Mapa do site */
const sitemap = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${paginas.map(p => `  <url>
    <loc>${URL_BASE}${p.url}</loc>
    <lastmod>${HOJE}</lastmod>
    <priority>${p.prioridade}</priority>
  </url>`).join('\n')}
  <url>
    <loc>${URL_BASE}/catalogo-servicos-2026.pdf</loc>
    <lastmod>${HOJE}</lastmod>
    <priority>0.6</priority>
  </url>
</urlset>
`;
fs.writeFileSync(path.join(OUT, 'sitemap.xml'), sitemap);
console.log(paginas.length, 'páginas');
