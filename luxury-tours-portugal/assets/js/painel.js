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
