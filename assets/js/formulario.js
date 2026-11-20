// Texto na língua da página: português ou, nas páginas em inglês (<html lang="en-GB">), inglês
var gmEN = document.documentElement.lang === 'en-GB';
function gmT(pt, en) { return gmEN ? en : pt; }

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
    nome: function (v) { return v.trim().length >= 2 || gmT("Indique o seu nome.", "Please enter your name."); },
    empresa: function (v) { return !paraEmpresa() || v.trim().length >= 2 || gmT("Indique o nome da empresa.", "Please enter the company name."); },
    email: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()) || gmT("Indique um email válido.", "Please enter a valid email address."); },
    telemovel: function (v) { return /^[0-9 +()\-]{9,20}$/.test(v.trim()) || gmT("Indique um número de telemóvel válido.", "Please enter a valid mobile number."); },
    mensagem: function (v) { return v.trim().length >= 10 || gmT("Descreva o pedido com pelo menos 10 caracteres.", "Please describe your request in at least 10 characters."); },
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
    mostrarErro("servicos", servicos ? "" : gmT("Selecione pelo menos um serviço.", "Please select at least one service."));
    if (!servicos && !primeiro) primeiro = form.querySelector('input[name="servicos[]"]');
    var consentimento = form.elements.consentimento.checked;
    mostrarErro("consentimento", consentimento ? "" : gmT("É necessário aceitar para podermos responder.", "You need to accept so that we can reply."));
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
    definirEstado(gmT("A enviar o pedido...", "Sending your request..."));

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
          h.textContent = gmT("Pedido enviado. Obrigado, ", "Request sent. Thank you, ") + nome + ".";
          var p1 = document.createElement("p");
          p1.textContent = gmT("Enviámos uma confirmação para ", "We have sent a confirmation to ") + email + (dados.referencia ? gmT(", com a referência ", ", with reference ") + dados.referencia : "") + ".";
          var p2 = document.createElement("p");
          p2.textContent = gmT("Um membro da nossa equipa entrará em contacto consigo brevemente.", "A member of our team will be in touch shortly.");
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
        definirEstado(dados.mensagem || gmT("Não foi possível enviar o pedido. Tente novamente ou escreva para info@gadgetmetodo.pt.", "Your request could not be sent. Please try again or write to info@gadgetmetodo.pt."), true);
        botaoEnviar.disabled = false;
      })
      .catch(function () {
        definirEstado(gmT("Não foi possível enviar o pedido. Verifique a ligação à internet ou escreva para info@gadgetmetodo.pt.", "Your request could not be sent. Please check your internet connection or write to info@gadgetmetodo.pt."), true);
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
  rec.lang = gmEN ? "en-GB" : "pt-PT";
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
    rotulo.textContent = ditado.ativo ? gmT("Parar ditado", "Stop dictation") : gmT("Ditar", "Dictate");
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
      "not-allowed": gmT("O acesso ao microfone foi recusado. Pode autorizá-lo nas definições do navegador.", "Microphone access was denied. You can allow it in your browser settings."),
      "service-not-allowed": gmT("O ditado não está disponível neste navegador.", "Dictation is not available in this browser."),
      "audio-capture": gmT("Não foi encontrado nenhum microfone.", "No microphone was found."),
      network: gmT("O ditado precisa de ligação à internet.", "Dictation needs an internet connection."),
    };
    ditado.parar();
    estadoDitado.textContent = textos[evento.error] || gmT("O ditado foi interrompido. Pode continuar a escrever.", "Dictation stopped. You can carry on typing.");
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
    estadoDitado.textContent = gmT("A ouvir. Fale normalmente; o texto aparece no campo e pode ser corrigido.", "Listening. Speak normally; the text appears in the field and can be corrected.");
    try { rec.start(); } catch (err) { /* já iniciado */ }
  });

  mensagem.addEventListener("input", function () {
    if (ditado.ativo) base = mensagem.value;
  });

  botaoDitado.hidden = false;
})();
