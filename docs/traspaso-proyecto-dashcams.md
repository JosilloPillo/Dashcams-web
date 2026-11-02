# Traspaso del proyecto: web de comparativa de dashcams (España)

## Qué es
Una página web de un solo archivo HTML (`comparativa-dashcams.html`) para el proyecto Dashcams ESP. Replica y amplía la web actual https://dashcams.es/ con comparativas de dashcams, enlaces de afiliado de Amazon, su tienda de merch (https://tienda.dashcams.es/) y la divulgación del canal de YouTube (@DashcamsEsp).

Idioma: español de España. Estilo: portada con aspecto de visor de dashcam, tipografías Barlow Condensed y Public Sans, modo claro y oscuro.

## Cómo está construida
- Un solo archivo HTML con CSS y JavaScript dentro. Se publica como página alojada (artifact); no puede cargar imágenes ni peticiones de otras webs (solo scripts de cdnjs/jsdelivr y Google Fonts).
- Las «páginas» son vistas dentro del mismo archivo, con rutas por hash: `#inicio`, `#recomendaciones`, `#blog`, un hash por artículo (`#a329`, `#n5`, `#que-comprar`, `#vs1`, `#que-es`, `#instalar`) y las páginas copiadas de dashcams.es: `#descargas`, `#contacto`, `#llavero`, `#instagram`, `#sobre-nosotros`, `#privacidad`, `#aviso-legal`, `#cookies`. Cada bloque lleva `data-view="..."` y un pequeño router muestra la vista que toca.
- Publicada como artifact: https://claude.ai/artifact/1mmgA3yQNGrjG6UQHK1iGc (slug 06404205-2a57-475c-afe2-931a53e4b9ad).
- Menú arriba: logo a la izquierda (PNG incrustado en base64), enlaces a la derecha, «Contacto» como desplegable con los dos formularios.

## Los datos
En el primer `<script>` (después de los artículos): `TAG` (etiqueta de afiliado `dashcamsesp-21`), `ASIN` (código de Amazon.es por modelo), `amz()` (crea el enlace afiliado; sin ASIN, búsqueda con etiqueta), `PR` (precios) y `POSTS` (artículos del blog, con plantilla en un comentario).

En el JavaScript del final del archivo:
- `DASHCAMS`: los 9 modelos de la tabla grande (con enlaces de afiliado `amzn.to`). Cada uno puede llevar `review:"#hash"` para enlazar su reseña.
- `CMP`: lista para el comparador y el recomendador (empieza con los mismos 9 modelos).
- `NEW`: 42 modelos más, en formato compacto: `[nombre, gama(1-4), resolución(1=1080p,2=2K,3=4K), cámaras(1-3), GPS, WiFi, aparcado, voz, pantalla, discreta, ángulo, tarjeta, texto trasera, nota resolución]`. Valores: 1 = sí, .5 = opcional, 0 = no, null = sin confirmar.
- `RA`: atributos numéricos de cada modelo para el recomendador.
- `PR`: precio orientativo redondeado (revisado en Amazon.es el 2 nov 2026). No se muestran precios exactos de Amazon a propósito (normas del programa de afiliados).
- Para añadir un modelo: nueva fila en `NEW`, su precio en `PR` y su ASIN en `ASIN`. El comparador y el recomendador lo cogen solos.

## Funciones
- Tabla comparativa de 9 modelos con enlaces a Amazon y a reseñas propias.
- Comparador: dos desplegables (agrupados por marca) y tabla lado a lado con las diferencias resaltadas, precio aproximado y enlace «Ver fotos».
- Recomendador guiado de 5 pasos (uso, gama, cámaras, resolución, extras). Filtra por gama, cámaras y resolución y puntúa por uso y extras. Muestra las 3 mejores.
- Blog: 6 artículos copiados de dashcams.es, con su texto completo (A329, N5, qué dashcam comprar, VS1, qué es una dashcam, guía de instalación).

## Hecho el 2 nov 2026
- Enlaces de afiliado en todos los modelos con la etiqueta `dashcamsesp-21` (sacada de los propios enlaces amzn.to). 25 modelos van a su ficha de Amazon.es; el resto, y la VS1, a una búsqueda con etiqueta.
- Enlaces amzn.to comprobados: «70mai 1S» era la M300, «A810 4K» la A810S (solo delantera), «AZDOME 4K+1080P» la M300S y «VANTRUE N4» la N4 Pro. Tabla y comparador renombrados y corregidos; se quitaron las filas duplicadas de M300 y N4 Pro en `NEW`.
- Vantrue N5: 2,7K (1944p) delantera según Amazon.es; el texto del análisis sigue diciendo 4K.
- Precios completados y fila de precio en la tabla grande.
- Páginas copiadas: Descargas, Contacto, formulario llavero, formulario Instagram, Sobre nosotros y Política de privacidad. Los formularios preparan un correo a info@dashcams.es (con botón Copiar por si el correo no se abre).
- Blog convertido en bloque de datos `POSTS`.

