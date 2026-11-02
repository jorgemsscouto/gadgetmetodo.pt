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
    aEnviar: false
  };

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
      var priv = el('p', { class: 'privacidade' }, [t('steps.contact.privacy_text')]);
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
      grelha.appendChild(botao);
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

  function validarPasso(n) {
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
