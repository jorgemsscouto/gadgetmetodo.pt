// Gerador das páginas HTML do site gadgetmetodo.pt.
// Utilização (a partir desta pasta): npm install && node gerar-site.mjs ..
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
  ['/abordagem/', 'Abordagem'],
  ['/experiencia/', 'Experiência'],
  ['/sobre/', 'Sobre'],
  ['/perguntas-frequentes/', 'Perguntas frequentes'],
];

const SERVICOS = [
  { url: '/servicos/organizacao-e-metodos/', nome: 'Organização e Métodos', icone: 'workflow', curto: 'Diagnóstico organizacional, mapeamento e redesenho de processos, normas e procedimentos internos, auditorias e planos de melhoria.' },
  { url: '/servicos/inteligencia-artificial/', nome: 'Inteligência artificial e transformação digital', icone: 'sparkles', curto: 'Estratégia de IA, casos de uso com retorno, arquitetura de sistemas e integrações, automação e painéis de indicadores.' },
  { url: '/servicos/protecao-de-dados-e-conformidade/', nome: 'Proteção de dados e conformidade', icone: 'shield-check', curto: 'Funções de Encarregado de Proteção de Dados, RGPD, NIS2 e governação da IA, com equilíbrio entre conformidade e operação.' },
  { url: '/servicos/estrategia-e-gestao/', nome: 'Estratégia e gestão', icone: 'compass', curto: 'Alinhamento estratégico, objetivos e indicadores, modelos de incentivos e apoio à administração em inovação e tecnologia.' },
  { url: '/servicos/formacao-ai-essentials/', nome: 'Formação AI Essentials', icone: 'graduation-cap', curto: 'Programa em cinco módulos para administrações e equipas: fundamentos, casos de uso, governação, privacidade e segurança.' },
];

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
  knowsAbout: ['Organização e métodos', 'Diagnóstico organizacional', 'Redesenho de processos', 'Normas e procedimentos internos', 'Auditorias internas de processos e sistemas de gestão', 'Estratégia de inteligência artificial', 'Arquitetura de sistemas e integrações', 'Automação de marketing', 'Análise de dados e painéis de indicadores', 'RGPD', 'Encarregado de Proteção de Dados', 'Diretiva NIS2', 'Governação da inteligência artificial', 'Planeamento estratégico', 'Indicadores-chave de desempenho', 'Formação em inteligência artificial'],
  hasOfferCatalog: {
    '@type': 'OfferCatalog',
    name: 'Serviços da Gadgetmetodo, Lda.',
    itemListElement: SERVICOS.map(s => ({ '@type': 'Offer', itemOffered: { '@type': s.url.includes('formacao') ? 'Course' : 'Service', '@id': URL_BASE + s.url + '#' + (s.url.includes('formacao') ? 'curso' : 'servico'), name: s.nome } })),
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
          <li><a class="nav-clientes" href="/clientes/" title="Área de clientes">${icone('circle-user-round').replace('<svg ', '<svg width="18" height="18" ')}<span class="nav-clientes-texto">Área de clientes</span></a></li>
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
          <p>Consultoria em organização e métodos, inteligência artificial aplicada, proteção de dados e apoio à gestão. Porto, Portugal.</p>
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
            <li><a href="/abordagem/">Abordagem</a></li>
            <li><a href="/experiencia/">Experiência</a></li>
            <li><a href="/sobre/">Sobre nós</a></li>
            <li><a href="/catalogo/">Catálogo de serviços</a></li>
            <li><a href="/perguntas-frequentes/">Perguntas frequentes</a></li>
            <li><a href="/contactos/">Contactos</a></li>
            <li><a href="/clientes/">Área de clientes</a></li>
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
function pagina({ url, titulo, descricao, h1Schema, tipo = 'WebPage', trilho, corpo, schemaExtra = [], prioridade = '0.7', llms, indexar = true, scripts = [] }) {
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
  const grafo = [ORG, WEBSITE, webpage];
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
  const html = `<!doctype html>
<html lang="pt-PT">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${esc(tituloCompleto)}</title>
  <meta name="description" content="${esc(descricao)}">
  <meta name="robots" content="${url === '/404.html' || !indexar ? 'noindex, follow' : 'index, follow, max-snippet:-1, max-image-preview:large'}">
  ${url === '/404.html' ? '' : `<link rel="canonical" href="${canonical}">`}
  <meta name="theme-color" content="#0A0F26">
  <meta name="author" content="Gadgetmetodo, Lda.">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="pt_PT">
  <meta property="og:site_name" content="Gadgetmetodo, Lda.">
  <meta property="og:title" content="${esc(tituloCompleto)}">
  <meta property="og:description" content="${esc(descricao)}">
  <meta property="og:url" content="${canonical}">
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
  <link rel="stylesheet" href="/assets/css/site.css">
  <script>document.documentElement.classList.add("js")</script>
  <script type="application/ld+json">
${jsonld}
  </script>
</head>
<body>
  <a class="saltar" href="#conteudo">Saltar para o conteúdo</a>
  ${cabecalho(url)}
  <main id="conteudo">
    ${corpo.trim()}
  </main>
  ${rodape()}
  <script src="/assets/js/site.js" defer></script>
${scripts.map(s => `  <script src="${s}" defer></script>\n`).join('')}</body>
</html>
`;
  const destino = url === '/404.html' ? path.join(OUT, '404.html') : path.join(OUT, url, 'index.html');
  fs.mkdirSync(path.dirname(destino), { recursive: true });
  fs.writeFileSync(destino, html);
  if (url !== '/404.html' && indexar) paginas.push({ url, titulo, descricao, prioridade, llms });
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
  ['Em que consiste a formação AI Essentials?', 'É um <a href="/servicos/formacao-ai-essentials/">programa em cinco módulos</a>: fundamentos de IA para a administração; casos de uso e integração de IA em sistemas; governação e ética da IA; RGPD, privacidade e IA; NIS2 e segurança da informação. Os conteúdos são adaptados ao setor e ao público, e a formação é ministrada por formador com Certificado de Competências Pedagógicas (CCP).'],
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
  llms: 'Página inicial: quem somos, serviços, abordagem e contactos.',
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
            <span class="mais">Ver a abordagem ${seta}</span>
          </a>
        </div>
        <p class="espaco-topo mb-0"><a class="botao botao-secundario" href="/catalogo/">${icone('file-text')} Descarregar o catálogo de serviços</a></p>
      </div>
    </section>

    <section class="secao" aria-labelledby="como-trabalhamos">
      <div class="contentor">
        <div class="cabeca-secao">
          <span class="sobretitulo">Abordagem</span>
          <h2 id="como-trabalhamos">Como trabalhamos</h2>
          <p>Cada projeto segue um percurso claro, com entregáveis definidos e decisões apoiadas em dados.</p>
        </div>
        <ol class="etapas">
          <li><h3>Diagnóstico</h3><p>Levantamento de processos, sistemas, equipas, dados e riscos. Identificação das prioridades com maior retorno.</p></li>
          <li><h3>Desenho</h3><p>Modelo organizacional, processos, arquitetura de sistemas, regras de governação e plano de ação com calendário.</p></li>
          <li><h3>Implementação</h3><p>Execução com as equipas: ferramentas, procedimentos, formação e gestão da mudança.</p></li>
          <li><h3>Acompanhamento</h3><p>Medição de resultados com indicadores, ajustes e apoio regular à administração.</p></li>
        </ol>
        <p class="espaco-topo mb-0"><a class="botao botao-secundario" href="/abordagem/">Conhecer a abordagem ${seta}</a></p>
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
  descricao: 'Organização e métodos, inteligência artificial e transformação digital, proteção de dados e conformidade, estratégia e gestão, e formação AI Essentials.',
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
          'A Gadgetmetodo, Lda. presta serviços em cinco áreas: Organização e Métodos (O&amp;M); inteligência artificial (IA) e transformação digital; proteção de dados e conformidade (RGPD, NIS2 e governação da IA); estratégia e gestão; e formação AI Essentials. As áreas podem ser contratadas isoladamente ou combinadas num único projeto, com uma equipa e um plano de ação comuns.',
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
function paginaServico({ s, titulo, descricao, h1, lead, pergunta, resumoTxt, inclui, quando, entregaveis, faq, extra = '', llms, schemaServico }) {
  const trilho = [['/', 'Início'], ['/servicos/', 'Serviços'], [s.url, s.nome]];
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
  s: SERVICOS[4],
  pergunta: 'O que é a formação AI Essentials?',
  titulo: 'Formação AI Essentials em IA',
  descricao: 'Formação AI Essentials: cinco módulos sobre IA para administrações e equipas, incluindo casos de uso, governação, RGPD e NIS2. Formador com CCP.',
  h1: 'Formação AI Essentials: inteligência artificial para administrações e equipas',
  lead: 'Cinco módulos para decidir, utilizar e governar a inteligência artificial com conhecimento de causa.',
  llms: 'Programa de formação AI Essentials em cinco módulos.',
  resumoTxt: [
    'AI Essentials é o programa de formação da Gadgetmetodo, Lda. sobre inteligência artificial (IA) aplicada à gestão. Organiza-se em cinco módulos, dos fundamentos à segurança da informação, e é adaptado ao setor e ao nível de cada grupo, da administração às equipas operacionais.',
    'É ministrado por formador com Certificado de Competências Pedagógicas (CCP).',
  ],
  schemaServico: {
    '@type': 'Course',
    '@id': URL_BASE + SERVICOS[4].url + '#curso',
    name: 'AI Essentials',
    description: 'Programa de formação em inteligência artificial aplicada à gestão, em cinco módulos, para administrações e equipas.',
    provider: { '@id': ORG_ID },
    inLanguage: 'pt-PT',
    url: URL_BASE + SERVICOS[4].url,
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
  extra: `<section class="secao secao-nevoa" aria-labelledby="modulos">
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

const FASES_ABORDAGEM = [
  ['Diagnóstico', 'Levantamento da situação atual: processos, sistemas, equipas, dados e riscos. Identificação das prioridades e oportunidades com maior retorno, com base em informação da própria empresa e, quando útil, em dados públicos do setor.'],
  ['Desenho', 'Definição da solução: modelo organizacional, processos, arquitetura de sistemas, regras de governação e plano de ação com responsáveis, calendário e indicadores.'],
  ['Implementação', 'Execução com as equipas: parametrização de ferramentas, procedimentos, formação e gestão da mudança, com especial cuidado na forma como a mudança é introduzida.'],
  ['Acompanhamento', 'Medição de resultados com indicadores, ajustes ao plano e apoio regular à administração para consolidar a mudança.'],
];

/* Abordagem */
pagina({
  url: '/abordagem/',
  titulo: 'Abordagem e metodologia',
  descricao: 'Como a Gadgetmetodo trabalha: diagnóstico, desenho, implementação e acompanhamento, com propostas à medida e modelos de colaboração por projeto ou continuados.',
  llms: 'Metodologia em quatro fases, princípios e modelos de colaboração.',
  trilho: [['/', 'Início'], ['/abordagem/', 'Abordagem']],
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
    ${topoPagina({ trilho: [['/', 'Início'], ['/abordagem/', 'Abordagem']], h1: 'A nossa abordagem: do diagnóstico aos resultados', lead: 'Um método claro, aplicado com proximidade às pessoas e à realidade de cada empresa.' })}

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
          <div class="cartao"><span class="icone">${icone('graduation-cap')}</span><h3>Formação</h3><p><a href="/servicos/formacao-ai-essentials/">Programa AI Essentials</a>, isolado ou integrado num projeto, adaptado ao setor e ao público.</p></div>
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
              `A Gadgetmetodo, Lda. é uma sociedade por quotas portuguesa, com sede na ${MORADA.rua}, no Porto. Dedica-se à consultoria em Organização e Métodos (O&amp;M), à inteligência artificial aplicada à gestão, à proteção de dados e conformidade e ao apoio estratégico a administrações, e desenvolve o programa de formação AI Essentials.`,
            ], 'Quem é a Gadgetmetodo?')}
            <div class="conteudo espaco-topo">
              <h2>Quem somos</h2>
              <p>A Gadgetmetodo parte de uma convicção: a eficiência de uma empresa depende, antes de mais, da forma como está organizada. A tecnologia, e hoje em particular a inteligência artificial, acelera o que já funciona bem, mas raramente resolve o que está mal organizado.</p>
              <p>Por isso, cada projeto junta três dimensões: <a href="/servicos/organizacao-e-metodos/">o método</a>, para simplificar e organizar; <a href="/servicos/inteligencia-artificial/">a tecnologia</a>, para automatizar e medir; e <a href="/servicos/protecao-de-dados-e-conformidade/">a conformidade</a>, para proteger clientes, parceiros e titulares dos dados. O <a href="/servicos/estrategia-e-gestao/">apoio à estratégia</a> e a <a href="/servicos/formacao-ai-essentials/">formação AI Essentials</a> completam a oferta.</p>
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
const TODAS_FAQ = [...FAQ_EMPRESA, ...FAQ_SERVICOS, ...FAQ_FUNCIONAMENTO];
const faqExtra = [];
faqExtra.__faq = faqSchema(TODAS_FAQ);
pagina({
  url: '/perguntas-frequentes/',
  titulo: 'Perguntas frequentes',
  descricao: 'Respostas sobre a Gadgetmetodo, Lda.: organização e métodos, inteligência artificial, DPO, RGPD, NIS2, formação, propostas e funcionamento dos projetos.',
  tipo: 'FAQPage',
  prioridade: '0.8',
  llms: 'Perguntas e respostas sobre a empresa, os serviços e o funcionamento dos projetos.',
  trilho: [['/', 'Início'], ['/perguntas-frequentes/', 'Perguntas frequentes']],
  schemaExtra: faqExtra,
  corpo: `
    ${topoPagina({ trilho: [['/', 'Início'], ['/perguntas-frequentes/', 'Perguntas frequentes']], h1: 'Perguntas frequentes', lead: 'Respostas diretas sobre a empresa, os serviços e a forma como trabalhamos.' })}

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
  ...SERVICOS.map(s => [s.url.split('/')[2], s.nome]),
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

              <div class="campos-2">
                <div class="campo">
                  <label for="f-nome">Nome *</label>
                  <input id="f-nome" name="nome" type="text" autocomplete="name" required maxlength="100">
                  <p class="erro-campo" id="e-nome" hidden></p>
                </div>
                <div class="campo">
                  <label for="f-empresa">Empresa *</label>
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
  descricao: 'Descarregue o Catálogo de Serviços 2026 da Gadgetmetodo, Lda.: áreas de serviço, abordagem, exemplos de intervenção e contactos, em 16 páginas (PDF).',
  prioridade: '0.7',
  llms: 'Página de download do Catálogo de Serviços 2026 em PDF.',
  trilho: [['/', 'Início'], ['/catalogo/', 'Catálogo de serviços']],
  schemaExtra: [{
    '@type': 'DigitalDocument',
    '@id': URL_BASE + '/catalogo/#catalogo',
    name: 'Catálogo de Serviços 2026',
    description: 'Catálogo da Gadgetmetodo, Lda. com as cinco áreas de serviço, a abordagem em quatro fases, os modelos de colaboração, exemplos de intervenção e contactos.',
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
              'O Catálogo de Serviços 2026 da Gadgetmetodo, Lda. apresenta, em 16 páginas, as cinco áreas de serviço (Organização e Métodos, inteligência artificial e transformação digital, proteção de dados e conformidade, estratégia e gestão, e formação AI Essentials), as questões a que cada uma responde, o que inclui e os entregáveis habituais.',
              'Inclui também a abordagem em quatro fases, os modelos de colaboração, exemplos de intervenção por setor e os contactos.',
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
              'Abordagem em quatro fases e modelos de colaboração',
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
          <li><strong>Dados do formulário de contacto:</strong> nome, empresa, email, telemóvel, tipo de pedido, serviços de interesse e mensagem.</li>
          <li><strong>Dados que nos envia</strong> por email ou telefone: nome, contactos, empresa e o conteúdo da mensagem.</li>
          <li><strong>Dados técnicos de acesso</strong> registados pelo serviço de alojamento do site, como o endereço IP, a data e hora do pedido, a página pedida e o tipo de navegador, para garantir o funcionamento e a segurança do site.</li>
          <li><strong>Acesso ao catálogo enviado por email:</strong> quando abre o catálogo através da ligação incluída na confirmação do pedido, registamos a data e hora do acesso e o tipo de navegador, associados à referência do pedido.</li>
          <li><strong>Área de clientes:</strong> nome, email profissional e entidade dos utilizadores com acesso, e data do último acesso.</li>
          <li><strong>Inscrições em formações:</strong> nome, email profissional e função do formando, respostas ao diagnóstico de utilização de inteligência artificial, ferramentas usadas e expectativas sobre a formação, indicados pela entidade cliente que faz a inscrição.</li>
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
              <tr><td>Gestão da relação contratual com clientes</td><td>Execução de contrato, artigo 6.º, n.º 1, alínea b)</td></tr>
              <tr><td>Cumprimento de obrigações legais, como as fiscais e contabilísticas</td><td>Obrigação jurídica, artigo 6.º, n.º 1, alínea c)</td></tr>
              <tr><td>Funcionamento e segurança do site e prevenção de abusos do formulário</td><td>Interesse legítimo, artigo 6.º, n.º 1, alínea f)</td></tr>
            </tbody>
          </table>
        </div>

        <h2>4. Ditado por voz no formulário</h2>
        <p>O ditado é opcional e só é ativado quando carrega no botão "Ditar". Usa o serviço de reconhecimento de voz do seu navegador: o áudio é tratado pelo fornecedor do navegador (por exemplo, a Google no Chrome ou a Apple no Safari), de acordo com as condições desse fornecedor. Este site recebe apenas o texto que decidir enviar e não grava nem guarda áudio.</p>

        <h2>5. Área de clientes e diagnóstico de utilização de IA</h2>
        <p>A área de clientes é reservada a pessoas autorizadas. Cada entidade cliente vê apenas as formações disponíveis e as inscrições dos seus próprios formandos; a Gadgetmetodo vê todas. O diagnóstico de utilização de IA é uma autoavaliação curta, usada apenas para ajustar os exemplos e os exercícios ao ponto de partida do grupo; não se destina a avaliar o desempenho profissional do formando. A entidade cliente que faz a inscrição deve informar o formando deste tratamento.</p>

        <h2>6. Cookies e tecnologias semelhantes</h2>
        <p>Este site não utiliza cookies, ferramentas de análise de tráfego ou de publicidade, nem incorpora conteúdos de terceiros, como mapas ou vídeos. As fontes tipográficas são alojadas no próprio site. A área de clientes e a área reservada da empresa, usadas apenas por pessoas autorizadas, utilizam um cookie de sessão estritamente necessário, que expira ao fechar o navegador.</p>

        <h2>7. Destinatários</h2>
        <p>Os dados podem ser tratados por prestadores de serviços de alojamento e de correio eletrónico, que atuam como subcontratantes. Quando algum destes prestadores trate dados fora do Espaço Económico Europeu, a transferência baseia-se nas garantias previstas no RGPD, como decisões de adequação ou cláusulas contratuais-tipo. Os dados não são vendidos nem cedidos para fins de marketing de terceiros.</p>

        <h2>8. Conservação</h2>
        <p>Os pedidos de contacto que não deem origem a uma relação contratual são conservados até 12 meses após o último contacto. Os registos de pedidos e de acessos ao catálogo guardados no site são apagados automaticamente 12 meses após o pedido. Os dados das inscrições em formações, incluindo as respostas ao diagnóstico, são apagados automaticamente 2 anos após a data da formação. As contas de acesso à área de clientes são mantidas enquanto durar a relação com o cliente. Os dados de clientes são conservados durante a relação contratual e, depois dela, pelos prazos exigidos por lei.</p>

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
