<?php

namespace Oscdenox\Core\Auth\TwoFactor;

use PragmaRX\Google2FA\Google2FA;
use Oscdenox\Core\Auth\TwoFactor\Exception\TotpReplayAttackException;

/**
 * Servicio TOTP para autenticacion de doble factor en el panel admin.
 *
 * Responsabilidades:
 * - Generar secretos TOTP de 32 caracteres (160 bits de entropia)
 * - Cifrar/descifrar secretos con sodium (XSalsa20-Poly1305) — nunca en texto plano en BD
 * - Verificar codigos TOTP con ventana de tolerancia +-30s
 * - Proteger contra replay attacks (SEC-04)
 *
 * La clave de cifrado se deriva de: SECURITY_KEY (en BD) + APP_2FA_SALT (en configure.php)
 * Asi, un dump de BD solo no es suficiente para descifrar los secretos.
 */
class TotpService
{
    /** @var Google2FA */
    private Google2FA $google2fa;

    /** @var string Clave de cifrado derivada (SODIUM_CRYPTO_SECRETBOX_KEYBYTES bytes) */
    private string $encryptionKey;

    /**
     * @param string $securityKey Valor de SECURITY_KEY de la tabla configuration
     * @param string $salt        Valor de APP_2FA_SALT de includes/configure.php
     */
    public function __construct(string $securityKey, string $salt)
    {
        // Derivar clave de cifrado combinando la clave de BD con la sal de fichero de configuracion
        // hash('sha256', ..., true) devuelve 32 bytes (binario), exactamente lo que necesita sodium
        $this->encryptionKey = substr(
            hash('sha256', $securityKey . $salt, true),
            0,
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES
        );

        $this->google2fa = new Google2FA();
    }

    /**
     * Genera un secreto TOTP nuevo de 32 caracteres (v9 por defecto).
     *
     * @return string Secreto en Base32 (32 chars = 160 bits de entropia)
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Cifra un secreto TOTP en texto plano con sodium (XSalsa20-Poly1305).
     *
     * Formato almacenado: base64( nonce[24 bytes] . ciphertext )
     *
     * @param string $plainSecret Secreto TOTP en texto plano (Base32)
     * @return string Secreto cifrado en base64, listo para almacenar en BD
     */
    public function encryptSecret(string $plainSecret): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plainSecret, $nonce, $this->encryptionKey);

        return base64_encode($nonce . $ciphertext);
    }

    /**
     * Descifra un secreto TOTP almacenado en BD.
     *
     * @param string $storedEncrypted Secreto cifrado en base64 (formato: nonce . ciphertext)
     * @return string Secreto TOTP en texto plano (Base32)
     * @throws \RuntimeException Si el descifrado falla (clave incorrecta o datos corruptos)
     */
    public function decryptSecret(string $storedEncrypted): string
    {
        $decoded = base64_decode($storedEncrypted, true);
        if ($decoded === false) {
            throw new \RuntimeException('No se pudo descifrar el secreto TOTP: base64 invalido');
        }

        // Los primeros SODIUM_CRYPTO_SECRETBOX_NONCEBYTES bytes son el nonce
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->encryptionKey);

        if ($plaintext === false) {
            throw new \RuntimeException('No se pudo descifrar el secreto TOTP');
        }

        return $plaintext;
    }

    /**
     * Verifica un codigo TOTP con proteccion anti-replay (SEC-04).
     *
     * Flujo:
     * 1. Detectar replay attack: mismo codigo usado en la misma ventana temporal (90s)
     * 2. Descifrar el secreto almacenado
     * 3. Verificar el codigo con google2fa (ventana +-30s)
     *
     * @param string      $code            Codigo TOTP de 6 digitos introducido por el usuario
     * @param string      $encryptedSecret Secreto cifrado almacenado en BD
     * @param string|null $lastUsedCode    Ultimo codigo TOTP usado (para anti-replay)
     * @param string|null $lastUsedAt      Timestamp del ultimo uso (datetime string)
     * @return bool true si el codigo es valido, false si no lo es
     * @throws TotpReplayAttackException Si se detecta un intento de replay attack
     */
    public function verify(
        string $code,
        string $encryptedSecret,
        ?string $lastUsedCode,
        ?string $lastUsedAt
    ): bool {
        // Proteccion anti-replay (SEC-04): rechazar codigos ya utilizados en la misma ventana temporal
        if (
            $lastUsedCode !== null
            && $lastUsedCode === $code
            && $lastUsedAt !== null
            && (time() - strtotime($lastUsedAt)) < 90
        ) {
            throw new TotpReplayAttackException();
        }

        // Descifrar el secreto almacenado en BD
        $decryptedSecret = $this->decryptSecret($encryptedSecret);

        // Verificar el codigo TOTP (window=1 permite tolerancia de +-30 segundos)
        return (bool) $this->google2fa->verifyKey($decryptedSecret, $code, 1);
    }

    /**
     * Verifica un codigo TOTP usando el secreto en texto plano (para setup).
     *
     * Durante el setup, el secreto aun no esta cifrado en BD — esta en $_SESSION.
     * Este metodo evita el ciclo cifrar/descifrar innecesario.
     *
     * @param string $code        Codigo TOTP de 6 digitos introducido por el usuario
     * @param string $plainSecret Secreto TOTP en texto plano (Base32)
     * @return bool true si el codigo es valido
     */
    public function verifyWithPlainSecret(string $code, string $plainSecret): bool
    {
        return (bool) $this->google2fa->verifyKey($plainSecret, $code, 1);
    }

    /**
     * Genera la URI otpauth:// para configurar el autenticador.
     *
     * Esta URI se convierte en codigo QR en la Fase 3.
     * Se implementa aqui porque es logica del servicio TOTP, no de la UI.
     *
     * @param string $secret  Secreto TOTP en texto plano (Base32)
     * @param string $email   Email del miembro admin (identificador en el autenticador)
     * @param string $issuer  Nombre del emisor que aparece en Google Authenticator
     * @return string URI otpauth:// lista para generar QR
     */
    /**
     * Genera un QR inline (data:image/png;base64) listo para <img src>.
     *
     * Centraliza la generacion del QR que antes estaba duplicada en setup y setup_confirm.
     *
     * @param string $secret  Secreto TOTP en texto plano
     * @param string $email   Email del admin
     * @param string $issuer  Nombre del emisor
     * @return array{qrInline: string, manualKey: string} QR en base64 y clave formateada
     */
    public function generateQrData(string $secret, string $email, string $issuer = ''): array
    {
        if ($issuer === '') {
            $issuer = defined('STORE_NAME') ? STORE_NAME . ' Admin' : 'Admin 2FA';
        }
        $provisioningUri = $this->getProvisioningUri($secret, $email, $issuer);

        $renderer = new \BaconQrCode\Renderer\GDLibRenderer(250);
        $writer = new \BaconQrCode\Writer($renderer);
        $pngBinary = $writer->writeString($provisioningUri);

        return [
            'qrInline' => 'data:image/png;base64,' . base64_encode($pngBinary),
            'manualKey' => implode(' ', str_split($secret, 4)),
        ];
    }

    public function getProvisioningUri(
        string $secret,
        string $email,
        string $issuer = ''
    ): string {
        if ($issuer === '') {
            $issuer = defined('STORE_NAME') ? STORE_NAME . ' Admin' : 'Admin 2FA';
        }
        return $this->google2fa->getQRCodeUrl($issuer, $email, $secret);
    }
}
