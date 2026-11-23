// Texto na língua da página: português ou, nas páginas em inglês (<html lang="en-GB">), inglês
var gmEN = document.documentElement.lang === 'en-GB';
function gmT(pt, en) { return gmEN ? en : pt; }

// Área reservada: lista das sessões do curso no formulário da formação (cada formação é uma edição do curso completo).
// Mostra só a lista do curso escolhido e propõe datas semanais a partir da data e do horário da 1.ª sessão.
(function () {
  var seletor = document.getElementById('c-curso');
  if (!seletor || !document.querySelector('.sessoes-curso')) {
    return;
  }
  var data = document.getElementById('c-data');
  var inicio = document.getElementById('c-hora_inicio');
  var fim = document.getElementById('c-hora_fim');

  function ativo() {
    return document.querySelector('.sessoes-curso:not([hidden])');
  }

  // Ao abrir a página não se propõe nada (uma edição gravada só com parte das datas fica como está até se mexer nas datas)
  function mostrar(propostas) {
    var id = seletor.value;
    document.querySelectorAll('.sessoes-curso').forEach(function (bloco) {
      var visivel = bloco.getAttribute('data-curso') === id;
      bloco.hidden = !visivel;
      bloco.disabled = !visivel;
    });
    marcar();
    if (propostas) {
      propor(false);
    }
  }

  function texto(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  // "18:30" mais 1,5 horas = "20:00" (vazio se não houver hora)
  function somar(hora, horas) {
    if (!hora || !horas) {
      return '';
    }
    var p = hora.split(':');
    var min = (parseInt(p[0], 10) * 60 + parseInt(p[1], 10) + Math.round(parseFloat(horas) * 60)) % 1440;
    return String(Math.floor(min / 60)).padStart(2, '0') + ':' + String(min % 60).padStart(2, '0');
  }

  // Valores propostos para cada linha: uma sessão por semana a partir da data e da hora gerais; o fim é a hora de
  // início mais a duração da sessão no formato (sem duração, a hora de fim geral)
  function valores(n, linha) {
    var dia = new Date(data.value + 'T12:00:00');
    dia.setDate(dia.getDate() + 7 * n);
    var h = inicio ? inicio.value : '';
    return { data: texto(dia), inicio: h, fim: somar(h, linha.getAttribute('data-horas')) || (fim ? fim.value : '') };
  }

  // Preenche as linhas do curso; sem "forcar", só mexe nos campos vazios ou propostos antes (o que foi escrito à mão fica)
  function propor(forcar) {
    var bloco = ativo();
    if (!bloco || !data || !data.value) {
      return;
    }
    bloco.querySelectorAll('tr[data-linha-curso]').forEach(function (linha, n) {
      var v = valores(n, linha);
      linha.querySelectorAll('input[data-campo]').forEach(function (c) {
        if (forcar || c.value === '' || c.dataset.proposta === '1') {
          c.value = v[c.getAttribute('data-campo')];
          c.dataset.proposta = '1';
        }
      });
    });
  }

  // Ao abrir (ou depois de um erro), os campos que seguem o padrão semanal contam como propostos
  function marcar() {
    var bloco = ativo();
    if (!bloco || !data || !data.value) {
      return;
    }
    bloco.querySelectorAll('tr[data-linha-curso]').forEach(function (linha, n) {
      var v = valores(n, linha);
      linha.querySelectorAll('input[data-campo]').forEach(function (c) {
        if (c.value !== '' && c.value === v[c.getAttribute('data-campo')]) {
          c.dataset.proposta = '1';
        }
      });
    });
  }

  seletor.addEventListener('change', function () { mostrar(true); });
  [data, inicio, fim].forEach(function (campo) {
    if (campo) {
      campo.addEventListener('change', function () { propor(false); });
    }
  });
  document.addEventListener('input', function (e) {
    if (e.target.closest && e.target.closest('.sessoes-curso')) {
      e.target.dataset.proposta = '';
    }
  });
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-preencher-sessoes]')) {
      propor(true);
    }
  });
  mostrar(false);
})();

