// Gera os mapas das regiões do inquérito Luxury Tours Portugal (um SVG por região, sem texto):
// o país em tom claro, a região vinícola em destaque dentro do país e os países vizinhos em tom neutro.
// Dados (domínio público): Natural Earth via world-atlas (países) e dados/regioes-luxury-tours.geojson
// (Natural Earth 10m Admin 1: províncias e departamentos), U.S. Census Bureau via us-atlas (condado de Napa).
// Ribera del Duero e Mosel não têm limite administrativo próprio: desenha-se uma faixa ao longo do rio,
// com coordenadas aproximadas das localidades. Stellenbosch: círculo aproximado de 15 km.
// Utilização: npm run mapas-luxury-tours

import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { createRequire } from 'node:module';
import { geoMercator, geoPath, geoContains, geoCircle, geoArea } from 'd3-geo';
import { feature } from 'topojson-client';

const require = createRequire(import.meta.url);
const destino = process.argv[2] || '../luxury-tours-portugal/assets/img/mapas';
const ler = (f) => JSON.parse(readFileSync(f, 'utf8'));
const mundo50 = ler(require.resolve('world-atlas/countries-50m.json'));
const mundo10 = ler(require.resolve('world-atlas/countries-10m.json'));
const condados = ler(require.resolve('us-atlas/counties-10m.json'));
const paises50 = feature(mundo50, mundo50.objects.countries).features;
const paises10 = feature(mundo10, mundo10.objects.countries).features;
const admin1 = ler(new URL('./dados/regioes-luxury-tours.geojson', import.meta.url)).features;

const L = 320;
const A = 220;
const CORES = { mar: '#f6f1e8', vizinhos: '#e5ded1', pais: '#efe3c6', contorno: '#cdb787', regiao: '#a0711f', anel: '#6c2717' };

// Enquadramento [oeste, sul, este, norte] por país (ISO numérico do Natural Earth)
const PAISES = {
  italia: { id: '380', quadro: [5.5, 36.2, 19.2, 47.4] },
  franca: { id: '250', quadro: [-5.2, 41.2, 9.8, 51.3] },
  espanha: { id: '724', quadro: [-9.8, 35.8, 4.4, 44.0] },
  alemanha: { id: '276', quadro: [5.3, 47.1, 15.3, 55.1] },
  grecia: { id: '300', quadro: [19.2, 34.6, 28.6, 41.9] },
  eua: { id: '840', quadro: [-125, 24.2, -66.5, 49.6] },
  argentina: { id: '032', quadro: [-74, -55.5, -53, -21.5] },
  africa_sul: { id: '710', quadro: [16, -35, 33.2, -22] },
};

// Percursos aproximados dos rios (longitude, latitude de localidades ribeirinhas)
const MOSEL = [[6.37, 49.47], [6.52, 49.62], [6.64, 49.76], [6.83, 49.84], [6.92, 49.88], [7.07, 49.92], [7.12, 49.96], [7.18, 50.03], [7.17, 50.15], [7.33, 50.24], [7.59, 50.36]];
const RIBERA = [[-4.36, 41.63], [-4.12, 41.6], [-3.93, 41.7], [-3.69, 41.67], [-3.4, 41.61], [-3.2, 41.58]];

const REGIOES = {
  region_tuscany: { pais: 'italia', admin1: true },
  region_piedmont: { pais: 'italia', admin1: true },
  region_bordeaux: { pais: 'franca', admin1: true },
  region_burgundy: { pais: 'franca', admin1: true },
  region_champagne: { pais: 'franca', admin1: true },
  region_rioja: { pais: 'espanha', admin1: true },
  region_ribera: { pais: 'espanha', rio: RIBERA },
  region_mosel: { pais: 'alemanha', rio: MOSEL },
  region_santorini: { pais: 'grecia', ilha: [25.43, 36.4] },
  region_napa: { pais: 'eua', condado: '06055' },
  region_mendoza: { pais: 'argentina', admin1: true },
  region_stellenbosch: { pais: 'africa_sul', circulo: [[18.86, -33.93], 0.135] },
};

