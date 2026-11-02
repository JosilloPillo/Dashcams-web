# Dashcams España — web

Web de comparativa de dashcams de [Dashcams España](https://www.youtube.com/@DashcamsEsp): comparador de 49 modelos, recomendador guiado, blog con análisis, descargas, formularios y páginas legales.

Toda la web es un solo archivo: `index.html` (HTML, CSS y JavaScript dentro).

## Dónde se edita cada cosa

| Qué | Dónde (dentro de `index.html`) |
| --- | --- |
| Etiqueta de afiliado de Amazon | `var TAG` |
| Códigos de producto de Amazon (ASIN) | `var ASIN` |
| Precios orientativos | `var PR` |
| Artículos del blog | `var POSTS` (hay una plantilla comentada encima) |
| Vídeos de los artículos | campo `video` de cada artículo en `POSTS` |
| Tabla grande de 9 modelos | `var DASHCAMS` |
| Resto de modelos | `var NEW` |
| Dominios donde se incrustan los vídeos | `var INCRUSTAR` |

## Publicar

### Opción A: GitHub Pages (gratis, para ir probando)
1. En el repositorio: **Settings → Pages**.
2. En **Source** elige **Deploy from a branch**, rama `main`, carpeta `/ (root)`, y guarda.
3. En un par de minutos la web está en `https://josillopillo.github.io/Dashcams-web/`.

### Opción B: Hostinger con despliegue automático desde GitHub
Requiere un plan de alojamiento web (Premium, Business o Cloud). No funciona con webs del creador de webs de Hostinger.
1. En hPanel: **Websites → Dashboard** de dashcams.es → **Advanced → Git**.
2. Pulsa **Continue with GitHub**, autoriza la app de Hostinger y elige este repositorio.
3. Rama `main`; carpeta de destino `public_html`.
4. Desde entonces, cada cambio que se suba a `main` se publica solo en dashcams.es.

### Opción C: dominio de Hostinger apuntando a GitHub Pages
Si solo tienes el dominio (o el plan del creador de webs):
1. Haz la opción A.
2. En **Settings → Pages → Custom domain** escribe `dashcams.es`.
3. En hPanel → **Dominios → DNS** de dashcams.es, sustituye los registros A de `@` por estos cuatro: `185.199.108.153`, `185.199.109.153`, `185.199.110.153`, `185.199.111.153`, y crea un CNAME `www` → `josillopillo.github.io`.
4. Cuando GitHub lo verifique, marca **Enforce HTTPS**.

## Pendiente
Ver `docs/traspaso-proyecto-dashcams.md`.
