<?php
class JWT {
    private static $secret = "SUPER_SECRET_KEY";

    private static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function encode($payload) {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $headerEncoded  = self::base64url_encode(json_encode($header));
        $payloadEncoded = self::base64url_encode(json_encode($payload));

        $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", self::$secret, true);
        $signatureEncoded = self::base64url_encode($signature);

        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }

    public static function decode($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;

        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;

        $validSig = self::base64url_encode(
            hash_hmac('sha256', "$headerEncoded.$payloadEncoded", self::$secret, true)
        );

        if (!hash_equals($validSig, $signatureEncoded)) return false;

        $payload = json_decode(self::base64url_decode($payloadEncoded), true);

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }

        return $payload;
    }
}
