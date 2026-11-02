<?php
/*
 * Recibe los formularios de la web (Contacto, Llavero e Instagram)
 * y los envía por correo a info@dashcams.es.
 * Funciona en el alojamiento de Hostinger (PHP con mail()).
 * En GitHub Pages no se ejecuta: allí la web prepara el correo en la app del visitante.
 */

$DESTINO   = 'info@dashcams.es';
$REMITENTE = 'info@dashcams.es';   // debe ser un buzón del dominio
$TIPOS     = ['Contacto', 'Llavero', 'Mejores Amigos de Instagram'];

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function responder($ok, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode(['ok' => $ok]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(false, 405);

// Campo trampa: los humanos no lo ven; si viene relleno es un robot.
if (!empty($_POST['web'])) responder(true);

$tipo = isset($_POST['_tipo']) ? trim($_POST['_tipo']) : '';
if (!in_array($tipo, $TIPOS, true)) responder(false, 400);

// Límite sencillo: 5 envíos cada 10 minutos por IP.
$ip      = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
$archivo = sys_get_temp_dir() . '/dashcams_form_' . md5($ip);
$ahora   = time();
$marcas  = is_file($archivo) ? array_filter(array_map('intval', explode(',', (string) file_get_contents($archivo))), function ($t) use ($ahora) { return $t > $ahora - 600; }) : [];
if (count($marcas) >= 5) responder(false, 429);
$marcas[] = $ahora;
@file_put_contents($archivo, implode(',', $marcas));

// Recoger campos (PHP cambia los espacios de los nombres por «_»).
$lineas = [];
$nombre = '';
$correo = '';
foreach ($_POST as $clave => $valor) {
    if ($clave === 'web' || $clave === '_tipo' || is_array($valor)) continue;
    $etiqueta = str_replace('_', ' ', $clave);
    $valor    = trim(str_replace("\r", '', (string) $valor));
    if (mb_strlen($valor) > 5000) $valor = mb_substr($valor, 0, 5000);
    if ($valor === '') continue;
    if ($nombre === '' && stripos($etiqueta, 'Nombre') === 0) $nombre = $valor;
    if ($etiqueta === 'Correo') $correo = $valor;
    $lineas[] = $etiqueta . ': ' . $valor;
}
if (!$lineas || $nombre === '') responder(false, 400);

$limpio  = function ($t) { return trim(preg_replace('/[\r\n]+/', ' ', $t)); };
$asunto  = '=?UTF-8?B?' . base64_encode('[' . $tipo . '] ' . $limpio($nombre)) . '?=';
$cuerpo  = implode("\n", $lineas) . "\n\n--\nEnviado desde el formulario de dashcams.es";

$cabeceras  = 'From: =?UTF-8?B?' . base64_encode('Web Dashcams España') . "?= <{$REMITENTE}>\r\n";
if ($correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $cabeceras .= 'Reply-To: ' . $limpio($correo) . "\r\n";
}
$cabeceras .= "MIME-Version: 1.0\r\n";
$cabeceras .= "Content-Type: text/plain; charset=UTF-8\r\n";
$cabeceras .= "Content-Transfer-Encoding: 8bit\r\n";

responder(@mail($DESTINO, $asunto, $cuerpo, $cabeceras, '-f' . $REMITENTE) ? true : false, 200);
