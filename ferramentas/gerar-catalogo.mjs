// Gera documentos/catalogo/catalogo.html (A4 horizontal) para conversão em PDF.
// Utilização (a partir desta pasta): node gerar-catalogo.mjs ../documentos/catalogo
import fs from 'fs';
import path from 'path';

const OUT = process.argv[2];
// Cursos do percurso de formação: o mesmo ficheiro de dados que alimenta o site e a área reservada
const CURSOS = JSON.parse(fs.readFileSync(new URL('../clientes/inc/catalogo/ia-generativa.json', import.meta.url), 'utf8'));
fs.mkdirSync(OUT, { recursive: true });
const A = '../../assets';

function icone(nome) {
  let svg = fs.readFileSync('node_modules/lucide-static/icons/' + nome + '.svg', 'utf8');
  return svg.replace(/<!--.*?-->\s*/s, '').replace(/\s*\n\s*/g, ' ').replace(/class="[^"]*"/, 'aria-hidden="true"').replace(/ width="24" height="24"/, '').trim();
}
const check = lista => `<ul class="check">${lista.map(i => `<li>${i}</li>`).join('')}</ul>`;

const TOTAL = 16;
let n = 0;
function pagina({ classe = '', seccao = '', corpo, sem_rodape = false }) {
  n += 1;
  return `<section class="pagina ${classe}">
  ${seccao ? `<div class="tracker"><span>${seccao}</span></div>` : ''}
  ${corpo}
  ${sem_rodape ? '' : `<footer class="rodape-pag"><span>Gadgetmetodo, Lda.&nbsp;&nbsp;|&nbsp;&nbsp;Catálogo de Serviços 2026</span><span>${String(n).padStart(2, '0')}</span></footer>`}
</section>`;
}