// Tabelas editáveis (preços e modalidades): "Acrescentar linha" junta à tabela uma linha em branco feita a partir do modelo
// do formulário (sem JavaScript, o botão grava e a página volta com uma linha em branco)
(function () {
  var n = 0;
  document.querySelectorAll('[data-acrescentar]').forEach(function (botao) {
    var formulario = botao.closest('form');
    var modelo = formulario && formulario.querySelector('template[data-modelo]');
    var tabela = formulario && formulario.querySelector('.tabela-editavel');
    if (!modelo || !tabela) {
      return;
    }
    botao.addEventListener('click', function (e) {
      e.preventDefault();
      n++;
      var corpo = tabela.tBodies[0];
      corpo.insertAdjacentHTML('beforeend', modelo.innerHTML.replace(/__N__/g, 'js' + n));
      var campo = corpo.lastElementChild.querySelector('input[type="text"]');
      campo.scrollIntoView({ block: 'center' });
      campo.focus();
    });
  });
})();

// Listas com a opção "Outra" (por exemplo, a antecedência do lembrete automático): o campo para escrever o valor só
// aparece com essa opção escolhida. Sem JavaScript, o campo fica visível quando o valor gravado não está na lista.
(function () {
  document.querySelectorAll("[data-outra-escolha]").forEach(function (lista) {
    var form = lista.closest("form");
    var campo = form && form.querySelector("[data-outra-campo]");
    if (!campo) return;
    function aplicar() {
      var outra = lista.value === "outra";
      campo.hidden = !outra;
      var entrada = campo.querySelector("input");
      if (entrada) entrada.required = outra;
    }
    lista.addEventListener("change", aplicar);
    aplicar();
  });
})();

