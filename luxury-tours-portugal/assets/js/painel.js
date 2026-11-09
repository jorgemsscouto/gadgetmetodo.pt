/* Painel do inquérito Luxury Tours Portugal: confirmações e cópia das ligações pessoais. */
(function () {
  'use strict';
  document.querySelectorAll('form[data-confirmar]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirmar'))) e.preventDefault();
    });
  });
  document.querySelectorAll('.ligacao-pessoal').forEach(function (i) {
    i.addEventListener('focus', function () { i.select(); });
    i.addEventListener('click', function () {
      i.select();
      if (navigator.clipboard) {
        navigator.clipboard.writeText(i.value).then(function () {
          i.classList.add('copiada');
          setTimeout(function () { i.classList.remove('copiada'); }, 1200);
        }).catch(function () { /* sem permissão: a ligação fica selecionada para copiar à mão */ });
      }
    });
  });
})();

/* Editor do inquérito: mostra só os campos próprios do tipo de resposta escolhido ([data-para="tipo1 tipo2"]). */
(function () {
  'use strict';
  var tipo = document.querySelector('select[data-tipo]');
  if (!tipo) return;
  function aplicar() {
    document.querySelectorAll('[data-para]').forEach(function (n) {
      var mostrar = n.getAttribute('data-para').split(' ').indexOf(tipo.value) !== -1;
      n.hidden = !mostrar;
      n.querySelectorAll('input, select').forEach(function (c) { c.disabled = !mostrar; });
    });
  }
  tipo.addEventListener('change', aplicar);
  aplicar();
})();
