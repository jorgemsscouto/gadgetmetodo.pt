// Botões "Copiar ligação" ([data-copiar]), usados na página do curso e na área de clientes.
(function () {
  function copiarTexto(texto) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(texto);
    }
    return new Promise(function (resolver, rejeitar) {
      var campo = document.createElement('textarea');
      campo.value = texto;
      campo.setAttribute('readonly', '');
      campo.style.position = 'fixed';
      campo.style.opacity = '0';
      document.body.appendChild(campo);
      campo.select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
      document.body.removeChild(campo);
      if (ok) { resolver(); } else { rejeitar(); }
    });
  }

  function mostrar(botao, texto) {
    var zona = botao.closest('.partilha, .ficha-acoes') || document;
    var estado = zona.querySelector('.estado-copia');
    if (estado) { estado.textContent = texto; }
  }

  document.addEventListener('click', function (evento) {
    var copiar = evento.target.closest('[data-copiar]');
    if (copiar) {
      var ligacao = copiar.getAttribute('data-copiar');
      copiarTexto(ligacao).then(function () {
        mostrar(copiar, 'Ligação copiada: ' + ligacao);
      }, function () {
        mostrar(copiar, 'Não foi possível copiar automaticamente. Copie esta ligação: ' + ligacao);
      });
    }
  });
})();