// O GeoJSON do Natural Earth segue a regra RFC 7946 (anel exterior no sentido anti-horário); o d3-geo
// espera o contrário e leria cada polígono como o mundo inteiro menos a região. Inverte-se quando for o caso.
function orientar(f) {
  if (geoArea(f) <= 2 * Math.PI) return f;
  const g = f.geometry;
  const inverter = (poligono) => poligono.map((anel) => [...anel].reverse());
  const coordinates = g.type === 'Polygon' ? inverter(g.coordinates) : g.coordinates.map(inverter);
  return { ...f, geometry: { ...g, coordinates } };
}

const arred = (d) => (d || '').replace(/-?\d+\.\d+/g, (n) => String(Math.round(Number(n) * 2) / 2));

function geometriaRegiao(regiao, def, paisAlvo) {
  if (def.admin1) {
    return { type: 'FeatureCollection', features: admin1.filter((f) => f.properties.regiao === regiao).map(orientar) };
  }
  if (def.condado) {
    const g = condados.objects.counties.geometries.find((c) => c.id === def.condado);
    return feature(condados, g);
  }
  if (def.circulo) {
    return geoCircle().center(def.circulo[0]).radius(def.circulo[1])();
  }
  if (def.ilha) {
    // A ilha é o polígono do país (10m) que contém o ponto
    const partes = paisAlvo.geometry.type === 'MultiPolygon' ? paisAlvo.geometry.coordinates : [paisAlvo.geometry.coordinates];
    const poligono = partes.find((p) => geoContains({ type: 'Polygon', coordinates: p }, def.ilha));
    return poligono ? { type: 'Polygon', coordinates: poligono } : geoCircle().center(def.ilha).radius(0.08)();
  }
  return null;
}

mkdirSync(destino, { recursive: true });
for (const [regiao, def] of Object.entries(REGIOES)) {
  const { id, quadro } = PAISES[def.pais];
  const [o, s, e, n] = quadro;
  const projecao = geoMercator()
    .fitExtent([[8, 8], [L - 8, A - 8]], { type: 'MultiPoint', coordinates: [[o, s], [e, n]] })
    .clipExtent([[0, 0], [L, A]]);
  const caminho = geoPath(projecao);
  const vizinhos = paises50.filter((p) => p.id !== id).map((p) => arred(caminho(p))).filter(Boolean).join('');
  // Contorno a 1:10 milhões só onde é preciso ver ilhas pequenas (Santorini); nos outros, 1:50 milhões
  const paisAlvo = (def.ilha ? paises10 : paises50).find((p) => p.id === id);
  const pais = arred(caminho(paisAlvo));

  let camada = '';
  const geo = geometriaRegiao(regiao, def, paisAlvo);
  let centro;
  let area = 0;
  if (geo) {
    camada = `<path d="${arred(caminho(geo))}" fill="${CORES.regiao}" stroke="${CORES.regiao}" stroke-width="0.6" stroke-linejoin="round"/>`;
    centro = caminho.centroid(geo);
    area = caminho.area(geo);
  } else {
    const linha = { type: 'LineString', coordinates: def.rio };
    camada = `<path d="${arred(caminho(linha))}" fill="none" stroke="${CORES.regiao}" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>`;
    centro = caminho.centroid(linha);
  }
  // Regiões muito pequenas para o tamanho do mapa levam um anel à volta, para se verem
  const anel = area > 0 && area < 120
    ? `<circle cx="${centro[0].toFixed(1)}" cy="${centro[1].toFixed(1)}" r="11" fill="none" stroke="${CORES.anel}" stroke-width="1.5"/>`
    : '';
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${L} ${A}" width="${L}" height="${A}">
<rect width="${L}" height="${A}" fill="${CORES.mar}"/>
<path d="${vizinhos}" fill="${CORES.vizinhos}" stroke="#ffffff" stroke-width="0.8" stroke-linejoin="round"/>
<path d="${pais}" fill="${CORES.pais}" stroke="${CORES.contorno}" stroke-width="0.8" stroke-linejoin="round"/>
${camada}${anel}
</svg>
`;
  writeFileSync(`${destino}/${regiao}.svg`, svg);
  console.log(`${regiao}.svg  ${(svg.length / 1024).toFixed(1)} KB`);
}
