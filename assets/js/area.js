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
