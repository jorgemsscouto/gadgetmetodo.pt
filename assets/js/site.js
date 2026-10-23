// Navegação móvel. O conteúdo do site não depende deste ficheiro.
(function () {
  var botao = document.querySelector(".menu-botao");
  var nav = document.getElementById("navegacao");
  if (!botao || !nav) return;

  function fechar() {
    nav.classList.remove("aberta");
    botao.setAttribute("aria-expanded", "false");
  }

  botao.addEventListener("click", function () {
    var aberta = nav.classList.toggle("aberta");
    botao.setAttribute("aria-expanded", aberta ? "true" : "false");
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && nav.classList.contains("aberta")) {
      fechar();
      botao.focus();
    }
  });

  window.matchMedia("(min-width: 1151px)").addEventListener("change", function (e) {
    if (e.matches) fechar();
  });
})();

// Formulários com a escolha "Particular / Empresa": o nome da empresa só aparece, e só é obrigatório, para empresas.
// Sem JavaScript o campo fica sempre visível e a validação é feita no servidor.
(function () {
  document.querySelectorAll("[data-tipo-cliente]").forEach(function (grupo) {
    var form = grupo.closest("form");
    if (!form) return;
    function aplicar() {
      var marcado = form.querySelector('input[name="tipo_cliente"]:checked');
      var empresa = !!marcado && marcado.value === "empresa";
      form.querySelectorAll("[data-so-empresa]").forEach(function (bloco) {
        bloco.hidden = !empresa;
        bloco.querySelectorAll("input").forEach(function (campo) {
          campo.disabled = !empresa;
          campo.required = empresa;
        });
      });
    }
    grupo.addEventListener("change", aplicar);
    aplicar();
  });
})();
