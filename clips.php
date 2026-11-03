<?php
/*
 * ENVÍO DE CLIPS
 * El vídeo viaja directamente del navegador del visitante a Cloudflare R2, por partes,
 * sin pasar por Hostinger. Este archivo solo:
 *   1. firma las subidas (sin dar nunca la clave secreta al navegador),
 *   2. cierra cada subida cuando termina,
 *   3. guarda los datos del envío junto a los vídeos y avisa por correo a info@dashcams.es.
 *
 * Necesita el archivo de configuración «clips-config.php» FUERA de public_html
 * (una carpeta por encima), con este contenido:
 *
 *   <?php return [
 *     'cuenta'    => 'ID de cuenta de Cloudflare',
 *     'clave'     => 'Access Key ID del token de R2',
 *     'secreto'   => 'Secret Access Key del token de R2',
 *     'bucket'    => 'clips-dashcams',
 *     'firma'     => 'una frase larga e inventada cualquiera',
 *     'correo'    => 'info@dashcams.es',
 *     'ue'        => true,   // si el bucket se creó con jurisdicción UE (recomendado)
 *   ];
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

const MAX_BYTES    = 4294967296;      // 4 GB por vídeo
const MAX_ARCHIVOS = 3;               // vídeos por envío
const PARTE        = 16777216;        // 16 MB por parte (4 GB = 256 partes)
const EXTENSIONES  = ['mp4', 'mov', 'avi', 'mkv', 'ts', 'mts', 'm2ts', 'm4v', '3gp', 'webm'];
const CADUCIDAD    = 43200;           // las firmas de subida valen 12 horas
const ENLACE_DIAS  = 7;               // los enlaces de descarga del correo valen 7 días

function salir(array $datos, int $codigo = 200): void {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fallo(string $mensaje, int $codigo = 400): void { salir(['ok' => false, 'error' => $mensaje], $codigo); }

/* ---------- Configuración ---------- */
$CFG = null;
foreach ([__DIR__ . '/../clips-config.php', __DIR__ . '/clips-config.php'] as $f) {
    if (is_file($f)) { $CFG = require $f; break; }
}
if (!is_array($CFG) || empty($CFG['clave']) || empty($CFG['secreto']) || empty($CFG['bucket']) || empty($CFG['firma'])
    || (empty($CFG['cuenta']) && empty($CFG['endpoint']))) {
    fallo('El envío de clips todavía no está configurado. Escríbenos a info@dashcams.es.', 503);
}
$ENDPOINT  = rtrim($CFG['endpoint'] ?? ('https://' . $CFG['cuenta'] . (!empty($CFG['ue']) ? '.eu' : '') . '.r2.cloudflarestorage.com'), '/');
$REGION    = $CFG['region'] ?? 'auto';
$DESTINO   = $CFG['correo'] ?? 'info@dashcams.es';
$REMITENTE = $CFG['remitente'] ?? 'info@dashcams.es';
$u = parse_url($ENDPOINT);
$HOST = $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');

