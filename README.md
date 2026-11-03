# Dashcams España — web

Web de comparativa de dashcams de [Dashcams España](https://www.youtube.com/@DashcamsEsp): comparador de más de 50 modelos, recomendador guiado, blog con análisis, descargas, formularios y páginas legales.

Archivos:
- `index.html`: toda la web (HTML, CSS y JavaScript dentro).
- `enviar.php`: envía los formularios a info@dashcams.es cuando la web está en Hostinger. En GitHub Pages no se usa: allí el formulario prepara el correo en la app del visitante.
- `og-image.png`: imagen que se ve al compartir la web en redes y WhatsApp.

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
5. Para que los formularios lleguen al correo, el buzón **info@dashcams.es** tiene que existir en **Emails** de hPanel (es el remitente que usa `enviar.php`).

Si dashcams.es está creada ahora con el creador de webs, Git no aparecerá en esa web: en hPanel crea una web nueva vacía (sin plantilla ni creador) con el dominio dashcams.es y conecta Git en esa web. Haz antes una copia de lo que tengas en la web actual.

### Opción C: dominio de Hostinger apuntando a GitHub Pages
Si solo tienes el dominio (o el plan del creador de webs):
1. Haz la opción A.
2. En **Settings → Pages → Custom domain** escribe `dashcams.es`.
3. En hPanel → **Dominios → DNS** de dashcams.es, sustituye los registros A de `@` por estos cuatro: `185.199.108.153`, `185.199.109.153`, `185.199.110.153`, `185.199.111.153`, y crea un CNAME `www` → `josillopillo.github.io`.
4. Cuando GitHub lo verifique, marca **Enforce HTTPS**.

## Pendiente
Ver `docs/traspaso-proyecto-dashcams.md`.