const SERVICOS = [
  {
    num: '01', nome: 'Organização e Métodos', icone: 'workflow',
    titulo: 'Uma organização mais simples de gerir, com processos documentados e responsabilidades claras',
    resumo: 'A consultoria em Organização e Métodos (O&amp;M) analisa como a empresa está organizada e como o trabalho é feito, para eliminar redundâncias, clarificar responsabilidades e reduzir custos de operação. Cada intervenção começa com um diagnóstico e termina com processos, procedimentos e indicadores que a equipa aplica no dia a dia.',
    questoes: ['A estrutura acompanha o crescimento da empresa?', 'Onde se perde tempo, qualidade ou dinheiro nos processos atuais?', 'Quem decide, quem executa e quem controla cada atividade?', 'Que procedimentos são necessários para uma certificação ou auditoria?'],
    inclui: ['Diagnóstico organizacional', 'Arquitetura organizacional: mapeamento, desenho e redesenho', 'Mapeamento e otimização de processos, com base digital', 'Normas e procedimentos internos', 'Funções, responsabilidades e gestão de áreas', 'Planos de melhoria e respetivo acompanhamento', 'Auditorias internas de processos e sistemas de gestão', 'Apoio em licenciamentos, acreditações e certificações'],
    entregaveis: ['Relatório de diagnóstico com prioridades', 'Organograma e matriz de responsabilidades', 'Mapas de processos atuais e propostos', 'Manual de normas e procedimentos', 'Plano de melhoria com indicadores'],
  },
  {
    num: '02', nome: 'Inteligência artificial e transformação digital', icone: 'sparkles',
    titulo: 'A inteligência artificial gera valor quando começa pelos casos de uso certos e se integra nos processos',
    resumo: 'Ajudamos a definir e executar a estratégia de inteligência artificial (IA): identificamos os casos de uso com maior retorno, desenhamos a arquitetura de sistemas e integrações, implementamos soluções digitais e preparamos as equipas para as utilizar, para que a IA melhore processos reais e não fique isolada em projetos-piloto.',
    questoes: ['Onde pode a IA gerar retorno mensurável no nosso negócio?', 'Por onde começar, com que investimento e em que sequência?', 'Os nossos sistemas e dados estão preparados?', 'Como integrar a IA nos produtos e serviços que já vendemos?'],
    inclui: ['Estratégia de IA alinhada com o negócio', 'Identificação e priorização de casos de uso', 'Roteiro de adoção, com fases e investimento', 'Diagnóstico de tecnologia, sistemas e integrações', 'Integração entre gestão empresarial (ERP), clientes (CRM) e outras aplicações', 'Fornecimento e implementação de soluções digitais com IA', 'Automação de marketing e personalização com IA', 'Painéis de indicadores e análise de dados'],
    entregaveis: ['Mapa de casos de uso priorizados', 'Roteiro de adoção e arquitetura de sistemas', 'Soluções implementadas e parametrizadas', 'Painéis de indicadores', 'Equipas formadas'],
    figura: 'matriz',
  },
  {
    num: '03', nome: 'Proteção de dados e conformidade', icone: 'shield-check',
    titulo: 'Conformidade proporcional ao risco protege clientes e titulares sem travar a operação',
    resumo: 'Asseguramos funções de Encarregado de Proteção de Dados (DPO, Data Protection Officer) e apoiamos a aplicação do RGPD, da Diretiva NIS2 sobre cibersegurança e da governação da IA, com clareza estratégica, realismo operacional e governação com bom senso.',
    questoes: ['Cumprimos o RGPD de forma demonstrável?', 'A NIS2 aplica-se à empresa, direta ou indiretamente?', 'Que regras devem orientar o uso de IA com dados pessoais?', 'Os contratos com clientes e subcontratantes protegem-nos?'],
    inclui: ['Funções de DPO em regime externo', 'Registo das atividades de tratamento', 'Contratos com clientes e subcontratantes', 'Transferências internacionais de dados', 'Impacto da IA no RGPD', 'Requisitos da NIS2 e segurança da informação', 'Governação e ética da IA', 'Formação em privacidade e segurança'],
    entregaveis: ['Registo das atividades de tratamento', 'Cláusulas e contratos revistos', 'Análise de transferências internacionais', 'Política de utilização de IA', 'Plano de ação de segurança da informação'],
    figura: 'referencias',
  },
  {
    num: '04', nome: 'Estratégia e gestão', icone: 'compass',
    titulo: 'A estratégia só produz resultados quando chega aos objetivos e aos incentivos de cada equipa',
    resumo: 'Apoiamos administrações na definição e execução da estratégia: alinhamento do negócio, missão, visão e valores, planos estratégicos, objetivos e indicadores-chave de desempenho (KPI, Key Performance Indicators) e modelos de incentivos ligados a resultados. Atuamos também como conselheiros da administração para inovação e tecnologia.',
    questoes: ['O que nos diferencia e onde devemos competir?', 'Porque está a margem a cair e onde agir primeiro?', 'Os objetivos de cada equipa estão ligados à estratégia?', 'Os incentivos premeiam os resultados certos?'],
    inclui: ['Alinhamento estratégico: negócio, missão, visão e valores', 'Planos estratégicos e de recuperação de margem', 'Análise de concorrência e de desempenho', 'Objetivos e KPI por área, canal ou equipa', 'Modelos de incentivos indexados a resultados', 'Desenho de equipas e operação omnicanal', 'Criação de novas unidades de negócio', 'Conselho à administração (board advisory)'],
    entregaveis: ['Plano estratégico com iniciativas e responsáveis', 'Análise de vendas e margens por canal', 'Sistema de objetivos e indicadores', 'Regulamento de incentivos', 'Relatórios para a administração'],
  },
  {
    num: '05', nome: 'Formação', icone: 'graduation-cap', formacao: true,
    titulo: 'Formação em IA para quem usa, aplica e lidera: cursos abertos e programas à medida',
    resumo: 'A formação da Gadgetmetodo tem dois formatos: o percurso IA Generativa no Trabalho, com quatro cursos abertos de seis horas, da iniciação à liderança da implementação, e programas à medida, como o AI Essentials, para administrações e equipas. Os cursos podem também ser organizados só para uma empresa.',
    questoes: ['Por que curso deve começar cada pessoa, e até onde pode ir?', 'Como passar do uso individual da IA para os processos da equipa?', 'Que regras e cuidados são necessários com dados e resultados?'],
    funciona: ['Inscrição online em cada curso; sem datas, pré-inscrição com convite quando forem marcadas', 'Diagnóstico de nível na inscrição, que indica o curso adequado a cada formando', 'Página e ficha em PDF de cada curso, com programa, casos práticos e avaliação', 'Evidência documentada da formação realizada'],
    entregaveis: ['Percurso de quatro cursos com diagnóstico de nível', 'Ficha de cada curso com programa e avaliação', 'Programas à medida para administrações e equipas', 'Evidência documentada da formação realizada'],
  },
];

