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
$texto = "¡Hola!\n\n"
    . "Alguien (esperamos que tú) ha pedido apuntarse a la newsletter de Dashcams España con este correo.\n\n"
    . "Para confirmarlo, abre este enlace (vale 7 días):\n" . $enlace . "\n\n"
    . "Una vez al mes te contaremos lo nuevo del blog, lo mejor del canal, novedades de la app y descuentos de la tienda. "
    . "Puedes darte de baja cuando quieras desde cualquier correo.\n\n"
    . "Si no has sido tú, ignora este mensaje: sin confirmar no te apuntamos a nada.\n\n"
    . "Jose, de Dashcams España\nhttps://dashcams.es\n";
$e = htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8');
$html = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Confirma tu suscripción</title></head>'
 . '<body style="margin:0;padding:0;background:#eeedf3;">'
 . '<div style="display:none;max-height:0;overflow:hidden;">Un clic y ya estás dentro de la newsletter de Dashcams España.</div>'
 . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eeedf3;"><tr><td align="center" style="padding:24px 12px;">'
 . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#1f2147;">'
 . '<tr><td align="center" style="background:#1f2147;padding:28px 24px 24px;">'
 . '<img src="https://dashcams.es/logo-dashcams.png" width="72" height="72" alt="Dashcams España" style="display:block;border:0;border-radius:14px;">'
 . '<p style="margin:14px 0 0;font-size:13px;letter-spacing:2px;text-transform:uppercase;color:#fbb80c;font-weight:bold;">Newsletter</p>'
 . '<h1 style="margin:6px 0 0;font-size:28px;line-height:1.2;color:#ffffff;">Confirma tu suscripción</h1>'
 . '</td></tr>'
 . '<tr><td style="padding:28px 32px 8px;font-size:16px;line-height:1.6;">'
 . '<p style="margin:0 0 14px;">¡Hola! Alguien (esperamos que tú) ha pedido apuntarse a la newsletter de Dashcams España con este correo.</p>'
 . '<p style="margin:0 0 22px;">Solo falta un clic para confirmarlo:</p>'
 . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 22px;"><tr><td align="center" style="border-radius:10px;background:#fbb80c;">'
 . '<a href="' . $e . '" style="display:inline-block;padding:15px 30px;font-size:18px;font-weight:bold;color:#1f2147;text-decoration:none;border-radius:10px;">Sí, quiero apuntarme</a>'
 . '</td></tr></table>'
 . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f5fa;border-radius:10px;"><tr><td style="padding:16px 20px;font-size:15px;line-height:1.55;">'
 . '<p style="margin:0 0 8px;font-weight:bold;">Una vez al mes te llegará:</p>'
 . '<p style="margin:0;color:#55577a;">Lo mejor del canal · Nuevos artículos del blog · Primicias de la app · El calendario de vídeos · Descuentos de la tienda</p>'
 . '</td></tr></table>'
 . '<p style="margin:20px 0 0;font-size:14px;color:#6a6c8a;">El enlace vale 7 días. Si no has sido tú, ignora este correo: sin confirmar no te apuntamos a nada.</p>'
 . '<p style="margin:10px 0 0;font-size:13px;color:#6a6c8a;">¿No funciona el botón? Copia este enlace en el navegador:<br><a href="' . $e . '" style="color:#6a6c8a;word-break:break-all;">' . $e . '</a></p>'
 . '</td></tr>'
 . '<tr><td style="padding:22px 32px 26px;font-size:16px;line-height:1.6;"><p style="margin:0 0 4px;">Nos vemos en la carretera (con cuidado).</p><p style="margin:0;font-weight:bold;">Jose</p></td></tr>'
 . '<tr><td align="center" style="padding:0 32px 26px;font-size:13px;line-height:1.6;color:#6a6c8a;">'
 . '<a href="https://www.youtube.com/@DashcamsEsp" style="color:#6a6c8a;">YouTube</a> · <a href="https://www.instagram.com/dashcams.esp/" style="color:#6a6c8a;">Instagram</a> · <a href="https://www.tiktok.com/@dashcams.esp" style="color:#6a6c8a;">TikTok</a> · <a href="https://dashcams.es" style="color:#6a6c8a;">dashcams.es</a>'
 . '</td></tr></table></td></tr></table></body></html>';
$frontera = 'b_' . bin2hex(random_bytes(12));
$cuerpo = "--$frontera\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($texto))
    . "--$frontera\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
    . "--$frontera--\r\n";
$cab  = 'From: =?UTF-8?B?' . base64_encode('Jose de Dashcams España') . "?= <{$REMITENTE}>\r\n";
$cab .= "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$frontera\"\r\n";
$asunto = '=?UTF-8?B?' . base64_encode('Confirma tu suscripción a la newsletter de Dashcams España') . '?=';
if (!@mail($correo, $asunto, $cuerpo, $cab, '-f' . $REMITENTE)) json(['ok' => false, 'error' => 'No hemos podido enviarte el correo de confirmación. Prueba otra vez en un rato.'], 500);
json(['ok' => true]);
