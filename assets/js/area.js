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

  // Valores propostos para cada linha: uma sessão por semana a partir da data e do horário gerais
  function valores(n) {
    var dia = new Date(data.value + 'T12:00:00');
    dia.setDate(dia.getDate() + 7 * n);
    return [texto(dia), inicio ? inicio.value : '', fim ? fim.value : ''];
  }

  // Preenche as linhas do curso; sem "forcar", só mexe nos campos vazios ou propostos antes (o que foi escrito à mão fica)
  function propor(forcar) {
    var bloco = ativo();
    if (!bloco || !data || !data.value) {
      return;
    }
    bloco.querySelectorAll('tr[data-linha-curso]').forEach(function (linha, n) {
      var v = valores(n);
      linha.querySelectorAll('input').forEach(function (c, i) {
        if (forcar || c.value === '' || c.dataset.proposta === '1') {
          c.value = v[i];
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
      var v = valores(n);
      linha.querySelectorAll('input').forEach(function (c, i) {
        if (c.value !== '' && c.value === v[i]) {
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
// ordenação, e a escolha fica guardada neste navegador. Sem JavaScript, a tabela aparece completa, pela ordem do servidor.
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
      return c ? (c.hasAttribute('data-valor') ? c.getAttribute('data-valor') : c.textContent) : '';
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
        todas.textContent = 'Todos';
        campo.appendChild(todas);
        var vistos = {};
        linhas.map(function (l) { return l.cells[i] ? l.cells[i].textContent.trim() : ''; })
          .filter(function (v) { if (vistos[v]) { return false; } vistos[v] = true; return true; })
          .sort(comparar)
          .forEach(function (v) {
            var o = document.createElement('option');
            o.value = v;
            o.textContent = v === '' ? '(vazio)' : v;
            campo.appendChild(o);
          });
      } else {
        campo = document.createElement('input');
        campo.type = 'search';
        campo.placeholder = 'Filtrar';
      }
      campo.setAttribute('aria-label', 'Filtrar por ' + nome.toLowerCase());
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
    limpar.textContent = 'Limpar filtros';
    limpar.hidden = true;
    limpar.addEventListener('click', function () {
      campos.forEach(function (c) { if (c) { c.value = ''; } });
      aplicar();
    });
    // Colunas visíveis: uma caixa por coluna (a das caixas de escolha fica sempre); fica pelo menos uma coluna à vista
    var chave = 'gm-colunas:' + (new URLSearchParams(location.search).get('p') || location.pathname) + ':' + t;
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
    resumo.textContent = 'Colunas';
    menu.appendChild(resumo);
    var listaColunas = document.createElement('div');
    listaColunas.className = 'colunas-lista';
    listaColunas.setAttribute('role', 'group');
    listaColunas.setAttribute('aria-label', 'Colunas visíveis');
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
      ocultas = caixasColunas.filter(function (x) { return !x.caixa.checked; }).map(function (x) { return x.i; });
      var visiveis = caixasColunas.filter(function (x) { return x.caixa.checked; });
      caixasColunas.forEach(function (x) { x.caixa.disabled = visiveis.length === 1 && x.caixa.checked; });
      resumo.textContent = ocultas.length ? 'Colunas (' + ocultas.length + ' escondida' + (ocultas.length > 1 ? 's' : '') + ')' : 'Colunas';
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
      caixa.checked = ocultas.indexOf(i) === -1;
      caixa.addEventListener('change', function () {
        mostrarColuna(i, caixa.checked);
        atualizarColunas();
      });
      rotulo.appendChild(caixa);
      rotulo.appendChild(document.createTextNode(' ' + th.textContent.trim()));
      listaColunas.appendChild(rotulo);
      caixasColunas.push({ i: i, caixa: caixa });
    });
    // Nunca esconder todas: se a escolha guardada as esconder, mostram-se todas
    if (caixasColunas.length && caixasColunas.every(function (x) { return !x.caixa.checked; })) {
      caixasColunas.forEach(function (x) { x.caixa.checked = true; });
    }
    var mostrarTodas = document.createElement('button');
    mostrarTodas.type = 'button';
    mostrarTodas.className = 'colunas-todas';
    mostrarTodas.textContent = 'Mostrar todas';
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
      contador.textContent = ativos ? visiveis + ' de ' + linhas.length + ' registos' : linhas.length + ' registos';
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