const fig_matriz = `<figure class="figura">
  <figcaption><strong>Figura 2.</strong> Priorização de casos de uso de IA</figcaption>
  <div class="matriz">
    <div class="eixo-y">Impacto no negócio</div>
    <div class="quadrantes">
      <div class="q q-destaque"><strong>Ganhos rápidos</strong><span>Alto impacto, baixo esforço. Avançar primeiro.</span></div>
      <div class="q"><strong>Apostas estratégicas</strong><span>Alto impacto, maior esforço. Planear por fases.</span></div>
      <div class="q"><strong>Melhorias pontuais</strong><span>Baixo impacto, baixo esforço. Fazer quando oportuno.</span></div>
      <div class="q q-fraco"><strong>Adiar</strong><span>Baixo impacto, maior esforço. Reavaliar mais tarde.</span></div>
    </div>
    <div class="eixo-x">Esforço de implementação</div>
  </div>
  <p class="fonte">Esquema ilustrativo do método de priorização usado no diagnóstico.</p>
</figure>`;

const fig_referencias = `<figure class="figura">
  <figcaption><strong>Figura 3.</strong> Três referências que pedem a mesma disciplina</figcaption>
  <div class="referencias">
    <div><strong>RGPD</strong><span>Regulamento (UE) 2016/679</span><em>Dados pessoais</em></div>
    <div><strong>NIS2</strong><span>Diretiva (UE) 2022/2555</span><em>Cibersegurança</em></div>
    <div><strong>Governação da IA</strong><span>Regras internas</span><em>Uso de IA</em></div>
  </div>
  <div class="referencias-base">Inventário de dados e sistemas · Responsáveis definidos · Controlos e evidências</div>
  <p class="fonte">Esquema ilustrativo.</p>
</figure>`;

// Formação: percurso de cursos abertos (do ficheiro de dados) e programa AI Essentials à medida
function paginaFormacao(s) {
  const cursos = CURSOS.cursos.map(c => {
    const [, n, titulo] = /^Curso (\d+): (.+)$/.exec(c.nome) || [null, '', c.nome];
    const lab = (/Laboratório de Casos Práticos: (\d+)/.exec(c.estrutura) || [])[1];
    return `<tr><th><span class="num">${n}</span>${titulo}</th><td>${c.objetivo}</td><td class="lab">${lab} min</td></tr>`;
  }).join('');
  return pagina({
    classe: 'denso formacao',
    seccao: `Serviços&nbsp;&nbsp;·&nbsp;&nbsp;${s.num}`,
    corpo: `
  <h1 class="titulo-acao">${s.titulo}</h1>
  <div class="grelha-servico">
    <div class="col-esq">
      <div class="servico-cab"><span class="icone">${icone(s.icone)}</span><span class="servico-nome">${s.nome}</span></div>
      <p class="resumo">${s.resumo}</p>
      <h3 class="rotulo">Questões a que ajudamos a responder</h3>
      <ol class="questoes">${s.questoes.map(q => `<li>${q}</li>`).join('')}</ol>
      <h3 class="rotulo">Como funciona</h3>${check(s.funciona)}
    </div>
    <div class="col-dir">
      <h3 class="rotulo">Percurso ${CURSOS.comum.percurso.split(':')[0]}: 4 cursos de 6 sessões de 60 minutos</h3>
      <table class="tabela tabela-cursos">
        <thead><tr><th>Curso</th><th>Objetivo</th><th>Prática por sessão</th></tr></thead>
        <tbody>${cursos}</tbody>
      </table>
      <h3 class="rotulo mt">AI Essentials: programa à medida em cinco módulos</h3>
      <p class="pequeno">Fundamentos de IA para a administração · Casos de uso e integração em sistemas · Governação e ética da IA · RGPD, privacidade e IA · NIS2 e segurança da informação. Ministrado por formador com Certificado de Competências Pedagógicas (CCP).</p>
    </div>
  </div>
  <p class="nota-pag">Ferramenta de referência nos cursos abertos: Claude (Anthropic); os conceitos são transferíveis para outros assistentes de IA generativa. Programa detalhado, datas e inscrição em www.gadgetmetodo.pt/formacoes.</p>`,
  });
}

