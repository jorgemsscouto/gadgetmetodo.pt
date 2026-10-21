// Gera a página de instalação das assinaturas de email, com um botão "Copiar" para cada uma.
// Fonte: documentos/assinatura-email/assinatura-completa.html e assinatura-resposta.html.
// Utilização (a partir desta pasta): node gerar-assinatura.mjs ../documentos/assinatura-email
import fs from 'fs';
import path from 'path';

const PASTA = process.argv[2];

// Extrai a tabela da assinatura (do primeiro <table> ao último </table>)
function assinatura(ficheiro) {
  const html = fs.readFileSync(path.join(PASTA, ficheiro), 'utf8');
  return html.slice(html.indexOf('<table'), html.lastIndexOf('</table>') + '</table>'.length);
}

const ASSINATURAS = [
  { id: 'completa', titulo: 'Assinatura completa', uso: 'Para mensagens novas', html: assinatura('assinatura-completa.html') },
  { id: 'resposta', titulo: 'Assinatura curta', uso: 'Para respostas e reencaminhamentos', html: assinatura('assinatura-resposta.html') },
];

const pagina = `<!doctype html>
<html lang="pt-PT">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Instalar assinatura no Outlook | Jorge Sá Couto</title>
<style>
  body { margin: 0; padding: 32px 16px 48px; background: #f5f7f8; color: #333; font: 15px/1.6 -apple-system, "Segoe UI", Arial, sans-serif; }
  main { max-width: 820px; margin: 0 auto; }
  h1 { margin: 0 0 6px; color: #0a0f26; font-size: 1.6rem; }
  h2 { margin: 0; color: #0a0f26; font-size: 1.1rem; }
  .lead { margin: 0 0 28px; color: #687280; }
  .cartao { margin-bottom: 20px; padding: 22px 24px; background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; }
  .cabeca { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 16px; }
  .cabeca p { margin: 2px 0 0; color: #687280; font-size: .9rem; }
  .moldura { padding: 20px; border: 1px dashed #c9ced6; border-radius: 10px; overflow-x: auto; }
  button { padding: 11px 18px; color: #fff; background: #00636a; font: 600 .95rem/1.2 -apple-system, "Segoe UI", Arial, sans-serif; border: 0; border-radius: 10px; cursor: pointer; }
  button:hover { background: #003f46; }
  button.feito { background: #1f7a4d; }
  .estado { min-height: 1.4em; margin: 10px 0 0; color: #1f7a4d; font-size: .9rem; }
  .estado.erro { color: #b42318; }
  ol { margin: 8px 0 0; padding-left: 1.3em; }
  li + li { margin-top: 6px; }
  .nota { color: #687280; font-size: .9rem; }
  code { padding: 1px 6px; background: #f5f7f8; border-radius: 6px; font-size: .88em; }
</style>
</head>
<body>
<main>
  <h1>Instalar a assinatura no Outlook</h1>
  <p class="lead">Carregue em "Copiar" e cole no editor de assinaturas do Outlook (Cmd+V no Mac, Ctrl+V no Windows). Não cole o código HTML: o Outlook mostraria o código em vez da assinatura.</p>
${ASSINATURAS.map(a => `
  <section class="cartao" aria-labelledby="t-${a.id}">
    <div class="cabeca">
      <div><h2 id="t-${a.id}">${a.titulo}</h2><p>${a.uso}</p></div>
      <button type="button" data-copiar="${a.id}">Copiar ${a.titulo.toLowerCase()}</button>
    </div>
    <div class="moldura"><div id="assinatura-${a.id}">${a.html}</div></div>
    <p class="estado" id="estado-${a.id}" aria-live="polite"></p>
  </section>`).join('')}

  <section class="cartao" aria-labelledby="t-passos">
    <h2 id="t-passos">Onde colar</h2>
    <ol>
      <li><strong>Outlook para Mac:</strong> Outlook, Definições, Assinaturas, botão "+". Cole, dê um nome ("Completa" ou "Resposta") e guarde. No fim, escolha a assinatura predefinida para mensagens novas e para respostas e reencaminhamentos.</li>
      <li><strong>Outlook para Windows (novo) e Outlook na Web:</strong> Definições, Contas, Assinaturas (nalgumas versões: Correio, Compor e responder). Crie uma assinatura nova, cole e defina as predefinições.</li>
      <li><strong>Outlook clássico para Windows:</strong> Ficheiro, Opções, Correio, Assinaturas, Novo. Cole no editor e defina as predefinições.</li>
    </ol>
    <p class="nota">O logótipo é carregado de <code>www.gadgetmetodo.pt</code>: se não aparecer, o site ainda não foi publicado com a versão mais recente. No Outlook clássico para Windows pode também instalar o ficheiro diretamente: copie <code>assinatura-completa.html</code> para a pasta <code>%APPDATA%\\Microsoft\\Signatures</code> com o nome <code>Jorge Sá Couto.htm</code> e reinicie o Outlook.</p>
  </section>
</main>
<script>
  // Copia a assinatura como conteúdo formatado (HTML), pronta a colar no Outlook
  async function copiar(id) {
    const el = document.getElementById('assinatura-' + id);
    const estado = document.getElementById('estado-' + id);
    let ok = false;
    try {
      if (navigator.clipboard && window.ClipboardItem) {
        await navigator.clipboard.write([new ClipboardItem({
          'text/html': new Blob([el.innerHTML], { type: 'text/html' }),
          'text/plain': new Blob([el.innerText], { type: 'text/plain' }),
        })]);
        ok = true;
      }
    } catch (e) { ok = false; }
    if (!ok) {
      // Alternativa para navegadores sem acesso à área de transferência: seleciona e copia
      const sel = window.getSelection();
      const r = document.createRange();
      r.selectNodeContents(el);
      sel.removeAllRanges();
      sel.addRange(r);
      try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
      sel.removeAllRanges();
    }
    estado.className = 'estado' + (ok ? '' : ' erro');
    estado.textContent = ok
      ? 'Copiada. Agora cole no editor de assinaturas do Outlook.'
      : 'Não foi possível copiar automaticamente. Selecione a assinatura com o rato, copie (Cmd+C ou Ctrl+C) e cole no Outlook.';
  }
  document.querySelectorAll('[data-copiar]').forEach(b => b.addEventListener('click', () => copiar(b.dataset.copiar)));
</script>
</body>
</html>
`;

fs.writeFileSync(path.join(PASTA, 'instalar-assinatura.html'), pagina);
console.log('instalar-assinatura.html gerado');