// Listas com filtros e ordenação (tabelas com data-filtros, por exemplo Clientes, Utilizadores e Diagnósticos): uma linha
// de filtros por baixo dos cabeçalhos (texto ou lista, conforme data-filtro="lista" no cabeçalho), ordenação ao clicar no
// cabeçalho (ascendente e descendente) e o botão "Colunas" para mostrar ou esconder colunas. O valor de ordenação de uma
// célula pode vir em data-valor (datas, números). As colunas escondidas continuam a contar para os filtros e para a
// ordenação, e a escolha fica guardada neste navegador, pelo nome da tabela (valor de data-filtros) e das colunas. Sem JavaScript, a tabela aparece completa, pela ordem do servidor.
(function () {
  var tabelas = document.querySelectorAll('table[data-filtros]');
  if (!tabelas.length) {
    return;
  }
  var semAcentos = function (t) {
    return (t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
  };
  var comparar = new Intl.Collator('pt-PT', { numeric: true, sensitivity: 'base' }).compare;

  tabelas.forEach(function (tabela, t) {
    var cabecalhos = Array.prototype.slice.call(tabela.querySelectorAll('thead tr:first-child th'));
    var corpo = tabela.tBodies[0];
    var linhas = Array.prototype.slice.call(corpo.rows);
    var original = linhas.slice();
    var valor = function (linha, i) {
      var c = linha.cells[i];
      if (!c) {
        return '';
      }
      if (c.hasAttribute('data-valor')) {
        return c.getAttribute('data-valor');
      }
      // Datas portuguesas (19/11/2026, 19/11/2026 10:30) ordenam-se pela data, sem data-valor
      var d = c.textContent.trim().match(/^(\d{2})\/(\d{2})\/(\d{4})(?:[ ,]+(\d{2}):(\d{2}))?/);
      return d ? d[3] + '-' + d[2] + '-' + d[1] + ' ' + (d[4] || '00') + ':' + (d[5] || '00') + c.textContent : c.textContent;
    };
    var texto = function (linha, i) {
      return linha.cells[i] ? semAcentos(linha.cells[i].textContent) : '';
    };

    // Linha dos filtros
    var filtros = document.createElement('tr');
    filtros.className = 'linha-filtros';
    var campos = cabecalhos.map(function (th, i) {
      var nome = th.textContent.trim();
      var celula = document.createElement('th');
      var campo;
      // Colunas sem filtro nem ordenação (por exemplo, a das caixas de escolha)
      if (th.hasAttribute('data-sem-filtro')) {
        filtros.appendChild(celula);
        return null;
      }
      if (th.getAttribute('data-filtro') === 'lista') {
        campo = document.createElement('select');
        var todas = document.createElement('option');
        todas.value = '';
        todas.textContent = gmT('Todos', 'All');
        campo.appendChild(todas);
        var vistos = {};
        linhas.map(function (l) { return l.cells[i] ? l.cells[i].textContent.trim() : ''; })
          .filter(function (v) { if (vistos[v]) { return false; } vistos[v] = true; return true; })
          .sort(comparar)
          .forEach(function (v) {
            var o = document.createElement('option');
            o.value = v;
            o.textContent = v === '' ? gmT('(vazio)', '(empty)') : v;
            campo.appendChild(o);
          });
      } else {
        campo = document.createElement('input');
        campo.type = 'search';
        campo.placeholder = gmT('Filtrar', 'Filter');
      }
      campo.setAttribute('aria-label', gmT('Filtrar por ', 'Filter by ') + nome.toLowerCase());
      campo.addEventListener('input', aplicar);
      // Numa tabela dentro de um formulário, Enter no filtro não envia o formulário
      campo.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
        }
      });
      celula.appendChild(campo);
      filtros.appendChild(celula);
      return campo;
    });
    tabela.tHead.appendChild(filtros);

    // Contador e "Limpar filtros", por cima da tabela
    var barra = document.createElement('div');
    barra.className = 'barra-filtros';
    var contador = document.createElement('p');
    contador.className = 'texto-suave';
    contador.setAttribute('aria-live', 'polite');
    var limpar = document.createElement('button');
    limpar.type = 'button';
    limpar.className = 'botao botao-secundario botao-pequeno';
    limpar.textContent = gmT('Limpar filtros', 'Clear filters');
    limpar.hidden = true;
    limpar.addEventListener('click', function () {
      campos.forEach(function (c) { if (c) { c.value = ''; } });
      aplicar();
    });
    // Colunas visíveis: uma caixa por coluna (a das caixas de escolha fica sempre); fica pelo menos uma coluna à vista
    var chave = 'gm-colunas2:' + (new URLSearchParams(location.search).get('p') || location.pathname) + ':' + (tabela.getAttribute('data-filtros') || String(t));
    var ocultas = [];
    try {
      ocultas = JSON.parse(window.localStorage.getItem(chave) || '[]');
    } catch (e) {
      ocultas = [];
    }
    if (!Array.isArray(ocultas)) {
      ocultas = [];
    }
    var menu = document.createElement('details');
    menu.className = 'colunas-menu';
    var resumo = document.createElement('summary');
    resumo.className = 'botao botao-secundario botao-pequeno';
    resumo.textContent = gmT('Colunas', 'Columns');
    menu.appendChild(resumo);
    var listaColunas = document.createElement('div');
    listaColunas.className = 'colunas-lista';
    listaColunas.setAttribute('role', 'group');
    listaColunas.setAttribute('aria-label', gmT('Colunas visíveis', 'Visible columns'));
    var caixasColunas = [];

    function mostrarColuna(i, mostrar) {
      var celulas = [cabecalhos[i], filtros.cells[i]];
      linhas.forEach(function (l) { celulas.push(l.cells[i]); });
      celulas.forEach(function (c) {
        if (c) {
          c.classList.toggle('col-oculta', !mostrar);
        }
      });
    }

    function atualizarColunas() {
      ocultas = caixasColunas.filter(function (x) { return !x.caixa.checked; }).map(function (x) { return x.nome; });
      var visiveis = caixasColunas.filter(function (x) { return x.caixa.checked; });
      caixasColunas.forEach(function (x) { x.caixa.disabled = visiveis.length === 1 && x.caixa.checked; });
      resumo.textContent = ocultas.length ? gmT('Colunas (' + ocultas.length + ' escondida' + (ocultas.length > 1 ? 's' : '') + ')', 'Columns (' + ocultas.length + ' hidden)') : gmT('Colunas', 'Columns');
      try {
        window.localStorage.setItem(chave, JSON.stringify(ocultas));
      } catch (e) {
        // sem armazenamento local, a escolha vale só nesta página
      }
    }

    cabecalhos.forEach(function (th, i) {
      if (th.hasAttribute('data-sem-filtro')) {
        return;
      }
      var rotulo = document.createElement('label');
      var caixa = document.createElement('input');
      caixa.type = 'checkbox';
      var nome = th.textContent.trim();
      caixa.checked = ocultas.indexOf(nome) === -1;
      caixa.addEventListener('change', function () {
        mostrarColuna(i, caixa.checked);
        atualizarColunas();
      });
      rotulo.appendChild(caixa);
      rotulo.appendChild(document.createTextNode(' ' + th.textContent.trim()));
      listaColunas.appendChild(rotulo);
      caixasColunas.push({ i: i, nome: nome, caixa: caixa });
    });
    // Nunca esconder todas: se a escolha guardada as esconder, mostram-se todas
    if (caixasColunas.length && caixasColunas.every(function (x) { return !x.caixa.checked; })) {
      caixasColunas.forEach(function (x) { x.caixa.checked = true; });
    }
    var mostrarTodas = document.createElement('button');
    mostrarTodas.type = 'button';
    mostrarTodas.className = 'colunas-todas';
    mostrarTodas.textContent = gmT('Mostrar todas', 'Show all');
    mostrarTodas.addEventListener('click', function () {
      caixasColunas.forEach(function (x) {
        x.caixa.checked = true;
        mostrarColuna(x.i, true);
      });
      atualizarColunas();
    });
    listaColunas.appendChild(mostrarTodas);
    menu.appendChild(listaColunas);
    document.addEventListener('click', function (e) {
      if (menu.open && !menu.contains(e.target)) {
        menu.open = false;
      }
    });
    menu.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && menu.open) {
        menu.open = false;
        resumo.focus();
      }
    });

    var acoesBarra = document.createElement('div');
    acoesBarra.className = 'barra-filtros-acoes';
    acoesBarra.appendChild(limpar);
    acoesBarra.appendChild(menu);
    barra.appendChild(contador);
    barra.appendChild(acoesBarra);
    var envolvente = tabela.closest('.tabela-rolante') || tabela;
    envolvente.parentNode.insertBefore(barra, envolvente);

    function aplicar() {
      var ativos = 0;
      var visiveis = 0;
      linhas.forEach(function (linha) {
        var mostra = campos.every(function (campo, i) {
          if (!campo || campo.value === '') {
            return true;
          }
          return campo.tagName === 'SELECT'
            ? (linha.cells[i] ? linha.cells[i].textContent.trim() : '') === campo.value
            : texto(linha, i).indexOf(semAcentos(campo.value)) !== -1;
        });
        linha.hidden = !mostra;
        visiveis += mostra ? 1 : 0;
      });
      campos.forEach(function (c) { ativos += c && c.value !== '' ? 1 : 0; });
      contador.textContent = ativos ? visiveis + gmT(' de ', ' of ') + linhas.length + gmT(' registos', ' records') : linhas.length + gmT(' registos', ' records');
      limpar.hidden = !ativos;
    }

    // Ordenação: clicar no cabeçalho ordena por essa coluna; de novo, inverte; à terceira, volta à ordem inicial
    cabecalhos.forEach(function (th, i) {
      if (th.hasAttribute('data-sem-filtro')) {
        return;
      }
      var botao = document.createElement('button');
      botao.type = 'button';
      botao.className = 'ordenar';
      while (th.firstChild) {
        botao.appendChild(th.firstChild);
      }
      th.appendChild(botao);
      botao.addEventListener('click', function () {
        var atual = th.getAttribute('aria-sort');
        var proximo = atual === 'ascending' ? 'descending' : atual === 'descending' ? null : 'ascending';
        cabecalhos.forEach(function (outro) { outro.removeAttribute('aria-sort'); });
        if (proximo) {
          th.setAttribute('aria-sort', proximo);
          linhas.sort(function (a, b) {
            var va = valor(a, i).trim();
            var vb = valor(b, i).trim();
            if (va === '' || vb === '') {
              return va === vb ? 0 : va === '' ? 1 : -1; // vazios sempre no fim
            }
            return proximo === 'ascending' ? comparar(va, vb) : comparar(vb, va);
          });
        } else {
          linhas = original.slice();
        }
        linhas.forEach(function (l) { corpo.appendChild(l); });
      });
    });
    // "Escolher todos": marca ou desmarca as caixas das linhas visíveis (as escondidas pelos filtros ficam como estão)
    var todos = tabela.querySelector('thead input.marcar-todos');
    if (todos) {
      todos.addEventListener('change', function () {
        linhas.forEach(function (l) {
          var caixa = l.querySelector('input[type=checkbox]');
          if (caixa && !l.hidden) {
            caixa.checked = todos.checked;
          }
        });
      });
    }
    tabela.setAttribute('data-tabela', String(t));
    caixasColunas.forEach(function (x) { mostrarColuna(x.i, x.caixa.checked); });
    atualizarColunas();
    aplicar();
  });
})();