- Recomendador sin «Ver fotos». Botón de modo oscuro/claro (solo icono 🌙/☀️) que recuerda la elección en el navegador.
- Vídeos: tarjeta «Ver el vídeo en YouTube» en A329 (eFf-2xmAF4M), N5 (Yx7RTv3QhK0), VS1 (3Qisdn7hP0w) e Instalar (ljR2ncPnakI), sacados de dashcams.es. En dashcams.es o *.github.io se incrustan solos con youtube-nocookie (variable `INCRUSTAR`).
- Añadidas las preguntas frecuentes de «¿Qué es una dashcam?», que faltaban.
- Aviso legal (con los datos del titular) y política de cookies, fechados el 2 nov 2026.
- SEO: descripción, Open Graph, URL canónica dashcams.es e icono (el logo).
- Carpeta del repositorio preparada (`index.html`, `README.md` con pasos para GitHub Pages y Hostinger, `.nojekyll`, `docs/`).

- Repositorio en GitHub: `JosilloPillo/Dashcams-web` (rama `main`). Aviso legal completado con los datos del titular.
- Análisis de la N5 corregido a 2,7K.
- Formularios: en dashcams.es se envían solos con `enviar.php` (PHP `mail()` de Hostinger, remitente info@dashcams.es, campo trampa anti-robots y límite de 5 envíos cada 10 min por IP). En otros sitios preparan el correo.
- `og-image.png` (1200×630) para compartir en redes.
- 14 modelos con huecos completados desde sus fichas de Amazon.es (GPS, WiFi, voz, pantalla, aparcado, ángulo, tarjeta).
- Limpieza del comparador (2 nov 2026): quedan 66 modelos. Descatalogados cambiados por su versión actual: BlackVue DR970X Plus → DR970X-2CH LTE Plus, DR770X → DR770X-2CH II, DR590X 2CH → DR590X-1CH (la de dos cámaras ya no está en Amazon.es), Nextbase 222 → 222X, AZDOME M550 → M550 Max 3CH, WolfBox espejo → WOLFBOX G840S. Quitados por descatalogados (su sucesora ya estaba en la lista) o por no venderse en Amazon.es ni AliExpress: Thinkware U1000 Plus, Thinkware Q1000, 70mai Dash Cam Lite, Miofive 4K, Rexing V1GW-4K y NEXPOW 3 canales. La 70mai A400 solo está en AliExpress: enlace en la lista `AE` (botón «Ver en AliExpress»). La DDPAI Z60 Pro ya tiene ficha en Amazon.es (B0FFRL9WQ7). La Viofo VS1 no está en Amazon.es ni AliExpress (sí en Powerplanet), pero se mantiene por tener reseña propia.
- Decisiones: los modelos sin ficha en Amazon.es se quedan con búsqueda; sin suscripción por correo de momento; Hostinger tiene plan de alojamiento web.

## Pendiente
1. **Activar GitHub Pages** (Settings → Pages → rama `main`, carpeta raíz) para ver la web en https://josillopillo.github.io/Dashcams-web/.
2. **Pasar a dashcams.es:** hPanel → web → Advanced → Git → Continue with GitHub → repositorio `Dashcams-web`, rama `main`, carpeta `public_html`. Si la web actual es del creador de webs, crear una web vacía con el dominio. El buzón info@dashcams.es debe existir para los formularios.
3. **Vídeo de «¿Qué es una dashcam?»:** el texto habla de un vídeo, pero ni dashcams.es lo tiene. Falta el enlace.
4. **Fotos de las dashcams:** las tiene que pasar Jose (propias o de prensa de las marcas). Los fondos de Descargas ya están en `img/fondos/` (originales optimizados) y `img/fondos/mini/` (miniaturas WebP).
5. Huecos «—» que quedan en modelos sin ficha en Amazon.es o con fichas incompletas.
6. «Opinión del canal»: solo hay nota en 3 modelos.
7. Opcional: alojar las fuentes en la propia web en vez de Google Fonts.

## Decisiones tomadas
- Las gamas (económica/media/alta/premium) son estimaciones por rango de precio, no precios exactos.
- No se incluyen datos que no se hayan podido confirmar: se muestran como «—».
- El logo se muestra sin recuadro ni fondo.
