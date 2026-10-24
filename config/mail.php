<?php
// ============================================================
// ARCHIVO: farmacia/config/mail.php
// DESCRIPCIÓN: Configuración SMTP y helper sendMail()
//
// CONFIGURAR ANTES DE USAR:
//   1. MAIL_HOST     → servidor SMTP (ej: smtp.gmail.com)
//   2. MAIL_PORT     → 587 para STARTTLS (recomendado)
//   3. MAIL_USERNAME → tu dirección de correo
//   4. MAIL_PASSWORD → contraseña de aplicación
//                      (Gmail: Cuenta → Seguridad → Verificación en 2 pasos
//                       → Contraseñas de aplicación → genera una de 16 dígitos)
//   5. MAIL_FROM     → dirección remitente (normalmente igual al username)
//   6. MAIL_FROM_NAME→ nombre visible en el correo
// ============================================================

define('MAIL_HOST',      'smtp.gmail.com');
define('MAIL_PORT',      587);
define('MAIL_USERNAME',  'tu_correo@gmail.com');
define('MAIL_PASSWORD',  'xxxx xxxx xxxx xxxx');
define('MAIL_FROM',      'tu_correo@gmail.com');
define('MAIL_FROM_NAME', 'Gravity');

/**
 * Envía un correo HTML vía SMTP con STARTTLS.
 * Compatible con Gmail, Outlook y cualquier servidor SMTP en puerto 587.
 * Requiere la extensión openssl habilitada en PHP (ya activa en XAMPP).
 *
 * @param string $to       Dirección destino
 * @param string $subject  Asunto del correo
 * @param string $htmlBody Cuerpo HTML del mensaje
 * @return bool            true si el servidor aceptó el mensaje
 */
function sendMail(string $to, string $subject, string $htmlBody): bool
{
    $context = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]
    ]);

    $fp = @stream_socket_client(
        'tcp://' . MAIL_HOST . ':' . MAIL_PORT,
        $errno, $errstr, 15,
        STREAM_CLIENT_CONNECT,
        $context
    );
    if (!$fp) return false;

    stream_set_timeout($fp, 15);

    // Lee la respuesta completa (puede ser multi-línea)
    $read = static function () use ($fp): string {
        $buf = '';
        while ($line = fgets($fp, 512)) {
            $buf .= $line;
            if (isset($line[3]) && $line[3] === ' ') break; // última línea de la respuesta
        }
        return $buf;
    };

    $write = static function (string $data) use ($fp): void {
        fputs($fp, $data . "\r\n");
    };

    $read();                                      // 220 greeting

    $write('EHLO localhost');
    $read();

    $write('STARTTLS');
    $r = $read();
    if (substr($r, 0, 3) !== '220') { fclose($fp); return false; }

    stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

    $write('EHLO localhost');                     // reenviar EHLO tras TLS
    $read();

    $write('AUTH LOGIN');
    $read();
    $write(base64_encode(MAIL_USERNAME));
    $read();
    $write(base64_encode(MAIL_PASSWORD));
    $authResp = $read();
    if (substr($authResp, 0, 3) !== '235') { fclose($fp); return false; }

    $write('MAIL FROM: <' . MAIL_FROM . '>');
    $read();

    $write('RCPT TO: <' . $to . '>');
    $rcptResp = $read();
    if (substr($rcptResp, 0, 3) !== '250') { fclose($fp); return false; }

    $write('DATA');
    $read();

    $msg  = 'From: =?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?= <' . MAIL_FROM . ">\r\n";
    $msg .= 'To: ' . $to . "\r\n";
    $msg .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n";
    $msg .= "\r\n";
    $msg .= chunk_split(base64_encode($htmlBody));
    $msg .= "\r\n.";

    $write($msg);
    $dataResp = $read();

    $write('QUIT');
    fclose($fp);

    return substr(trim($dataResp), 0, 3) === '250';
}