// Formulários com data-confirmar: pedem confirmação antes de enviar
document.addEventListener('submit', function (e) {
  var f = e.target;
  if (f.getAttribute && f.getAttribute('data-confirmar') && !window.confirm(f.getAttribute('data-confirmar'))) {
    e.preventDefault();
  }
});

// Materiais: o ficheiro é enviado em partes de 1,5 MB (abaixo dos limites habituais do alojamento), com a barra de
// progresso; o servidor junta as partes e regista o material na última. Sem JavaScript, o formulário envia o ficheiro inteiro.
(function () {
  var PARTE = 1536 * 1024;
  document.querySelectorAll('form[data-carregar-material]').forEach(function (form) {
    var entrada = form.querySelector('input[type=file]');
    var caixa = form.querySelector('.carregar-progresso');
    var barra = caixa.querySelector('progress');
    var texto = caixa.querySelector('span');
    var botao = form.querySelector('button[type=submit]');
    form.addEventListener('submit', function (e) {
      var ficheiro = entrada.files && entrada.files[0];
      if (!ficheiro || !window.fetch || !window.FormData) {
        return;
      }
      e.preventDefault();
      var envio = Array.from(crypto.getRandomValues(new Uint8Array(8))).map(function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
      var partes = Math.max(1, Math.ceil(ficheiro.size / PARTE));
      caixa.hidden = false;
      botao.disabled = true;
      var enviar = function (i) {
        var dados = new FormData(form);
        dados.delete('ficheiro');
        dados.append('ficheiro', ficheiro.slice(i * PARTE, (i + 1) * PARTE), 'parte');
        dados.append('js', '1');
        dados.append('envio', envio);
        dados.append('parte', String(i));
        dados.append('partes', String(partes));
        dados.append('nome', ficheiro.name);
        dados.append('tamanho', String(ficheiro.size));
        texto.textContent = gmT('A enviar ', 'Uploading ') + Math.round(100 * i / partes) + '%';
        return fetch(form.action || window.location.href, { method: 'POST', body: dados, credentials: 'same-origin' })
          .then(function (r) { return r.json().catch(function () { return { ok: false, erro: gmT('Resposta inválida do servidor (código ', 'Invalid server response (code ') + r.status + ').' }; }); })
          .then(function (r) {
            if (!r.ok) {
              throw new Error(r.erro || gmT('Falha no envio.', 'Upload failed.'));
            }
            barra.value = Math.round(100 * (i + 1) / partes);
            if (i + 1 < partes) {
              return enviar(i + 1);
            }
            texto.textContent = gmT('Concluído.', 'Done.');
            window.location.hash = 'materiais';
            window.location.reload();
          });
      };
      enviar(0).catch(function (erro) {
        texto.textContent = erro.message;
        botao.disabled = false;
      });
    });
  });
})();

// Diagnóstico de IA: com "Nunca usei" na primeira pergunta, as perguntas sobre a forma como usa a IA (data-so-quem-usa)
// ficam escondidas e deixam de ser obrigatórias. Sem JavaScript aparecem todas; o servidor ignora-as nesse caso.
(function () {
  document.querySelectorAll("[data-frequencia-ia]").forEach(function (primeira) {
    var form = primeira.closest("form");
    if (!form) return;
    var aviso = primeira.querySelector("[data-nunca-usou]");
    function aplicar() {
      var marcado = primeira.querySelector("input:checked");
      var nunca = !!marcado && marcado.value === "0";
      if (aviso) aviso.hidden = !nunca;
      form.querySelectorAll("[data-so-quem-usa]").forEach(function (bloco) {
        bloco.hidden = nunca;
        bloco.querySelectorAll("input").forEach(function (campo) {
          campo.disabled = nunca;
        });
      });
    }
    primeira.addEventListener("change", aplicar);
    aplicar();
  });
})();
