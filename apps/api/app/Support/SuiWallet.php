<?php

namespace App\Support;

class SuiWallet
{
    /**
     * Verifies a Sui "personal message" signature made by an ordinary Ed25519 wallet.
     * Uses PHP's built-in libsodium. Passkey and zkLogin wallets are not supported yet.
     */
    public static function verifyPersonalMessage(string $message, string $signatureB64, string $claimedAddress): bool
    {
        $raw = base64_decode($signatureB64, true);
        if ($raw === false || strlen($raw) !== 97 || ord($raw[0]) !== 0) { // flag 0 = Ed25519, then 64-byte sig + 32-byte key
            return false;
        }
        $sig = substr($raw, 1, 64);
        $pub = substr($raw, 65, 32);

        $digest = sodium_crypto_generichash("\x03\x00\x00" . self::uleb128(strlen($message)) . $message, '', 32);
        try {
            if (! sodium_crypto_sign_verify_detached($sig, $digest, $pub)) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        $address = '0x' . bin2hex(sodium_crypto_generichash("\x00" . $pub, '', 32));

        return hash_equals(strtolower($address), strtolower($claimedAddress));
    }

    private static function uleb128(int $n): string
    {
        $out = '';
        do {
            $byte = $n & 0x7F;
            $n >>= 7;
            $out .= chr($n ? ($byte | 0x80) : $byte);
        } while ($n);

        return $out;
    }
}
