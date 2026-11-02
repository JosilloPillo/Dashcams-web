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

## Pendiente
1. **Publicar:** el código está en GitHub (repositorio `JosilloPillo/Dashcams-web`, rama `main`). Falta activar GitHub Pages y, al final, conectar Hostinger o el dominio (ver README).
3. **Vídeo de «¿Qué es una dashcam?»:** el texto habla de un vídeo, pero ni dashcams.es lo tiene. Falta el enlace.
4. **Imágenes:** fotos de las dashcams y miniaturas de los fondos de Descargas (hacen falta los archivos).
5. **Comprobar el plan de Hostinger:** Git solo funciona con alojamiento web, no con el creador de webs.
6. Sin ficha en Amazon.es: Thinkware U1000 Plus y Q1000, BlackVue DR970X Plus, DR770X y DR590X 2CH, Nextbase 222, 70mai A400 y Lite, DDPAI Z50, Miofive 4K, Rexing V1GW-4K, Redtiger F77, AZDOME M550 3CH, NEXPOW, WolfBox y Viofo VS1.
7. Huecos «—» que quedan en modelos poco conocidos; análisis de la N5 dice 4K (Amazon: 2,7K).
8. Opcional: suscripción por correo y formularios que lleguen solos al correo (posible al estar en Hostinger).
9. Opcional: alojar las fuentes en la propia web en vez de Google Fonts (más privacidad).

## Decisiones tomadas
- Las gamas (económica/media/alta/premium) son estimaciones por rango de precio, no precios exactos.
- No se incluyen datos que no se hayan podido confirmar: se muestran como «—».
- El logo se muestra sin recuadro ni fondo.