/* ---------- Firma S3 (AWS Signature V4), la que usa Cloudflare R2 ---------- */
function ruta(string $clave): string {
    global $CFG;
    return '/' . rawurlencode($CFG['bucket']) . '/' . implode('/', array_map('rawurlencode', explode('/', $clave)));
}
function consulta(array $q): string {
    $p = [];
    foreach ($q as $k => $v) $p[rawurlencode((string) $k)] = rawurlencode((string) $v);
    ksort($p, SORT_STRING);
    $o = [];
    foreach ($p as $k => $v) $o[] = $k . '=' . $v;
    return implode('&', $o);
}
function llave(string $dia): string {
    global $CFG, $REGION;
    $k = hash_hmac('sha256', $dia, 'AWS4' . $CFG['secreto'], true);
    $k = hash_hmac('sha256', $REGION, $k, true);
    $k = hash_hmac('sha256', 's3', $k, true);
    return hash_hmac('sha256', 'aws4_request', $k, true);
}
/* URL firmada para que el navegador suba o descargue sin conocer la clave */
function prefirmar(string $metodo, string $clave, array $q = [], int $expira = CADUCIDAD, ?int $ahora = null): string {
    global $CFG, $REGION, $HOST, $ENDPOINT;
    $t = gmdate('Ymd\THis\Z', $ahora ?? time());
    $dia = substr($t, 0, 8);
    $ambito = "$dia/$REGION/s3/aws4_request";
    $q += [
        'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
        'X-Amz-Credential'    => $CFG['clave'] . '/' . $ambito,
        'X-Amz-Date'          => $t,
        'X-Amz-Expires'       => (string) $expira,
        'X-Amz-SignedHeaders' => 'host',
    ];
    $cq = consulta($q);
    $canonica = "$metodo\n" . ruta($clave) . "\n$cq\nhost:$HOST\n\nhost\nUNSIGNED-PAYLOAD";
    $texto = "AWS4-HMAC-SHA256\n$t\n$ambito\n" . hash('sha256', $canonica);
    return $ENDPOINT . ruta($clave) . '?' . $cq . '&X-Amz-Signature=' . hash_hmac('sha256', $texto, llave($dia));
}
/* Petición firmada desde el servidor (crear, cerrar o cancelar subidas, guardar el resumen) */
function peticion(string $metodo, string $clave, array $q = [], string $cuerpo = '', array $extra = []): array {
    global $CFG, $REGION, $HOST, $ENDPOINT;
    $t = gmdate('Ymd\THis\Z');
    $dia = substr($t, 0, 8);
    $ambito = "$dia/$REGION/s3/aws4_request";
    $hash = hash('sha256', $cuerpo);
    $cab = ['host' => $HOST, 'x-amz-content-sha256' => $hash, 'x-amz-date' => $t];
    foreach ($extra as $k => $v) $cab[strtolower($k)] = trim($v);
    ksort($cab, SORT_STRING);
    $lineas = '';
    foreach ($cab as $k => $v) $lineas .= $k . ':' . $v . "\n";
    $firmadas = implode(';', array_keys($cab));
    $cq = consulta($q);
    $canonica = "$metodo\n" . ruta($clave) . "\n$cq\n$lineas\n$firmadas\n$hash";
    $texto = "AWS4-HMAC-SHA256\n$t\n$ambito\n" . hash('sha256', $canonica);
    $firma = hash_hmac('sha256', $texto, llave($dia));
    $http = ['Authorization: AWS4-HMAC-SHA256 Credential=' . $CFG['clave'] . "/$ambito, SignedHeaders=$firmadas, Signature=$firma"];
    foreach ($cab as $k => $v) if ($k !== 'host') $http[] = "$k: $v";
    $http[] = 'Expect:';
    $ch = curl_init($ENDPOINT . ruta($clave) . ($cq !== '' ? '?' . $cq : ''));
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_HTTPHEADER     => $http,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_NOBODY         => $metodo === 'HEAD',
    ]);
    if ($cuerpo !== '' || $metodo === 'POST' || $metodo === 'PUT') curl_setopt($ch, CURLOPT_POSTFIELDS, $cuerpo);
    $resp = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tam = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($ch);
    return [$codigo, is_string($resp) ? $resp : '', $tam];
}

/* ---------- Utilidades ---------- */
function ficha(string $texto): string { global $CFG; return hash_hmac('sha256', $texto, $CFG['firma']); }
function texto($v, int $max = 2000): string {
    $v = trim(str_replace("\r", '', (string) $v));
    return mb_strlen($v) > $max ? mb_substr($v, 0, $max) : $v;
}
function nombre_archivo(string $n): string {
    $ext = strtolower(pathinfo($n, PATHINFO_EXTENSION));
    $base = pathinfo($n, PATHINFO_FILENAME);
    $base = strtr($base, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N','ü'=>'u','Ü'=>'U','ç'=>'c','Ç'=>'C']);
    $base = trim(preg_replace('/[^A-Za-z0-9._-]+/', '-', $base), '-.');
    if ($base === '') $base = 'video';
    return substr($base, 0, 60) . '.' . $ext;
}
function limite(string $tipo, int $max, int $segundos): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
    $f = sys_get_temp_dir() . '/dashcams_clips_' . $tipo . '_' . md5($ip);
    $ahora = time();
    $m = is_file($f) ? array_filter(array_map('intval', explode(',', (string) file_get_contents($f))), function ($t) use ($ahora, $segundos) { return $t > $ahora - $segundos; }) : [];
    if (count($m) >= $max) fallo('Has hecho demasiados envíos seguidos. Espera un rato y vuelve a intentarlo.', 429);
    $m[] = $ahora;
    @file_put_contents($f, implode(',', $m));
}
function mb_tam(int $b): string { return $b >= 1073741824 ? number_format($b / 1073741824, 2, ',', '.') . ' GB' : number_format($b / 1048576, 1, ',', '.') . ' MB'; }

