// Formulário de pedido: validação, envio sem recarregar a página e ditado por voz.
// Sem JavaScript, o formulário é enviado normalmente e validado no servidor.
(function () {
  var form = document.getElementById("pedido");
  if (!form) return;

  var estado = form.querySelector(".formulario-estado");
  var botaoEnviar = form.querySelector('button[type="submit"]');
  var mensagem = document.getElementById("f-mensagem");
  var campoTempo = form.querySelector('input[name="t"]');

  campoTempo.value = String(Math.floor(Date.now() / 1000));
  form.noValidate = true;

  /* Validação */

  var REGRAS = {
    nome: function (v) { return v.trim().length >= 2 || "Indique o seu nome."; },
    empresa: function (v) { return !paraEmpresa() || v.trim().length >= 2 || "Indique o nome da empresa."; },
    email: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()) || "Indique um email válido."; },
    telemovel: function (v) { return /^[0-9 +()\-]{9,20}$/.test(v.trim()) || "Indique um número de telemóvel válido."; },
    mensagem: function (v) { return v.trim().length >= 10 || "Descreva o pedido com pelo menos 10 caracteres."; },
  };

  // Nos pedidos de particulares o nome da empresa não é pedido
  function paraEmpresa() {
    var t = form.querySelector('input[name="tipo_cliente"]:checked');
    return !t || t.value === "empresa";
  }

  function mostrarErro(nome, texto) {
    var p = document.getElementById("e-" + nome);
    var campo = form.elements[nome];
    if (p) {
      p.textContent = texto || "";
      p.hidden = !texto;
    }
    if (campo && campo.setAttribute && !campo.length) {
      if (texto) {
        campo.setAttribute("aria-invalid", "true");
        campo.setAttribute("aria-errormessage", "e-" + nome);
      } else {
        campo.removeAttribute("aria-invalid");
      }
    }
  }

  function validar() {
    var primeiro = null;
    Object.keys(REGRAS).forEach(function (nome) {
      var r = REGRAS[nome](form.elements[nome].value);
      mostrarErro(nome, r === true ? "" : r);
      if (r !== true && !primeiro) primeiro = form.elements[nome];
    });
    var servicos = form.querySelectorAll('input[name="servicos[]"]:checked').length > 0;
    mostrarErro("servicos", servicos ? "" : "Selecione pelo menos um serviço.");
    if (!servicos && !primeiro) primeiro = form.querySelector('input[name="servicos[]"]');
    var consentimento = form.elements.consentimento.checked;
    mostrarErro("consentimento", consentimento ? "" : "É necessário aceitar para podermos responder.");
    if (!consentimento && !primeiro) primeiro = form.elements.consentimento;
    if (primeiro) primeiro.focus();
    return !primeiro;
  }

  Object.keys(REGRAS).forEach(function (nome) {
    form.elements[nome].addEventListener("blur", function () {
      if (!this.value) return;
      var r = REGRAS[nome](this.value);
      mostrarErro(nome, r === true ? "" : r);
    });
  });

  form.addEventListener("change", function (e) {
    if (e.target.name === "servicos[]" && form.querySelector('input[name="servicos[]"]:checked')) mostrarErro("servicos", "");
    if (e.target.name === "consentimento" && e.target.checked) mostrarErro("consentimento", "");
    if (e.target.name === "tipo_cliente" && !paraEmpresa()) mostrarErro("empresa", "");
  });

  /* Envio */

  function definirEstado(texto, erro) {
    estado.textContent = texto;
    estado.classList.toggle("erro", !!erro);
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    if (ditado.ativo) ditado.parar();
    definirEstado("");
    if (!validar()) return;

    botaoEnviar.disabled = true;
    definirEstado("A enviar o pedido...");

    fetch(form.action, {
      method: "POST",
      body: new FormData(form),
      headers: { Accept: "application/json" },
    })
      .then(function (r) {
        return r.json().catch(function () { return { ok: false }; });
      })
      .then(function (dados) {
        if (dados.ok) {
          var nome = form.elements.nome.value.trim().split(" ")[0];
          var email = form.elements.email.value.trim();
          var sucesso = document.createElement("div");
          sucesso.className = "sucesso-envio";
          sucesso.setAttribute("tabindex", "-1");
          var h = document.createElement("h3");
          h.textContent = "Pedido enviado. Obrigado, " + nome + ".";
          var p1 = document.createElement("p");
          p1.textContent = "Enviámos uma confirmação para " + email + (dados.referencia ? ", com a referência " + dados.referencia : "") + ".";
          var p2 = document.createElement("p");
          p2.textContent = "Um membro da nossa equipa entrará em contacto consigo brevemente.";
          sucesso.appendChild(h);
          sucesso.appendChild(p1);
          sucesso.appendChild(p2);
          form.replaceWith(sucesso);
          sucesso.focus();
          return;
        }
        if (dados.erros) {
          Object.keys(dados.erros).forEach(function (nome) { mostrarErro(nome, dados.erros[nome]); });
        }
        definirEstado(dados.mensagem || "Não foi possível enviar o pedido. Tente novamente ou escreva para info@gadgetmetodo.pt.", true);
        botaoEnviar.disabled = false;
      })
      .catch(function () {
        definirEstado("Não foi possível enviar o pedido. Verifique a ligação à internet ou escreva para info@gadgetmetodo.pt.", true);
        botaoEnviar.disabled = false;
      });
  });

  /* Ditado por voz (Web Speech API) */

  var Reconhecimento = window.SpeechRecognition || window.webkitSpeechRecognition;
  var botaoDitado = form.querySelector(".botao-ditado");
  var estadoDitado = document.getElementById("estado-ditado");
  var ditado = { ativo: false, parar: function () {} };

  if (!Reconhecimento || !botaoDitado) return;

  var rec = new Reconhecimento();
  rec.lang = "pt-PT";
  rec.continuous = true;
  rec.interimResults = true;

  var base = "";
  var rotulo = botaoDitado.querySelector("span");

  function juntar(antes, novo) {
    novo = novo.trim();
    if (!novo) return antes;
    var fimFrase = /[.!?]\s*$/.test(antes) || antes.trim() === "";
    if (fimFrase) novo = novo.charAt(0).toUpperCase() + novo.slice(1);
    return antes + (antes && !/\s$/.test(antes) ? " " : "") + novo;
  }

  function atualizarBotao() {
    botaoDitado.setAttribute("aria-pressed", ditado.ativo ? "true" : "false");
    rotulo.textContent = ditado.ativo ? "Parar ditado" : "Ditar";
  }

  ditado.parar = function () {
    ditado.ativo = false;
    atualizarBotao();
    estadoDitado.textContent = "";
    try { rec.stop(); } catch (err) { /* já parado */ }
  };

  rec.onresult = function (evento) {
    var provisorio = "";
    for (var i = evento.resultIndex; i < evento.results.length; i++) {
      var texto = evento.results[i][0].transcript;
      if (evento.results[i].isFinal) base = juntar(base, texto);
      else provisorio += texto;
    }
    mensagem.value = juntar(base, provisorio);
    mostrarErro("mensagem", "");
  };

  rec.onerror = function (evento) {
    if (evento.error === "no-speech") return;
    var textos = {
      "not-allowed": "O acesso ao microfone foi recusado. Pode autorizá-lo nas definições do navegador.",
      "service-not-allowed": "O ditado não está disponível neste navegador.",
      "audio-capture": "Não foi encontrado nenhum microfone.",
      network: "O ditado precisa de ligação à internet.",
    };
    ditado.parar();
    estadoDitado.textContent = textos[evento.error] || "O ditado foi interrompido. Pode continuar a escrever.";
  };

  rec.onend = function () {
    // O navegador termina o reconhecimento após uma pausa; retomar enquanto o ditado estiver ativo.
    if (ditado.ativo) {
      try { rec.start(); } catch (err) { ditado.parar(); }
    }
  };

  botaoDitado.addEventListener("click", function () {
    if (ditado.ativo) {
      ditado.parar();
      return;
    }
    base = mensagem.value;
    ditado.ativo = true;
    atualizarBotao();
    estadoDitado.textContent = "A ouvir. Fale normalmente; o texto aparece no campo e pode ser corrigido.";
    try { rec.start(); } catch (err) { /* já iniciado */ }
  });

  mensagem.addEventListener("input", function () {
    if (ditado.ativo) base = mensagem.value;
  });

  botaoDitado.hidden = false;
})();