function paginaServico(s) {
  if (s.formacao) return paginaFormacao(s);
  const direita = s.modulos
    ? `<h3 class="rotulo">Os cinco módulos</h3>
       <table class="tabela">
         <tbody>${s.modulos.map(([t, d], i) => `<tr><th><span class="num">${i + 1}</span>${t}</th><td>${d}</td></tr>`).join('')}</tbody>
       </table>
       <div class="duas mt">
         <div><h3 class="rotulo">Entregáveis</h3>${check(s.entregaveis)}</div>
         <div><h3 class="rotulo">Formato</h3><p class="pequeno">Duração, modalidade (presencial, remota ou mista) e exemplos práticos ajustados a cada grupo e definidos na proposta.</p></div>
       </div>`
    : `<h3 class="rotulo">O que inclui</h3>
       <div class="modulos">${s.inclui.map(i => `<div>${i}</div>`).join('')}</div>
       ${s.figura === 'matriz' ? fig_matriz : s.figura === 'referencias' ? fig_referencias : `<h3 class="rotulo mt">Entregáveis habituais</h3>${check(s.entregaveis)}`}`;
  return pagina({
    classe: s.figura || s.modulos ? 'denso' : '',
    seccao: `Serviços&nbsp;&nbsp;·&nbsp;&nbsp;${s.num}`,
    corpo: `
  <h1 class="titulo-acao">${s.titulo}</h1>
  <div class="grelha-servico">
    <div class="col-esq">
      <div class="servico-cab"><span class="icone">${icone(s.icone)}</span><span class="servico-nome">${s.nome}</span></div>
      <p class="resumo">${s.resumo}</p>
      <h3 class="rotulo">Questões a que ajudamos a responder</h3>
      <ol class="questoes">${s.questoes.map(q => `<li>${q}</li>`).join('')}</ol>
      ${s.figura ? `<h3 class="rotulo">Entregáveis habituais</h3>${check(s.entregaveis)}` : ''}
    </div>
    <div class="col-dir">${direita}</div>
  </div>`,
  });
}

const paginas = [];

/* 1. Capa */
paginas.push(pagina({
  classe: 'capa', sem_rodape: true,
  corpo: `
  <img class="capa-simbolo" src="${A}/marca/simbolo-negativo.svg" alt="">
  <img class="capa-logo" src="${A}/marca/logotipo-negativo.svg" alt="Gadgetmetodo, Lda.">
  <div class="capa-texto">
    <p class="assinatura">Simplificar&nbsp;&nbsp;·&nbsp;&nbsp;Organizar&nbsp;&nbsp;·&nbsp;&nbsp;Fazer acontecer</p>
    <h1>Catálogo de Serviços</h1>
    <p class="capa-sub">Organização, métodos e inteligência artificial para empresas</p>
  </div>
  <p class="capa-data">Edição 2026&nbsp;&nbsp;|&nbsp;&nbsp;Porto, Portugal</p>`,
}));

/* 2. Índice */
const INDICE = [
  ['Síntese', 3], ['O nosso ponto de vista', 4], ['Mapa de serviços', 5],
  ['Organização e Métodos', 6], ['Inteligência artificial e transformação digital', 7], ['Proteção de dados e conformidade', 8], ['Estratégia e gestão', 9], ['Formação', 10],
  ['A Metodologia', 11], ['Modelos de colaboração e princípios', 12], ['Experiência', 13], ['Equipa', 14], ['Como iniciar', 15],
];
paginas.push(pagina({
  seccao: 'Índice',
  corpo: `
  <div class="indice-grelha">
    <div>
      <h1 class="titulo-acao">Índice</h1>
      <ol class="indice">${INDICE.map(([t, p], i) => `<li><span class="i-num">${String(i + 1).padStart(2, '0')}</span><span class="i-tit">${t}</span><span class="i-pag">${String(p).padStart(2, '0')}</span></li>`).join('')}</ol>
    </div>
    <aside class="nota-abertura">
      <h3 class="rotulo">Sobre este catálogo</h3>
      <p>Este documento apresenta os serviços da Gadgetmetodo, Lda., a forma como trabalhamos e exemplos do tipo de intervenção que desenvolvemos.</p>
      <p>Cada página de serviço responde a três perguntas: que problema resolvemos, que questões ajudamos a responder e o que recebe no fim.</p>
      <p>As condições de cada projeto (âmbito, entregáveis, calendário e investimento) são sempre definidas numa proposta à medida.</p>
    </aside>
  </div>`,
}));

