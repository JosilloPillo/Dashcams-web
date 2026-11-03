/*
  Genera las páginas publicables a partir de web.html (la fuente que se edita).

  Uso:  node herramientas/generar.js

  Crea:
    index.html            portada
    paginas/<ruta>.html   una página por sección y por artículo (/blog, /viofo-a329…)
    404.html              página de error
    sitemap.xml           solo con las páginas que deben salir en Google

  Cada página lleva ya escritos su título, descripción, dirección canónica, imagen para
  redes sociales, datos estructurados y el contenido visible, para que Google, WhatsApp,
  Facebook, etc. la lean sin tener que ejecutar JavaScript. El JavaScript de la web sigue
  funcionando igual encima.
*/
const http = require('http');
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const RAIZ = path.resolve(__dirname, '..');
const FUENTE = path.join(RAIZ, 'web.html');
const PUERTO = 8123;
const TIPOS = { '.webp': 'image/webp', '.png': 'image/png', '.jpg': 'image/jpeg', '.woff': 'font/woff', '.ico': 'image/x-icon', '.svg': 'image/svg+xml' };

const servidor = http.createServer((req, res) => {
  const ruta = decodeURIComponent(req.url.split('?')[0]);
  if (ruta === '/' || /^\/[a-z0-9-]+$/.test(ruta)) {
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    return res.end(fs.readFileSync(FUENTE));
  }
  const f = path.join(RAIZ, path.normalize(ruta));
  if (f.startsWith(RAIZ) && fs.existsSync(f) && fs.statSync(f).isFile()) {
    res.writeHead(200, { 'Content-Type': TIPOS[path.extname(f)] || 'application/octet-stream' });
    return res.end(fs.readFileSync(f));
  }
  res.writeHead(404); res.end('no');
});

(async () => {
  await new Promise(r => servidor.listen(PUERTO, '127.0.0.1', r));
  const nav = await chromium.launch({ args: ['--host-resolver-rules=MAP dashcams.es 127.0.0.1'] });
  const pag = await nav.newPage({ viewport: { width: 1280, height: 900 } });
  const errores = [];
  pag.on('pageerror', e => errores.push(e.message));
  // Nada de fuera: la tienda y YouTube se cargan luego en el navegador del visitante
  await pag.route(/^https?:\/\/(?!dashcams\.es:)/, r => r.abort());
  const BASE = `http://dashcams.es:${PUERTO}`;

  await pag.goto(BASE + '/');
  const vistas = await pag.evaluate(() => {
    const vs = [...new Set([...document.querySelectorAll('[data-view]')].map(e => e.getAttribute('data-view')))];
    return vs.map(v => ({ v, ruta: window.enlace(v) }));
  });

  fs.rmSync(path.join(RAIZ, 'paginas'), { recursive: true, force: true });
  fs.mkdirSync(path.join(RAIZ, 'paginas'));
  const mapa = [];
  for (const { v, ruta } of vistas) {
    await pag.goto(BASE + (v === 'no-encontrada' ? '/esta-pagina-no-existe' : ruta));
    await pag.waitForTimeout(250);
    const datos = await pag.evaluate(() => {
      // Limpia lo que el JavaScript vuelve a crear al cargar
      document.querySelectorAll('input[name="web"], .sent').forEach(e => e.remove());
      // Cada página lleva solo su contenido: nada de copias ocultas de las demás páginas
      document.querySelectorAll('[data-view]').forEach(e => { if (e.hidden) e.remove(); });
      document.querySelector('nav.top')?.classList.remove('abierto');
      const robots = document.querySelector('meta[name="robots"]');
      return {
        html: '<!DOCTYPE html>\n' + document.documentElement.outerHTML + '\n',
        indexable: !robots || !/noindex/.test(robots.content),
        canonica: document.querySelector('link[rel="canonical"]').href,
        fecha: (() => { try { const j = JSON.parse(document.getElementById('ld-pagina').textContent); return ((j['@graph'] || []).find(x => x.datePublished) || {}).datePublished; } catch (e) { return null; } })(),
        titulo: document.title
      };
    });
    // El texto de los artículos ya está en la página: no hace falta repetirlo dentro del JavaScript
    datos.html = datos.html.replace(/cuerpo:`[\s\S]*?`/g, 'cuerpo:""');
    const destino = v === 'inicio' ? 'index.html' : v === 'no-encontrada' ? '404.html' : path.join('paginas', ruta.slice(1) + '.html');
    fs.writeFileSync(path.join(RAIZ, destino), datos.html);
    if (datos.indexable && v !== 'no-encontrada') mapa.push({ loc: datos.canonica, fecha: datos.fecha });
    console.log(destino.padEnd(42), datos.indexable ? '' : '(noindex)', datos.titulo);
  }

  const hoy = new Date().toISOString().slice(0, 10);
  const xml = '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n' +
    mapa.map(u => `  <url><loc>${u.loc}</loc><lastmod>${u.fecha || hoy}</lastmod></url>`).join('\n') + '\n</urlset>\n';
  fs.writeFileSync(path.join(RAIZ, 'sitemap.xml'), xml);
  console.log(`\nsitemap.xml: ${mapa.length} páginas`);
  if (errores.length) { console.log('ERRORES DE JAVASCRIPT:', [...new Set(errores)]); process.exitCode = 1; }
  await nav.close(); servidor.close();
})();
