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

  window.matchMedia("(min-width: 1141px)").addEventListener("change", function (e) {
    if (e.matches) fechar();
  });
})();