/* 3. Síntese */
paginas.push(pagina({
  seccao: 'Síntese',
  corpo: `
  <h1 class="titulo-acao">Ajudamos empresas a simplificar processos, adotar a inteligência artificial com critério e proteger os dados, do diagnóstico à implementação</h1>
  <div class="tres">
    <div class="bloco"><span class="icone">${icone('building-2')}</span><h3>Quem somos</h3><p>Consultora com sede no Porto, especializada em Organização e Métodos (O&amp;M), inteligência artificial (IA) aplicada à gestão, proteção de dados e apoio estratégico a administrações.</p></div>
    <div class="bloco"><span class="icone">${icone('layers')}</span><h3>O que fazemos</h3><p>Cinco áreas complementares: Organização e Métodos; IA e transformação digital; proteção de dados e conformidade; estratégia e gestão; formação, com cursos abertos de IA generativa e programas à medida.</p></div>
    <div class="bloco"><span class="icone">${icone('route')}</span><h3>Como trabalhamos</h3><p>Quatro fases (diagnóstico, desenho, implementação e acompanhamento), por projeto de âmbito fechado ou em acompanhamento continuado, com propostas à medida.</p></div>
  </div>
  <dl class="factos">
    <div><dt>Para quem</dt><dd>Pequenas e médias empresas (PME) e respetivas administrações</dd></div>
    <div><dt>Setores com experiência</dt><dd>Têxtil e vestuário, tecnologia, turismo, retalho</dd></div>
    <div><dt>Sede</dt><dd>Rua das Cruzes, 625, 4100-195 Porto</dd></div>
    <div><dt>Contacto</dt><dd>info@gadgetmetodo.pt · +351&nbsp;915&nbsp;416&nbsp;112</dd></div>
  </dl>`,
}));

/* 4. Ponto de vista */
paginas.push(pagina({
  seccao: 'O nosso ponto de vista',
  corpo: `
  <h1 class="titulo-acao">A tecnologia rende mais numa organização arrumada: por isso tratamos organização, tecnologia, conformidade e estratégia como um só sistema</h1>
  <div class="grelha-pv">
    <div class="insights">
      <div class="insight"><span class="i-n">1</span><div><h3>Organização primeiro</h3><p>Um sistema novo aplicado a processos confusos tende a automatizar a confusão. Os projetos começam pela estrutura, pelos processos e pelas responsabilidades.</p></div></div>
      <div class="insight"><span class="i-n">2</span><div><h3>A IA começa nos casos de uso</h3><p>O ponto de partida não é a ferramenta, é o problema. Priorizamos os casos de uso pelo retorno esperado e pelo esforço de implementação.</p></div></div>
      <div class="insight"><span class="i-n">3</span><div><h3>Conformidade desde o desenho</h3><p>Proteção de dados, segurança da informação e governação da IA entram no início do projeto e não como correção no fim.</p></div></div>
      <div class="insight"><span class="i-n">4</span><div><h3>Pessoas preparadas, resultados medidos</h3><p>Formação, gestão da mudança e indicadores acompanhados pela administração consolidam o que foi implementado.</p></div></div>
    </div>
    <figure class="figura">
      <figcaption><strong>Figura 1.</strong> Modelo integrado de intervenção</figcaption>
      <div class="modelo">
        <div class="m m1"><span>01</span>Organização e Métodos</div>
        <div class="m m2"><span>02</span>IA e transformação digital</div>
        <div class="m m3"><span>03</span>Proteção de dados e conformidade</div>
        <div class="m m4"><span>04</span>Estratégia e gestão</div>
        <div class="m-centro">Resultados medidos pela administração</div>
      </div>
      <div class="modelo-base"><span>05</span>Formação: pessoas preparadas para a mudança</div>
      <p class="fonte">Os módulos que se ligam para formar uma solução são também a ideia do símbolo da marca.</p>
    </figure>
  </div>`,
}));

