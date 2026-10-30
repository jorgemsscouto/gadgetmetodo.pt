/* Inquérito Luxury Tours Portugal
   Todos os textos vêm dos ficheiros i18n/*.json; este ficheiro só tem estrutura e comportamento.
   As respostas são guardadas com códigos internos neutros (region_tuscany, intent_very_likely, ...). */
(function () {
  'use strict';

  var D = JSON.parse(document.getElementById('ltp-dados').textContent);
  var S = D.structure;
  var app = document.getElementById('app');
  var seletor = document.getElementById('idioma');
  var cache = {};
  cache[D.lang] = D.texts;

  var estado = {
    lang: D.lang,
    t: D.texts,
    vista: D.state === 'open' ? 'intro' : D.state, // intro | passo | fim | closed | unavailable
    passo: 1,
    respostas: D.previous ? JSON.parse(JSON.stringify(D.previous)) : vazio(),
    // Identificação: só pedida a quem entra pela ligação geral (sem convite)
    identidade: { name: '', email: '', phone: '', country: '' },
    aEnviar: false
  };
  var pedeIdentidade = !D.token;
  var comHover = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

  function vazio() {
    return { regions: [], regions_other: '', intent: null, duration: null, group: null, budget: null, seasons: [], priorities: [], comment: '', contact: null };
  }

  /* Textos e formatação local */

  function t(caminho) {
    var v = estado.t;
    caminho.split('.').forEach(function (p) { v = v && typeof v === 'object' ? v[p] : undefined; });
    return typeof v === 'string' ? v : '';
  }

  function locale() { return estado.t.locale || estado.lang; }

  function numero(n) {
    try { return new Intl.NumberFormat(locale()).format(n); } catch (e) { return String(n); }
  }

  function euros(n) {
    try {
      return new Intl.NumberFormat(locale(), { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(n);
    } catch (e) { return n + ' EUR'; }
  }

  function data(iso) {
    var d = new Date(iso + 'T12:00:00Z');
    try {
      return new Intl.DateTimeFormat(locale(), { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Europe/Lisbon' }).format(d);
    } catch (e) { return iso; }
  }

  /* Construção do DOM */

  function el(tag, attrs, filhos) {
    var n = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        var v = attrs[k];
        if (v === null || v === undefined || v === false) return;
        if (k === 'class') n.className = v;
        else if (k === 'text') n.textContent = v;
        else if (k.slice(0, 2) === 'on') n.addEventListener(k.slice(2), v);
        else n.setAttribute(k, v === true ? '' : v);
      });
    }
    (filhos || []).forEach(function (f) {
      if (f === null || f === undefined || f === '') return;
      n.appendChild(typeof f === 'string' ? document.createTextNode(f) : f);
    });
    return n;
  }

  // Substitui {marcadores}; cada valor fica isolado num <bdi> para não baralhar o texto da direita para a esquerda
  function preencher(texto, vars) {
    var frag = document.createDocumentFragment();
    var partes = texto.split(/(\{\w+\})/);
    partes.forEach(function (p) {
      var m = /^\{(\w+)\}$/.exec(p);
      if (m && vars && Object.prototype.hasOwnProperty.call(vars, m[1])) {
        var v = vars[m[1]];
        frag.appendChild(el('bdi', null, [typeof v === 'string' ? v : v.cloneNode(true)]));
      } else if (p) {
        frag.appendChild(document.createTextNode(p));
      }
    });
    return frag;
  }

  function seta(direcao) {
    var ns = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('class', 'seta seta-' + direcao);
    var p = document.createElementNS(ns, 'path');
    p.setAttribute('d', direcao === 'frente' ? 'M5 12h13M13 6l6 6-6 6' : 'M19 12H6M11 6l-6 6 6 6');
    svg.appendChild(p);
    return svg;
  }

  function nomeRegiao(codigo) {
    var r = (estado.t.regions || {})[codigo] || {};
    return el('span', { class: 'regiao-rotulo' }, [
      el('span', { class: 'regiao-nome', text: r.name || codigo }),
      ' ',
      el('span', { class: 'regiao-pais', text: '(' + (r.country || '') + ')' })
    ]);
  }

  function rotuloOpcao(codigo) {
    var texto = (estado.t.options || {})[codigo] || codigo;
    var valores = S.budget[codigo];
    if (!valores) return document.createTextNode(texto);
    var vars = {};
    if (valores.min !== undefined) vars.min = euros(valores.min);
    if (valores.max !== undefined) vars.max = euros(valores.max);
    return preencher(texto, vars);
  }

  /* Vistas */

  function render(focar) {
    app.textContent = '';
    var vista;
    if (estado.vista === 'intro') vista = vistaIntro();
    else if (estado.vista === 'passo') vista = vistaPasso(estado.passo);
    else if (estado.vista === 'fim') vista = vistaFim();
    else vista = vistaAviso(estado.vista);
    app.appendChild(vista);
    rodape();
    if (focar) {
      var alvo = app.querySelector('[data-foco]') || app;
      alvo.focus({ preventScroll: true });
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  }

  function saudacao() {
    if (!D.name) return null;
    var chave = D.form === 'm' ? 'intro.greeting_m' : D.form === 'f' ? 'intro.greeting_f' : 'intro.greeting';
    return el('p', { class: 'saudacao' }, [preencher(t(chave), { name: D.name })]);
  }

  function vistaIntro() {
    return el('section', { class: 'cartao intro' }, [
      el('p', { class: 'olho', text: t('intro.eyebrow') }),
      el('div', { class: 'filete', 'aria-hidden': 'true' }),
      saudacao(),
      el('h1', { tabindex: '-1', 'data-foco': true, text: t('intro.title') }),
      el('p', { class: 'lead', text: t('intro.lead') }),
      D.previous && D.closeDate ? el('p', { class: 'nota' }, [preencher(t('intro.returning'), { date: data(D.closeDate) })]) : null,
      el('p', { class: 'meta' }, [
        t('intro.length'),
        D.closeDate ? ' ' : '',
        D.closeDate ? preencher(t('intro.deadline'), { date: data(D.closeDate) }) : ''
      ]),
      el('div', { class: 'acoes acoes-centro' }, [
        el('button', { type: 'button', class: 'botao botao-principal', onclick: function () { irPara(1); } }, [
          el('span', { text: t('ui.begin') }), seta('frente')
        ])
      ])
    ]);
  }

  function vistaPasso(n) {
    var total = S.steps.length;
    var passo = S.steps[n - 1];
    var progresso = preencher(t('ui.progress'), { n: numero(n), total: numero(total) });
    var textoProgresso = t('ui.progress').replace('{n}', numero(n)).replace('{total}', numero(total));
    var corpo = el('form', { class: 'cartao passo', novalidate: true, onsubmit: function (e) { e.preventDefault(); avancar(); } }, [
      el('div', { class: 'passo-topo' }, [
        el('p', { class: 'passo-numero' }, [progresso]),
        el('div', {
          class: 'progresso', role: 'progressbar', 'aria-valuemin': '1', 'aria-valuemax': String(total),
          'aria-valuenow': String(n), 'aria-valuetext': textoProgresso
        }, [el('div', { class: 'progresso-barra', style: 'inline-size:' + (n / total * 100) + '%' })])
      ])
    ]);
    var primeiro = true;
    if (passo.id === 'contact' && pedeIdentidade) {
      corpo.appendChild(campoIdentidade());
      primeiro = false;
    }
    passo.fields.forEach(function (campo) {
      corpo.appendChild(campoVista(campo, primeiro));
      primeiro = false;
    });
    if (passo.id === 'contact') {
      // Campo isco para robôs (invisível para pessoas)
      corpo.appendChild(el('div', { class: 'isco', 'aria-hidden': 'true' }, [
        el('input', { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off' })
      ]));
    }
    corpo.appendChild(el('p', { class: 'erro', role: 'alert', id: 'erro' }));
    var ultimo = n === total;
    corpo.appendChild(el('div', { class: 'acoes' }, [
      el('button', { type: 'button', class: 'botao botao-secundario', onclick: function () { recuar(); } }, [
        seta('tras'), el('span', { text: t('ui.back') })
      ]),
      el('button', { type: 'submit', class: 'botao botao-principal', disabled: estado.aEnviar }, [
        el('span', { text: estado.aEnviar ? t('ui.sending') : t(ultimo ? 'ui.submit' : 'ui.next') }),
        ultimo ? null : seta('frente')
      ])
    ]));
    return corpo;
  }

  function titulo(texto, principal, id) {
    return el(principal ? 'h2' : 'h3', { class: 'pergunta', id: id, tabindex: principal ? '-1' : null, 'data-foco': principal || null }, [texto]);
  }

  function campoVista(campo, principal) {
    if (campo === 'regions') return campoRegioes(principal);
    if (campo === 'comment') return campoComentario();
    var def = S.fields[campo];
    var chave = 'steps.' + campo;
    var id = 'q-' + campo;
    var grupo = el('fieldset', { class: 'campo', 'aria-labelledby': id, 'data-campo': campo });
    if (campo === 'intent' && estado.respostas.regions[0]) {
      grupo.appendChild(el('p', { class: 'olho olho-escolha' }, [preencher(t('steps.intent.kicker'), { region: nomeRegiao(estado.respostas.regions[0]) })]));
    }
    grupo.appendChild(titulo(t(chave + '.title'), principal, id));
    if (t(chave + '.help')) grupo.appendChild(el('p', { class: 'ajuda', text: t(chave + '.help') }));
    var lista = el('div', { class: 'opcoes' + (def.options.length > 4 ? ' opcoes-duas' : '') });
    def.options.forEach(function (codigo) {
      var multi = def.type === 'multi';
      var marcado = multi ? estado.respostas[campo].indexOf(codigo) !== -1 : estado.respostas[campo] === codigo;
      var input = el('input', {
        type: multi ? 'checkbox' : 'radio', name: campo, value: codigo, checked: marcado,
        onchange: function (e) { escolher(campo, codigo, e.target, multi); }
      });
      lista.appendChild(el('label', { class: 'opcao' }, [input, el('span', { class: 'opcao-marca', 'aria-hidden': 'true' }), el('span', { class: 'opcao-texto' }, [rotuloOpcao(codigo)])]));
    });
    grupo.appendChild(lista);
    if (campo === 'contact') {
      var priv = el('p', { class: 'privacidade' }, [t(pedeIdentidade ? 'steps.identity.privacy_text' : 'steps.contact.privacy_text')]);
      if (D.privacyUrl) {
        priv.appendChild(document.createTextNode(' '));
        priv.appendChild(el('a', { href: D.privacyUrl, target: '_blank', rel: 'noopener', text: t('ui.privacy') }));
      }
      grupo.appendChild(priv);
    }
    return grupo;
  }

  function escolher(campo, codigo, input, multi) {
    if (!multi) {
      estado.respostas[campo] = codigo;
    } else {
      var lista = estado.respostas[campo];
      var i = lista.indexOf(codigo);
      if (input.checked && i === -1) {
        if (campo === 'priorities' && lista.length >= S.maxPriorities) {
          input.checked = false;
          mostrarErro(preencher(t('ui.limit_reached'), { max: numero(S.maxPriorities) }));
          return;
        }
        lista.push(codigo);
      } else if (!input.checked && i !== -1) {
        lista.splice(i, 1);
      }
    }
    mostrarErro(null);
  }

  function campoRegioes(principal) {
    var grupo = el('fieldset', { class: 'campo', 'aria-labelledby': 'q-regions', 'data-campo': 'regions' }, [
      titulo(t('steps.regions.title'), principal, 'q-regions'),
      el('p', { class: 'ajuda', text: t('steps.regions.help') }),
      el('p', { class: 'contagem', id: 'contagem', 'aria-live': 'polite' })
    ]);
    var grelha = el('div', { class: 'regioes' });
    S.regions.forEach(function (codigo) {
      var r = (estado.t.regions || {})[codigo] || {};
      var botao = el('button', { type: 'button', class: 'regiao', 'data-codigo': codigo, onclick: function () { alternarRegiao(codigo); } }, [
        el('span', { class: 'regiao-ordem', 'aria-hidden': 'true' }),
        el('span', { class: 'sr regiao-ordem-sr' }),
        el('span', { class: 'regiao-nome', text: r.name || codigo }),
        el('span', { class: 'regiao-pais', text: '(' + (r.country || '') + ')' }),
        el('span', { class: 'regiao-nota', text: r.note || '' })
      ]);
      grelha.appendChild(el('div', { class: 'regiao-cartao' }, [botao].concat(mapaRegiao(codigo, r))));
    });
    grupo.appendChild(grelha);
    var outra = el('input', {
      type: 'text', id: 'regiao-outra', maxlength: String(S.maxOther), placeholder: t('steps.regions.other_placeholder'),
      value: estado.respostas.regions_other, autocomplete: 'off',
      oninput: function (e) { estado.respostas.regions_other = e.target.value; }
    });
    grupo.appendChild(el('div', { class: 'texto-livre' }, [
      el('label', { for: 'regiao-outra' }, [t('steps.regions.other_label'), ' ', el('span', { class: 'opcional', text: '(' + t('ui.optional') + ')' })]),
      outra
    ]));
    setTimeout(atualizarRegioes, 0);
    return grupo;
  }

  /* Identificação (ligação geral) */

  function nomesPaises() {
    var nomes = null;
    try { nomes = new Intl.DisplayNames([locale(), estado.lang], { type: 'region' }); } catch (e) { /* sem suporte: mostra o código */ }
    var lista = S.countries.map(function (c) {
      var n = c;
      try { n = (nomes && nomes.of(c)) || c; } catch (e) { /* código desconhecido no navegador */ }
      return { codigo: c, nome: n };
    });
    var ordem;
    try { ordem = new Intl.Collator(locale()).compare; } catch (e) { ordem = function (a, b) { return a < b ? -1 : a > b ? 1 : 0; }; }
    lista.sort(function (a, b) { return ordem(a.nome, b.nome); });
    return lista;
  }

  function campoTexto(id, rotulo, atributos, ajuda) {
    var chave = id.slice(3);
    var input = el(atributos.tag || 'input', Object.assign({
      id: id, name: chave, required: true,
      'aria-describedby': ajuda ? id + '-ajuda' : null,
      oninput: function (e) { estado.identidade[chave] = e.target.value; mostrarErro(null); e.target.removeAttribute('aria-invalid'); },
      onchange: function (e) { estado.identidade[chave] = e.target.value; }
    }, atributos.attrs || {}), atributos.filhos || []);
    if (input.tagName === 'INPUT') input.value = estado.identidade[chave];
    return el('div', { class: 'texto-livre identidade-campo' }, [
      el('label', { for: id, text: rotulo }),
      input,
      ajuda ? el('p', { class: 'ajuda-campo', id: id + '-ajuda', text: ajuda }) : null
    ]);
  }

  function campoIdentidade() {
    var opcoes = [el('option', { value: '', text: t('steps.identity.country_placeholder') })];
    nomesPaises().forEach(function (p) {
      opcoes.push(el('option', { value: p.codigo, selected: estado.identidade.country === p.codigo, text: p.nome }));
    });
    return el('fieldset', { class: 'campo identidade', 'aria-labelledby': 'q-identity' }, [
      titulo(t('steps.identity.title'), true, 'q-identity'),
      el('p', { class: 'ajuda', text: t('steps.identity.intro') }),
      el('div', { class: 'identidade-grelha' }, [
        campoTexto('id-name', t('steps.identity.name'), { attrs: { type: 'text', autocomplete: 'name', maxlength: String(S.maxName) } }),
        campoTexto('id-email', t('steps.identity.email'), { attrs: { type: 'email', autocomplete: 'email', inputmode: 'email', dir: 'ltr', maxlength: '160' } }),
        campoTexto('id-phone', t('steps.identity.phone'), { attrs: { type: 'tel', autocomplete: 'tel', inputmode: 'tel', dir: 'ltr', maxlength: '30' } }, t('steps.identity.phone_help')),
        campoTexto('id-country', t('steps.identity.country'), { tag: 'select', attrs: { autocomplete: 'country' }, filhos: opcoes })
      ])
    ]);
  }

  /* Mapa de cada região: (i) com pré-visualização ao passar o rato; o clique abre o mapa */

  function icone(caminhos, classe) {
    var ns = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('class', classe);
    caminhos.forEach(function (d) {
      var p = document.createElementNS(ns, 'path');
      p.setAttribute('d', d);
      svg.appendChild(p);
    });
    return svg;
  }

  function mapaRegiao(codigo, r) {
    var nome = (r.name || codigo) + ' (' + (r.country || '') + ')';
    var rotulo = t('ui.map_label').replace('{region}', nome);
    var ligacao = S.maps[codigo];
    var painel = el('div', { class: 'regiao-mapa', id: 'mapa-' + codigo, role: 'tooltip', hidden: true }, [
      el('img', { src: 'assets/img/mapas/' + codigo + '.svg', alt: rotulo, width: '320', height: '220', loading: 'lazy', decoding: 'async' }),
      el('p', { class: 'regiao-mapa-titulo' }, [el('bdi', null, [nomeRegiao(codigo)])]),
      el('a', { class: 'regiao-mapa-ligacao', href: ligacao, target: '_blank', rel: 'noopener' }, [
        el('span', { text: t('ui.map_open') }),
        icone(['M14 4h6v6', 'M20 4l-9 9', 'M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5'], 'icone-externo')
      ])
    ]);
    var info = el('a', {
      class: 'regiao-info', href: ligacao, target: '_blank', rel: 'noopener',
      'aria-label': rotulo, 'aria-describedby': 'mapa-' + codigo, 'aria-expanded': 'false'
    }, [icone(['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z', 'M12 11v6', 'M12 7.5v.01'], 'icone-info')]);

    var temporizador = null;
    var abertoEm = 0;
    function abrir() {
      clearTimeout(temporizador);
      abertoEm = Date.now();
      fecharMapas(painel);
      painel.hidden = false;
      info.setAttribute('aria-expanded', 'true');
      painel.classList.remove('acima');
      var caixa = painel.getBoundingClientRect();
      if (caixa.bottom > window.innerHeight - 8 && caixa.top - caixa.height - 48 > 0) painel.classList.add('acima');
    }
    function fechar() {
      painel.hidden = true;
      info.setAttribute('aria-expanded', 'false');
    }
    function fecharDepois() {
      clearTimeout(temporizador);
      temporizador = setTimeout(fechar, 220);
    }
    painel._fechar = fechar;
    if (comHover) {
      [info, painel].forEach(function (n) {
        n.addEventListener('mouseenter', abrir);
        n.addEventListener('mouseleave', fecharDepois);
      });
    } else {
      // Ecrãs tácteis: o primeiro toque mostra o mapa; o botão dentro do mapa abre-o
      info.addEventListener('click', function (e) {
        e.preventDefault();
        // O toque também dá foco ao (i) e abre o mapa; só fecha num segundo toque
        if (painel.hidden) abrir(); else if (Date.now() - abertoEm > 400) fechar();
      });
    }
    info.addEventListener('focus', abrir);
    info.addEventListener('blur', function () { if (!painel.contains(document.activeElement)) fecharDepois(); });
    painel.addEventListener('focusout', function (e) { if (!painel.contains(e.relatedTarget) && e.relatedTarget !== info) fecharDepois(); });
    painel.addEventListener('focusin', function () { clearTimeout(temporizador); });
    return [info, painel];
  }

  function fecharMapas(excepto) {
    app.querySelectorAll('.regiao-mapa').forEach(function (m) {
      if (m !== excepto && !m.hidden && m._fechar) m._fechar();
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') fecharMapas(null);
  });
  document.addEventListener('click', function (e) {
    if (!e.target.closest || !e.target.closest('.regiao-info, .regiao-mapa')) fecharMapas(null);
  });

  function alternarRegiao(codigo) {
    var lista = estado.respostas.regions;
    var i = lista.indexOf(codigo);
    if (i !== -1) {
      lista.splice(i, 1);
    } else if (lista.length >= S.maxRegions) {
      mostrarErro(preencher(t('ui.limit_reached'), { max: numero(S.maxRegions) }));
      return;
    } else {
      lista.push(codigo);
    }
    mostrarErro(null);
    atualizarRegioes();
  }

  function atualizarRegioes() {
    var lista = estado.respostas.regions;
    app.querySelectorAll('.regiao').forEach(function (b) {
      var pos = lista.indexOf(b.getAttribute('data-codigo'));
      b.setAttribute('aria-pressed', pos !== -1 ? 'true' : 'false');
      b.querySelector('.regiao-ordem').textContent = pos !== -1 ? t('rank.' + (pos + 1)) : '';
      b.querySelector('.regiao-ordem-sr').textContent = pos !== -1 ? t('rank_choice').replace('{rank}', t('rank.' + (pos + 1))) + ': ' : '';
    });
    var contagem = document.getElementById('contagem');
    if (contagem) {
      contagem.textContent = '';
      contagem.appendChild(preencher(t('ui.selected_count'), { count: numero(lista.length), max: numero(S.maxRegions) }));
    }
  }

  function campoComentario() {
    return el('div', { class: 'texto-livre' }, [
      el('label', { for: 'comentario' }, [t('steps.comment.label'), ' ', el('span', { class: 'opcional', text: '(' + t('ui.optional') + ')' })]),
      el('textarea', {
        id: 'comentario', rows: '4', maxlength: String(S.maxComment), placeholder: t('steps.comment.placeholder'),
        oninput: function (e) { estado.respostas.comment = e.target.value; }
      }, [estado.respostas.comment])
    ]);
  }

  function vistaFim() {
    var r = estado.respostas;
    var resumo = el('ol', { class: 'resumo' });
    r.regions.forEach(function (codigo, i) {
      resumo.appendChild(el('li', null, [
        el('span', { class: 'regiao-ordem', text: t('rank.' + (i + 1)) }),
        el('bdi', null, [nomeRegiao(codigo)])
      ]));
    });
    return el('section', { class: 'cartao fim' }, [
      el('img', { class: 'selo', src: 'assets/img/selo.png', alt: '', width: '274', height: '259' }),
      el('h1', { tabindex: '-1', 'data-foco': true }, [D.name ? preencher(t('thanks.title_name'), { name: D.name }) : t('thanks.title')]),
      el('p', { class: 'lead', text: t('thanks.text') }),
      el('h2', { class: 'olho', text: t('thanks.summary') }),
      resumo,
      el('p', { class: 'assinatura', text: t('thanks.signature') }),
      el('div', { class: 'acoes acoes-centro' }, [
        el('button', { type: 'button', class: 'botao botao-secundario', onclick: function () { irPara(1); }, text: t('thanks.edit') })
      ])
    ]);
  }

  function vistaAviso(tipo) {
    if (tipo === 'closed') {
      return el('section', { class: 'cartao fim' }, [
        el('img', { class: 'selo', src: 'assets/img/selo.png', alt: '', width: '274', height: '259' }),
        el('h1', { tabindex: '-1', 'data-foco': true, text: t('ui.closed_title') }),
        el('p', { class: 'lead', text: t('ui.closed_text') })
      ]);
    }
    return el('section', { class: 'cartao fim' }, [
      el('p', { class: 'lead', tabindex: '-1', 'data-foco': true, text: t('ui.unavailable') })
    ]);
  }

  function rodape() {
    var ligacoes = document.getElementById('rodape-ligacoes');
    ligacoes.textContent = '';
    if (D.privacyUrl) ligacoes.appendChild(el('a', { href: D.privacyUrl, target: '_blank', rel: 'noopener', text: t('ui.privacy') }));
    var aviso = document.getElementById('rodape-aviso');
    var partes = [];
    if (D.demo) partes.push(t('ui.prototype'));
    if (D.reviewed.indexOf(estado.lang) === -1) partes.push(t('ui.pending_review'));
    aviso.textContent = partes.join(' · ');
    aviso.hidden = partes.length === 0;
  }

  /* Navegação e validação */

  function mostrarErro(conteudo) {
    var e = document.getElementById('erro');
    if (!e) return;
    e.textContent = '';
    if (conteudo) e.appendChild(typeof conteudo === 'string' ? document.createTextNode(conteudo) : conteudo);
  }

  function irPara(n) {
    estado.vista = 'passo';
    estado.passo = n;
    render(true);
  }

  function recuar() {
    if (estado.passo <= 1) {
      estado.vista = 'intro';
      render(true);
    } else {
      irPara(estado.passo - 1);
    }
  }

  function validarIdentidade() {
    var i = estado.identidade;
    var digitos = i.phone.replace(/\D/g, '');
    var falhas = [
      ['name', i.name.trim().length < 2, 'ui.required_fields'],
      ['email', !i.email.trim(), 'ui.required_fields'],
      ['phone', !i.phone.trim(), 'ui.required_fields'],
      ['country', !i.country, 'ui.required_fields'],
      ['email', !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(i.email.trim()), 'ui.invalid_email'],
      ['phone', !/^(\+|00)?[0-9 ().\-]{6,24}$/.test(i.phone.trim()) || digitos.length < 7 || digitos.length > 15, 'ui.invalid_phone']
    ];
    app.querySelectorAll('.identidade [aria-invalid]').forEach(function (n) { n.removeAttribute('aria-invalid'); });
    for (var k = 0; k < falhas.length; k++) {
      if (falhas[k][1]) {
        var campo = document.getElementById('id-' + falhas[k][0]);
        if (campo) campo.setAttribute('aria-invalid', 'true');
        return { foco: campo, msg: t(falhas[k][2]) };
      }
    }
    return null;
  }

  function validarPasso(n) {
    if (S.steps[n - 1].id === 'contact' && pedeIdentidade) {
      var falhaId = validarIdentidade();
      if (falhaId) return falhaId;
    }
    var campos = S.steps[n - 1].fields;
    for (var i = 0; i < campos.length; i++) {
      var c = campos[i];
      if (c === 'regions' && estado.respostas.regions.length === 0) return { campo: c, msg: t('ui.required_rank') };
      var def = S.fields[c];
      if (!def || !def.required) continue;
      var v = estado.respostas[c];
      if (def.type === 'single' ? !v : !v.length) return { campo: c, msg: t('ui.required_one') };
    }
    return null;
  }

  function avancar() {
    if (estado.aEnviar) return;
    var falha = validarPasso(estado.passo);
    if (falha) {
      mostrarErro(falha.msg);
      if (falha.foco) {
        falha.foco.focus();
        return;
      }
      var alvo = app.querySelector('[data-campo="' + falha.campo + '"] input, [data-campo="' + falha.campo + '"] .regiao');
      if (alvo) alvo.focus();
      return;
    }
    if (estado.passo < S.steps.length) {
      irPara(estado.passo + 1);
    } else {
      enviar();
    }
  }

  function idAnonimo() {
    var id = null;
    try { id = localStorage.getItem('ltp-anon'); } catch (e) { /* armazenamento indisponível */ }
    if (!id || !/^[a-f0-9-]{16,40}$/.test(id)) {
      if (window.crypto && crypto.randomUUID) {
        id = crypto.randomUUID();
      } else {
        var b = new Uint8Array(16);
        (window.crypto || window.msCrypto).getRandomValues(b);
        id = Array.prototype.map.call(b, function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
      }
      try { localStorage.setItem('ltp-anon', id); } catch (e) { /* sem persistência: cada envio conta como novo */ }
    }
    return id;
  }

  function enviar() {
    estado.aEnviar = true;
    render(false);
    var isco = app.querySelector('input[name="website"]');
    var corpo = {
      lang: estado.lang,
      token: D.token,
      anon: D.token ? null : idAnonimo(),
      website: isco ? isco.value : '',
      identity: pedeIdentidade ? estado.identidade : null,
      answers: estado.respostas
    };
    fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(corpo), credentials: 'same-origin' })
      .then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (j) { return { status: res.status, corpo: j }; });
      })
      .then(function (r) {
        estado.aEnviar = false;
        if (r.status === 200 && r.corpo.ok) {
          D.previous = JSON.parse(JSON.stringify(estado.respostas));
          estado.vista = 'fim';
          render(true);
        } else if (r.status === 410) {
          estado.vista = 'closed';
          render(true);
        } else {
          render(false);
          mostrarErro(t('ui.error_send'));
        }
      })
      .catch(function () {
        estado.aEnviar = false;
        render(false);
        mostrarErro(t('ui.error_send'));
      });
  }

  /* Mudança de idioma sem perder as respostas */

  function carregarIdioma(codigo) {
    if (cache[codigo]) return Promise.resolve(cache[codigo]);
    return fetch('i18n/' + codigo + '.json', { credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) throw new Error('i18n');
      return r.json();
    }).then(function (j) { cache[codigo] = j; return j; });
  }

  function aplicarIdioma(codigo, textos) {
    estado.lang = codigo;
    estado.t = textos;
    var html = document.documentElement;
    html.lang = codigo;
    html.dir = textos.dir || 'ltr';
    document.title = t('meta.title');
    var desc = document.querySelector('meta[name="description"]');
    if (desc) desc.setAttribute('content', t('meta.description'));
    document.querySelectorAll('[data-t]').forEach(function (n) { n.textContent = t(n.getAttribute('data-t')); });
    try {
      var url = new URL(window.location.href);
      url.searchParams.set('lang', codigo);
      history.replaceState(null, '', url.toString());
    } catch (e) { /* URL sem suporte: o idioma mantém-se até recarregar */ }
    var erro = document.getElementById('erro');
    var tinhaErro = erro && erro.textContent !== '';
    render(false);
    if (tinhaErro && estado.vista === 'passo') {
      var falha = validarPasso(estado.passo);
      if (falha) mostrarErro(falha.msg);
    }
  }

  if (seletor) {
    seletor.addEventListener('change', function () {
      var codigo = seletor.value;
      seletor.disabled = true;
      carregarIdioma(codigo).then(function (textos) {
        aplicarIdioma(codigo, textos);
      }).catch(function () {
        seletor.value = estado.lang;
      }).then(function () {
        seletor.disabled = false;
      });
    });
  }

  render(false);
})();
