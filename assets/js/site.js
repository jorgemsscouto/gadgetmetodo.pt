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

// Leitor de áudio (página do Manual do Formando): se o ficheiro não carregar, mostra um aviso em vez do leitor vazio.
(function () {
  document.querySelectorAll(".audio-leitor").forEach(function (audio) {
    var aviso = audio.parentNode.querySelector(".audio-erro");
    if (!aviso) return;
    function falhou() { aviso.hidden = false; }
    audio.addEventListener("error", falhou);
    if (audio.error) falhou();
  });
})();

// Movimento nas caixas: brilho que segue o rato e entrada suave ao aparecerem no ecrã.
// Desligado com "reduzir movimento"; sem este ficheiro, as caixas aparecem normalmente.
(function () {
  var reduzir = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var caixas = document.querySelectorAll(".cartao, .atalho, .cartao-curso, .caso, .destaque-formacoes");
  if (!caixas.length || reduzir) return;

  Array.prototype.forEach.call(caixas, function (caixa) {
    caixa.classList.add("efeito-luz");
  });

  document.addEventListener("pointermove", function (e) {
    var caixa = e.target && e.target.closest ? e.target.closest(".efeito-luz") : null;
    if (!caixa) return;
    var r = caixa.getBoundingClientRect();
    caixa.style.setProperty("--mx", Math.round(e.clientX - r.left) + "px");
    caixa.style.setProperty("--my", Math.round(e.clientY - r.top) + "px");
  }, { passive: true });

  if (!("IntersectionObserver" in window)) return;
  var observador = new IntersectionObserver(function (entradas) {
    entradas.forEach(function (entrada) {
      if (!entrada.isIntersecting) return;
      entrada.target.classList.add("visivel");
      observador.unobserve(entrada.target);
    });
  }, { rootMargin: "0px 0px -40px 0px", threshold: 0.08 });

  Array.prototype.forEach.call(caixas, function (caixa) {
    // Pequeno desfasamento entre caixas da mesma grelha, para entrarem em sequência
    var irmaos = caixa.parentElement ? Array.prototype.indexOf.call(caixa.parentElement.children, caixa) : 0;
    caixa.style.transitionDelay = Math.min(irmaos, 5) * 70 + "ms";
    caixa.classList.add("revelar");
    observador.observe(caixa);
    // Depois de entrar, o atraso deixa de se aplicar às animações do rato
    caixa.addEventListener("transitionend", function limpar(ev) {
      if (ev.propertyName !== "opacity") return;
      caixa.style.transitionDelay = "";
      caixa.removeEventListener("transitionend", limpar);
    });
  });
})();