/* 5. Mapa de serviços */
const FORMATO = ['Projeto de âmbito fechado', 'Projeto por fases ou acompanhamento continuado', 'Encarregado de Proteção de Dados (DPO) externo ou projeto de conformidade', 'Projeto ou conselho continuado à administração', 'Cursos abertos com inscrição online ou programas à medida, presenciais ou à distância'];
const PARA = ['Simplificar a estrutura e os processos, clarificar responsabilidades e preparar certificações', 'Escolher e implementar casos de uso de IA com retorno, integrados nos sistemas existentes', 'Cumprir o Regulamento Geral sobre a Proteção de Dados (RGPD) e a Diretiva NIS2 e governar o uso de IA com proporcionalidade', 'Transformar a estratégia em objetivos, indicadores e incentivos por equipa', 'Preparar pessoas, equipas e administração para usar, aplicar e liderar a IA com responsabilidade'];
paginas.push(pagina({
  seccao: 'Mapa de serviços',
  corpo: `
  <h1 class="titulo-acao">Cinco áreas complementares, que podem ser contratadas isoladamente ou combinadas num único projeto</h1>
  <table class="tabela mapa">
    <thead><tr><th style="width:24%">Área</th><th style="width:31%">Para que serve</th><th style="width:27%">Principais entregáveis</th><th>Formato típico</th></tr></thead>
    <tbody>${SERVICOS.map((s, i) => `<tr><th><span class="icone pequeno-ic">${icone(s.icone)}</span><span><small>${s.num}</small>${s.nome}</span></th><td>${PARA[i]}</td><td>${s.entregaveis.slice(0, 3).join('; ')}</td><td>${FORMATO[i]}</td></tr>`).join('')}</tbody>
  </table>`,
}));

/* 6 a 10. Serviços */
for (const s of SERVICOS) paginas.push(paginaServico(s));

/* 11. A Metodologia */
const FASES = [
  ['Diagnóstico', 'Levantamento de processos, sistemas, equipas, dados e riscos. Prioridades com maior retorno.', ['Relatório de diagnóstico', 'Prioridades e oportunidades']],
  ['Desenho', 'Modelo organizacional, processos, arquitetura de sistemas e regras de governação.', ['Solução desenhada', 'Plano de ação com calendário']],
  ['Implementação', 'Execução com as equipas: ferramentas, procedimentos, formação e gestão da mudança.', ['Soluções em funcionamento', 'Procedimentos e equipas formadas']],
  ['Acompanhamento', 'Medição de resultados, ajustes e apoio regular à administração.', ['Indicadores acompanhados', 'Relatórios para a administração']],
];
paginas.push(pagina({
  seccao: 'A Metodologia',
  corpo: `
  <h1 class="titulo-acao">Um método em quatro fases, com entregáveis definidos em cada uma e decisões apoiadas em dados</h1>
  <figure class="figura">
    <figcaption><strong>Figura 4.</strong> Fases de um projeto Gadgetmetodo</figcaption>
    <div class="fases">${FASES.map(([t], i) => `<div class="fase"><span>${String(i + 1).padStart(2, '0')}</span>${t}</div>`).join('')}</div>
    <div class="fases-det">${FASES.map(([, d, e]) => `<div><p>${d}</p><h4>Entregáveis</h4>${check(e)}</div>`).join('')}</div>
  </figure>
  <p class="nota-pag">O âmbito, os entregáveis e o calendário de cada fase são fixados na proposta. A proteção de dados e a gestão da mudança atravessam todas as fases.</p>`,
}));

