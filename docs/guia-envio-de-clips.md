# Envío de clips: cómo activarlo

La página **Envía tu clip** (`#enviar-clip`) sube los vídeos directamente desde el navegador del visitante a **Cloudflare R2**. No pasan por Hostinger, así que no gastan espacio ni tienen el límite de subida del hosting. Cuando termina un envío, te llega un correo a tuclip@dashcams.es con los datos y un enlace por vídeo para verlo o descargarlo (vale 10 días).

Solo funciona en el alojamiento PHP/HTML de Hostinger (dashcams.es o la dirección provisional `lime-emu-605845.hostingersite.com`), porque necesita `clips.php`. La web hecha con el Creador de Hostinger no ejecuta PHP. En GitHub Pages o en la vista previa sale un aviso y el botón queda desactivado.

## 1. Activar R2 en Cloudflare

1. Crea una cuenta gratuita en https://dash.cloudflare.com (no hace falta mover el dominio a Cloudflare).
2. En el menú, entra en **R2 Object Storage** y actívalo. Te pedirá una tarjeta, pero no cobra mientras no pases del nivel gratuito: 10 GB guardados al mes. A partir de ahí cuesta unos 0,015 $ por GB y mes, y descargar los vídeos es gratis.

## 2. Crear el bucket (la «carpeta» de los clips)

1. **Create bucket** → nombre: `clips-dashcams`.
2. En **Location**, elige **Specify jurisdiction** → **European Union (EU)**. Es importante: la política de privacidad dice que los vídeos se guardan en la UE.
3. Créalo.

## 3. Permitir que la web suba vídeos (CORS)

En el bucket → **Settings** → **CORS Policy** → **Add CORS policy** → pestaña **JSON**, pega esto y guarda:

```json
[
  {
    "AllowedOrigins": ["https://dashcams.es", "https://www.dashcams.es", "https://lime-emu-605845.hostingersite.com"],
    "AllowedMethods": ["PUT", "GET"],
    "AllowedHeaders": ["Content-Type"],
    "ExposeHeaders": ["ETag"],
    "MaxAgeSeconds": 3600
  }
]
```

## 4. Borrado automático (para no pagar de más)

En el bucket → **Settings** → **Object lifecycle rules** → **Add rule**:

- Nombre: `borrar a los 90 días`, prefijo `clips/`, **Delete objects after 90 days**. Las condiciones de envío prometen borrar a los 90 días los vídeos que no se publiquen, así que descarga antes los que vayas a usar.
- Comprueba que también exista una regla para **abortar las subidas incompletas** (Abort incomplete multipart uploads) a los 1–7 días. Cloudflare suele crearla por defecto; si no está, añádela.

## 5. Crear la clave de acceso

1. En **R2 Object Storage** → **Manage API tokens** (o **API** → **Manage API tokens**) → **Create API token**.
2. Permisos: **Object Read & Write**. Aplicar solo al bucket `clips-dashcams`.
3. Al crearlo, Cloudflare enseña **una sola vez** el **Access Key ID** y el **Secret Access Key**. Cópialos.
4. Copia también tu **Account ID**: sale en la página de R2 y dentro de la dirección del endpoint, `https://<ACCOUNT_ID>.eu.r2.cloudflarestorage.com`.

## 6. Guardar la configuración en Hostinger

En hPanel → **Administrador de archivos**, sube un nivel por encima de `public_html` (la carpeta que contiene `public_html`, no dentro). Crea ahí un archivo llamado `clips-config.php` con esto, cambiando los valores:

```php
<?php return [
  'cuenta'  => 'TU_ACCOUNT_ID',
  'clave'   => 'TU_ACCESS_KEY_ID',
  'secreto' => 'TU_SECRET_ACCESS_KEY',
  'bucket'  => 'clips-dashcams',
  'ue'      => true,
  'firma'   => 'inventa aquí una frase larga cualquiera, por ejemplo la matrícula de tu primer coche y tu comida favorita',
];
```

**Nunca** subas este archivo a GitHub: el repositorio es público y lleva la clave secreta. Por eso va fuera de `public_html` y está en `.gitignore`.

## 7. Probar

Entra en https://dashcams.es/#enviar-clip, envía un vídeo corto y comprueba que:

- sale «¡Clip enviado!»;
- te llega el correo «Dashcam de [nombre] loc. [lugar, carretera]» a tuclip@dashcams.es (crea antes ese buzón en hPanel → Emails) con el enlace de descarga;
- en el bucket aparece la carpeta `clips/<fecha>-<código>/` con el vídeo y un `envio.json` con los datos.

## Dónde ver los clips

- En el **correo**: cada clip trae un enlace para verlo o descargarlo que vale 10 días (pasa por clips.php, que pide a Cloudflare un acceso nuevo cada vez que se abre).
- En **Cloudflare → R2 → clips-dashcams → clips/**: una carpeta por envío, con los vídeos y `envio.json` (correo, cómo quiere aparecer, lugar, carretera, descripción y lo que aceptó).

## Límites que tiene configurados

- 3 vídeos por envío, 4 GB por vídeo. Formatos MP4, MOV, AVI, MKV, TS, MTS, M2TS, M4V, 3GP y WebM.
- Cada IP puede iniciar como mucho 12 subidas y terminar 6 envíos por hora (antispam). Además tiene un campo trampa para robots.
- Las subidas van en partes de 16 MB, 3 a la vez. Si una parte falla, se reintenta hasta 4 veces.

Se cambian al principio de `clips.php`.
