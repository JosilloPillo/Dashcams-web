<?php
/*
 * NEWSLETTER (alta con doble confirmación)
 *   1. POST desde el formulario de la web: comprueba el correo y manda un enlace de confirmación.
 *   2. GET ?confirmar=...: si el enlace es bueno y no ha caducado, da de alta el correo en Sender
 *      (grupo «Newsletter web») y redirige a /newsletter?alta=ok.
 * Aquí no se guarda ningún correo: la lista vive solo en Sender.
 *
 * Necesita «newsletter-config.php» FUERA de public_html (una carpeta por encima):
 *
 *   <?php return [
 *     'token'     => 'API access token de Sender',
 *     'grupo'     => 'ID del grupo «Newsletter web» en Sender',
 *     'firma'     => 'una frase larga e inventada cualquiera',
 *     'remitente' => 'newsletter@dashcams.es',   // buzón del dominio
 *   ];
 */

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

const CADUCA = 7 * 86400;   // el enlace de confirmación vale 7 días

$CFG = null;
foreach ([__DIR__ . '/../newsletter-config.php', __DIR__ . '/newsletter-config.php'] as $f) {
    if (is_file($f)) { $CFG = require $f; break; }
}
$LISTA = is_array($CFG) && !empty($CFG['token']) && !empty($CFG['grupo']) && !empty($CFG['firma']);
$REMITENTE = $CFG['remitente'] ?? 'newsletter@dashcams.es';

function json(array $d, int $c = 200): void {
    http_response_code($c);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function ir(string $estado): void { header('Location: /newsletter?alta=' . $estado, true, 303); exit; }
function b64u(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function deb64u(string $s): string { return (string) base64_decode(strtr($s, '-_', '+/')); }
function ficha(string $txt): string { global $CFG; return substr(b64u(hash_hmac('sha256', $txt, $CFG['firma'], true)), 0, 32); }
function limite(int $max, int $segundos): void {
    $f = sys_get_temp_dir() . '/dashcams_news_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
    $ahora = time();
    $m = is_file($f) ? array_filter(array_map('intval', explode(',', (string) file_get_contents($f))), function ($t) use ($ahora, $segundos) { return $t > $ahora - $segundos; }) : [];
    if (count($m) >= $max) json(['ok' => false, 'error' => 'Demasiados intentos seguidos. Prueba dentro de un rato.'], 429);
    $m[] = $ahora;
    @file_put_contents($f, implode(',', $m));
}
function base(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . preg_replace('/[^A-Za-z0-9.:-]/', '', $_SERVER['HTTP_HOST'] ?? 'dashcams.es');
}

/* ---------- 2. Enlace de confirmación ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['confirmar'])) {
    if (!$LISTA) ir('error');
    $correo = deb64u((string) $_GET['confirmar']);
    $t = (int) ($_GET['t'] ?? 0);
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || $t < time() - CADUCA || $t > time() + 60
        || !hash_equals(ficha('alta|' . $correo . '|' . $t), (string) ($_GET['f'] ?? ''))) ir('caducado');
    $ch = curl_init('https://api.sender.net/v2/subscribers');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $CFG['token'], 'Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['email' => $correo, 'groups' => [$CFG['grupo']], 'trigger_automation' => true]),
    ]);
    $r = curl_exec($ch);
    $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $d = json_decode((string) $r, true);
    if ($c >= 200 && $c < 300 && !empty($d['success'])) ir('ok');
    if (stripos((string) $r, 'already') !== false || stripos((string) $r, 'exist') !== false) ir('ok');
    error_log('newsletter: Sender respondió ' . $c . ' ' . substr((string) $r, 0, 300));
    ir('error');
}

/* ---------- 1. Formulario ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') ir('error');
if (!$LISTA) json(['ok' => false, 'error' => 'La newsletter todavía no está lista. Vuelve a intentarlo en unos días.'], 503);
if (!empty($_POST['web'])) json(['ok' => true]);   // trampa para robots: fingimos que ha ido bien
$correo = trim((string) ($_POST['correo'] ?? ''));
if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 120) json(['ok' => false, 'error' => 'Revisa el correo: no parece válido.'], 400);
if (empty($_POST['privacidad'])) json(['ok' => false, 'error' => 'Tienes que aceptar la política de privacidad.'], 400);
limite(5, 3600);

$t = time();
$enlace = base() . '/newsletter.php?confirmar=' . b64u($correo) . '&t=' . $t . '&f=' . ficha('alta|' . $correo . '|' . $t);
$cuerpo = "¡Hola!\n\n"
    . "Alguien (esperamos que tú) ha pedido apuntarse a la newsletter de Dashcams España con este correo.\n\n"
    . "Para confirmarlo, abre este enlace (vale 7 días):\n" . $enlace . "\n\n"
    . "Una vez al mes te contaremos lo nuevo del blog, lo mejor del canal, novedades de la app y descuentos de la tienda. "
    . "Puedes darte de baja cuando quieras desde cualquier correo.\n\n"
    . "Si no has sido tú, ignora este mensaje: sin confirmar no te apuntamos a nada.\n\n"
    . "Jose, de Dashcams España\nhttps://dashcams.es\n";
$cab  = 'From: =?UTF-8?B?' . base64_encode('Dashcams España') . "?= <{$REMITENTE}>\r\n";
$cab .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
$asunto = '=?UTF-8?B?' . base64_encode('Confirma tu suscripción a la newsletter de Dashcams España') . '?=';
if (!@mail($correo, $asunto, $cuerpo, $cab, '-f' . $REMITENTE)) json(['ok' => false, 'error' => 'No hemos podido enviarte el correo de confirmación. Prueba otra vez en un rato.'], 500);
json(['ok' => true]);