/* 12. Modelos e princípios */
paginas.push(pagina({
  seccao: 'Modelos de colaboração e princípios',
  corpo: `
  <h1 class="titulo-acao">Três formas de trabalhar connosco, sempre com condições escritas na proposta</h1>
  <div class="tres">
    <div class="bloco"><span class="icone">${icone('target')}</span><h3>Projeto de âmbito fechado</h3><p>Diagnóstico, implementação de uma solução ou reorganização de uma área, com entregáveis definidos e pagamento associado à conclusão ou a marcos do projeto.</p></div>
    <div class="bloco"><span class="icone">${icone('handshake')}</span><h3>Acompanhamento continuado</h3><p>Apoio regular à administração, por exemplo como board advisor ou DPO externo, com prestações periódicas, como trimestrais, ao longo de um ou mais anos.</p></div>
    <div class="bloco"><span class="icone">${icone('graduation-cap')}</span><h3>Formação</h3><p>Cursos abertos do percurso IA Generativa no Trabalho, com inscrição online, ou o programa AI Essentials à medida, isolados ou integrados num projeto.</p></div>
  </div>
  <h3 class="rotulo mt-g">Princípios que orientam as nossas decisões</h3>
  <dl class="principios">
    <div><dt>Clareza</dt><dd>Comunicação direta, entregáveis compreensíveis e decisões fundamentadas.</dd></div>
    <div><dt>Método</dt><dd>Diagnóstico rigoroso, processos documentados e indicadores para medir o progresso.</dd></div>
    <div><dt>Integração</dt><dd>Pessoas, processos e tecnologia tratados como um só sistema.</dd></div>
    <div><dt>Confiança</dt><dd>Confidencialidade, proteção de dados e governação com bom senso.</dd></div>
  </dl>`,
}));

/* 13. Experiência */
const CASOS = [
  ['shirt', 'Têxtil e vestuário', 'Digitalização do desenvolvimento de produto numa confeção de malhas circulares', 'Implementação de prototipagem 3D de vestuário, com bibliotecas de tecidos e avatares, integração com moldes digitais, formação e acompanhamento.', 'Menos tempo, custo e desperdício nas amostras; melhor comunicação entre comercial, desenvolvimento e produção.'],
  ['file-text', 'Tecnologia e gestão documental', 'Estratégia de IA e proteção de dados numa empresa de serviços tecnológicos', 'Conselho à administração em inovação e tecnologia, funções de DPO, roteiro de IA, governação com RGPD e NIS2 e formação AI Essentials, ao longo de dois anos.', 'Casos de uso com maior retorno, IA integrada na oferta e conformidade demonstrável.'],
  ['wine', 'Turismo premium', 'IA e automação de marketing numa agência de enoturismo', 'Diagnóstico de campanhas, gestão de clientes e website; jornada do cliente; fluxos automáticos com IA; indicadores e painéis; formação da equipa.', 'Personalização em escala sem perder o toque humano e medição rigorosa do marketing.'],
  ['shopping-bag', 'Retalho especializado', 'Plano de recuperação de margem num retalhista omnicanal de tecnologia', 'Análise de concorrência e de margens por canal, organização das equipas por canal, objetivos e modelo de incentivos indexado à margem, nova unidade de assistência técnica.', 'Inverter a perda de margem e ligar os incentivos aos resultados.'],
];
paginas.push(pagina({
  seccao: 'Experiência',
  corpo: `
  <h1 class="titulo-acao">Experiência na indústria, na tecnologia, no turismo e no retalho, com o mesmo método em contextos diferentes</h1>
  <div class="casos">${CASOS.map(([ic, setor, tit, amb, obj]) => `<div class="caso">
    <div class="caso-cab"><span class="icone">${icone(ic)}</span><span class="setor">${setor}</span></div>
    <h3>${tit}</h3>
    <p><strong>Âmbito.</strong> ${amb}</p>
    <p><strong>Objetivos.</strong> ${obj}</p>
  </div>`).join('')}</div>
  <p class="nota-pag">Por confidencialidade, os clientes não são identificados.</p>`,
}));