/* ---------- Entrada ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') fallo('Método no permitido.', 405);
$E = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($E)) fallo('Petición no válida.');
if (!empty($E['web'])) salir(['ok' => true]);              // campo trampa para robots
$accion = $E['accion'] ?? '';

/* 1. Empezar la subida de un vídeo: devuelve una URL firmada por cada parte */
if ($accion === 'iniciar') {
    limite('iniciar', 12, 3600);
    $nombre = (string) ($E['archivo'] ?? '');
    $tam = (int) ($E['tam'] ?? 0);
    $num = (int) ($E['num'] ?? 0);
    $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    if (!in_array($ext, EXTENSIONES, true)) fallo('Ese formato no está admitido. Envía el vídeo en MP4, MOV, AVI, MKV o TS.');
    if ($tam <= 0 || $tam > MAX_BYTES) fallo('Cada vídeo puede ocupar como máximo 4 GB.');
    if ($num < 0 || $num >= MAX_ARCHIVOS) fallo('Puedes enviar como máximo ' . MAX_ARCHIVOS . ' vídeos a la vez.');
    $envio = (string) ($E['envio'] ?? '');
    if ($envio === '') $envio = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
    elseif (!preg_match('/^\d{8}-\d{6}-[0-9a-f]{6}$/', $envio) || !hash_equals(ficha('envio|' . $envio), (string) ($E['fe'] ?? ''))) fallo('Envío no válido.');
    $clave = 'clips/' . $envio . '/' . ($num + 1) . '-' . nombre_archivo($nombre);
    $tipos = ['mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'mov' => 'video/quicktime', 'webm' => 'video/webm', 'mkv' => 'video/x-matroska', 'avi' => 'video/x-msvideo', '3gp' => 'video/3gpp'];
    [$c, $r] = peticion('POST', $clave, ['uploads' => ''], '', ['content-type' => $tipos[$ext] ?? 'application/octet-stream']);
    if ($c !== 200 || !preg_match('#<UploadId>([^<]+)</UploadId>#', $r, $m)) fallo('No hemos podido preparar la subida. Inténtalo de nuevo en unos minutos.', 502);
    $id = html_entity_decode($m[1], ENT_QUOTES | ENT_XML1);
    $partes = (int) ceil($tam / PARTE);
    $urls = [];
    for ($i = 1; $i <= $partes; $i++) $urls[] = prefirmar('PUT', $clave, ['partNumber' => (string) $i, 'uploadId' => $id]);
    salir(['ok' => true, 'envio' => $envio, 'fe' => ficha('envio|' . $envio), 'clave' => $clave, 'id' => $id,
           'ficha' => ficha($clave . '|' . $id), 'parte' => PARTE, 'urls' => $urls]);
}

/* 2. Cerrar la subida de un vídeo cuando han llegado todas las partes */
if ($accion === 'completar' || $accion === 'cancelar') {
    $clave = (string) ($E['clave'] ?? '');
    $id = (string) ($E['id'] ?? '');
    if (!preg_match('#^clips/\d{8}-\d{6}-[0-9a-f]{6}/\d-[A-Za-z0-9._-]+$#', $clave) || !hash_equals(ficha($clave . '|' . $id), (string) ($E['ficha'] ?? ''))) fallo('Subida no válida.');
    if ($accion === 'cancelar') { peticion('DELETE', $clave, ['uploadId' => $id]); salir(['ok' => true]); }
    $partes = $E['partes'] ?? [];
    if (!is_array($partes) || !$partes || count($partes) > 10000) fallo('Faltan partes del vídeo.');
    usort($partes, function ($a, $b) { return ((int) $a['n']) <=> ((int) $b['n']); });
    $xml = '<CompleteMultipartUpload>';
    foreach ($partes as $p) {
        $etag = trim((string) ($p['etag'] ?? ''), "\" \t");
        if (!preg_match('/^[A-Za-z0-9-]{1,80}$/', $etag)) fallo('Faltan partes del vídeo.');
        $xml .= '<Part><PartNumber>' . (int) $p['n'] . '</PartNumber><ETag>"' . $etag . '"</ETag></Part>';
    }
    $xml .= '</CompleteMultipartUpload>';
    [$c, $r] = peticion('POST', $clave, ['uploadId' => $id], $xml, ['content-type' => 'application/xml']);
    if ($c !== 200 || strpos($r, '<Error>') !== false) fallo('No hemos podido terminar de guardar el vídeo. Vuelve a intentarlo.', 502);
    salir(['ok' => true]);
}

/* 3. Terminar el envío: guardar los datos junto a los vídeos y avisar por correo */
if ($accion === 'terminar') {
    limite('terminar', 6, 3600);
    $envio = (string) ($E['envio'] ?? '');
    if (!preg_match('/^\d{8}-\d{6}-[0-9a-f]{6}$/', $envio) || !hash_equals(ficha('envio|' . $envio), (string) ($E['fe'] ?? ''))) fallo('Envío no válido.');
    foreach (['autor', 'cesion', 'privacidad', 'edad'] as $k) if (empty($E[$k])) fallo('Tienes que aceptar las condiciones de envío y la política de privacidad.');
    $d = [
        'Nombre'                 => texto($E['nombre'] ?? '', 120),
        'Correo'                 => texto($E['correo'] ?? '', 160),
        'Cómo quiere aparecer'   => texto($E['aparecer'] ?? '', 160),
        'Fecha del incidente'    => texto($E['fecha'] ?? '', 40),
        'Lugar'                  => texto($E['lugar'] ?? '', 200),
        'Qué pasó'               => texto($E['descripcion'] ?? '', 3000),
    ];
    if ($d['Nombre'] === '' || !filter_var($d['Correo'], FILTER_VALIDATE_EMAIL)) fallo('Revisa tu nombre y tu correo.');
    $archivos = $E['archivos'] ?? [];
    if (!is_array($archivos) || !$archivos || count($archivos) > MAX_ARCHIVOS) fallo('No ha llegado ningún vídeo.');
    $lista = [];
    foreach ($archivos as $a) {
        $clave = (string) ($a['clave'] ?? '');
        if (strpos($clave, 'clips/' . $envio . '/') !== 0) fallo('Envío no válido.');
        [$c, , $tam] = peticion('HEAD', $clave);
        if ($c !== 200) fallo('Uno de los vídeos no ha terminado de subirse. Vuelve a intentarlo.', 409);
        $lista[] = ['clave' => $clave, 'nombre' => texto($a['nombre'] ?? '', 200), 'tam' => $tam];
    }
    $resumen = $d + [
        'Envío'      => $envio,
        'Fecha de envío (UTC)' => gmdate('Y-m-d H:i:s'),
        'IP'         => $_SERVER['REMOTE_ADDR'] ?? '',
        'Acepta'     => 'Es autor o tiene permiso del autor; cede los derechos según las condiciones de envío; acepta la política de privacidad; es mayor de edad.',
        'Vídeos'     => $lista,
    ];
    peticion('PUT', 'clips/' . $envio . '/envio.json', [], json_encode($resumen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ['content-type' => 'application/json']);

    $limpio = function ($t) { return trim(preg_replace('/[\r\n]+/', ' ', $t)); };
    $cuerpo = "Nuevo clip enviado desde dashcams.es\n\n";
    foreach ($d as $k => $v) if ($v !== '') $cuerpo .= "$k: $v\n";
    $cuerpo .= "\nVídeos (los enlaces de descarga caducan en " . ENLACE_DIAS . " días; los archivos siguen en Cloudflare R2, carpeta clips/$envio/):\n";
    foreach ($lista as $a) {
        $cuerpo .= '- ' . $a['nombre'] . ' (' . mb_tam($a['tam']) . "):\n  " . prefirmar('GET', $a['clave'], [], ENLACE_DIAS * 86400) . "\n";
    }
    $cuerpo .= "\nEl remitente ha aceptado las condiciones de envío (cesión de derechos) y la política de privacidad, y declara ser mayor de edad y autor del vídeo o tener permiso del autor.\n";
    $cuerpo .= "Fecha de envío (UTC): " . $resumen['Fecha de envío (UTC)'] . " · IP: " . $resumen['IP'] . "\n";
    $asunto = '=?UTF-8?B?' . base64_encode('[Clip] ' . $limpio($d['Nombre']) . ($d['Lugar'] !== '' ? ' · ' . $limpio(mb_substr($d['Lugar'], 0, 60)) : '')) . '?=';
    $cab  = 'From: =?UTF-8?B?' . base64_encode('Web Dashcams España') . "?= <{$REMITENTE}>\r\n";
    $cab .= 'Reply-To: ' . $limpio($d['Correo']) . "\r\n";
    $cab .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
    @mail($DESTINO, $asunto, $cuerpo, $cab, '-f' . $REMITENTE);
    salir(['ok' => true]);
}

fallo('Acción no válida.');
