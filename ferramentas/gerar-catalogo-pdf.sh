#!/bin/sh
# Catálogo de Serviços em PDF, em português e em inglês (EN-UK), a partir de ferramentas/gerar-catalogo.mjs:
# 1. gera documentos/catalogo/catalogo.html (npm run catalogo);
# 2. traduz para documentos/catalogo/catalogue-en.html com o dicionário do site (php traduzir-catalogo.php; pára se
#    faltarem frases, que se acrescentam a clientes/inc/traducoes/en.json);
# 3. imprime os dois em PDF com o Chrome sem janela (A4 horizontal, sem margens, com os fundos);
# 4. copia-os para a raiz do site (catalogo-servicos-2026.pdf e services-catalogue-2026.pdf) e para documentos/, e
#    gera a capa da versão inglesa (assets/img/catalogue-cover-en.jpg).
# Utilização (a partir da pasta ferramentas): sh gerar-catalogo-pdf.sh
set -e
cd "$(dirname "$0")"
RAIZ=$(cd .. && pwd)
CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
npm run -s catalogo
php traduzir-catalogo.php
pdf() {
  "$CHROME" --headless=new --disable-gpu --no-pdf-header-footer --virtual-time-budget=5000 --print-to-pdf="$2" "file://$RAIZ/documentos/catalogo/$1" 2>/dev/null
}
pdf catalogo.html "$RAIZ/documentos/Gadgetmetodo-Catalogo-de-Servicos-2026.pdf"
pdf catalogue-en.html "$RAIZ/documentos/Gadgetmetodo-Services-Catalogue-2026.pdf"
cp "$RAIZ/documentos/Gadgetmetodo-Catalogo-de-Servicos-2026.pdf" "$RAIZ/catalogo-servicos-2026.pdf"
cp "$RAIZ/documentos/Gadgetmetodo-Services-Catalogue-2026.pdf" "$RAIZ/services-catalogue-2026.pdf"
# Capa (960 px de largura), se houver o pdftoppm (poppler)
if command -v pdftoppm >/dev/null; then
  pdftoppm -jpeg -jpegopt quality=85 -f 1 -l 1 -scale-to-x 960 -scale-to-y -1 -singlefile "$RAIZ/services-catalogue-2026.pdf" "$RAIZ/assets/img/catalogue-cover-en"
fi
ls -l "$RAIZ/catalogo-servicos-2026.pdf" "$RAIZ/services-catalogue-2026.pdf"
