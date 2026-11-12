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

// Listas com filtros e ordenação (tabelas com data-filtros, por exemplo Clientes e Utilizadores): uma linha de filtros por
// baixo dos cabeçalhos (texto ou lista, conforme data-filtro="lista" no cabeçalho) e ordenação ao clicar no cabeçalho
// (ascendente e descendente). O valor de ordenação de uma célula pode vir em data-valor (datas, números). Sem JavaScript,
// a tabela aparece completa, pela ordem do servidor.
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
      campos.forEach(function (c) { c.value = ''; });
      aplicar();
    });
    barra.appendChild(contador);
    barra.appendChild(limpar);
    var envolvente = tabela.closest('.tabela-rolante') || tabela;
    envolvente.parentNode.insertBefore(barra, envolvente);

    function aplicar() {
      var ativos = 0;
      var visiveis = 0;
      linhas.forEach(function (linha) {
        var mostra = campos.every(function (campo, i) {
          if (campo.value === '') {
            return true;
          }
          return campo.tagName === 'SELECT'
            ? (linha.cells[i] ? linha.cells[i].textContent.trim() : '') === campo.value
            : texto(linha, i).indexOf(semAcentos(campo.value)) !== -1;
        });
        linha.hidden = !mostra;
        visiveis += mostra ? 1 : 0;
      });
      campos.forEach(function (c) { ativos += c.value !== '' ? 1 : 0; });
      contador.textContent = ativos ? visiveis + ' de ' + linhas.length + ' registos' : linhas.length + ' registos';
      limpar.hidden = !ativos;
    }

    // Ordenação: clicar no cabeçalho ordena por essa coluna; de novo, inverte; à terceira, volta à ordem inicial
    cabecalhos.forEach(function (th, i) {
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
    tabela.setAttribute('data-tabela', String(t));
    aplicar();
  });
})();