/* 14. Equipa */
paginas.push(pagina({
  seccao: 'Equipa',
  corpo: `
  <h1 class="titulo-acao">Cada projeto é acompanhado da fase de diagnóstico à implementação pela mesma pessoa</h1>
  <div class="equipa">
    <div class="perfil">
      <div class="monograma">JSC</div>
      <div>
        <h2>Jorge Sá Couto</h2>
        <p class="funcao">Gestor de projeto e consultor</p>
        <p>Licenciado em Engenharia Informática, com formação complementar em economia, gestão e estratégia de inteligência artificial. Acompanha os projetos da Gadgetmetodo da fase de diagnóstico à implementação, com especial foco na ligação entre organização, tecnologia e proteção de dados.</p>
      </div>
    </div>
    <div class="formacao">
      <h3 class="rotulo">Formação académica e certificações</h3>
      ${check(['Licenciatura em Engenharia Informática, Universidade NOVA de Lisboa', 'Frequência do curso de Economia, Universidade de Coimbra', 'MBA em Gestão, ISCTE - Instituto Universitário de Lisboa', 'Certificado The Business of AI: Strategies for Leaders, London Business School', 'Certificado de Competências Pedagógicas (CCP)'])}
    </div>
  </div>
  <h3 class="rotulo mt-g">Áreas em que acompanha projetos</h3>
  <div class="areas" style="margin-top:0">${SERVICOS.map(s => `<div><span class="icone">${icone(s.icone)}</span><br>${s.nome}</div>`).join('')}</div>`,
}));

/* 15. Como iniciar */
const PASSOS = [
  ['Pedido', 'Pelo formulário em gadgetmetodo.pt, por email ou por telefone.'],
  ['Primeira conversa', 'Objetivos, contexto, prioridades e prazos.'],
  ['Proposta à medida', 'Âmbito, entregáveis, calendário e investimento.'],
  ['Arranque', 'Equipa, plano de trabalho e primeiros entregáveis.'],
];
paginas.push(pagina({
  seccao: 'Como iniciar',
  corpo: `
  <h1 class="titulo-acao">Da primeira conversa à proposta: quatro passos simples para começar</h1>
  <div class="passos">${PASSOS.map(([t, d], i) => `<div class="passo"><span>${String(i + 1).padStart(2, '0')}</span><h3>${t}</h3><p>${d}</p></div>`).join('')}</div>
  <div class="cta">
    <div>
      <h2>Vamos simplificar o próximo passo da sua organização?</h2>
      <p>Conte-nos o seu desafio. Preparamos uma proposta à medida, com âmbito, entregáveis, calendário e investimento claros.</p>
    </div>
    <dl>
      <div><dt>Formulário</dt><dd>www.gadgetmetodo.pt/contactos</dd></div>
      <div><dt>Email</dt><dd>info@gadgetmetodo.pt</dd></div>
      <div><dt>Telefone</dt><dd>+351&nbsp;915&nbsp;416&nbsp;112</dd></div>
    </dl>
  </div>`,
}));

/* 16. Contracapa */
paginas.push(pagina({
  classe: 'contracapa', sem_rodape: true,
  corpo: `
  <img class="capa-simbolo" src="${A}/marca/simbolo-negativo.svg" alt="">
  <img class="capa-logo" src="${A}/marca/logotipo-negativo.svg" alt="Gadgetmetodo, Lda.">
  <div class="contracapa-texto">
    <p class="assinatura">Simplificar&nbsp;&nbsp;·&nbsp;&nbsp;Organizar&nbsp;&nbsp;·&nbsp;&nbsp;Fazer acontecer</p>
    <dl>
      <div><dt>Morada</dt><dd>Rua das Cruzes, 625<br>4100-195 Porto, Portugal</dd></div>
      <div><dt>Contactos</dt><dd>+351&nbsp;915&nbsp;416&nbsp;112<br>info@gadgetmetodo.pt</dd></div>
      <div><dt>Website</dt><dd>www.gadgetmetodo.pt</dd></div>
    </dl>
  </div>
  <p class="legal">© 2026 Gadgetmetodo, Lda. · NIPC 513&nbsp;978&nbsp;011. Documento informativo, que não constitui proposta contratual. As condições de cada projeto são definidas em proposta específica.</p>`,
}));

if (n !== TOTAL) console.warn('Atenção: ' + n + ' páginas, esperado ' + TOTAL);

const css = fs.readFileSync(path.join(path.dirname(new URL(import.meta.url).pathname), 'catalogo.css'), 'utf8');
const html = `<!doctype html>
<html lang="pt-PT">
<head>
<meta charset="utf-8">
<title>Catálogo de Serviços 2026 | Gadgetmetodo, Lda.</title>
<meta name="author" content="Gadgetmetodo, Lda.">
<style>
${css}
</style>
</head>
<body>
${paginas.join('\n')}
</body>
</html>
`;
fs.writeFileSync(path.join(OUT, 'catalogo.html'), html);
console.log(n, 'páginas');
