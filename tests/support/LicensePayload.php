<?php

declare(strict_types=1);

namespace LowWidontTests {
    final class LicensePayload
    {
        public static $captured = [];
    }
}

namespace Low\Widont\FluxCapacitor\Conduit {
    /**
     * Observe plaintext in an isolated request while retaining actual OpenSSL encryption.
     *
     * @param string $data
     * @param string|null $encrypted
     * @param mixed $key
     * @param int $padding
     * @return bool
     */
    function openssl_public_encrypt($data, &$encrypted, $key, $padding = OPENSSL_PKCS1_PADDING)
    {
        \LowWidontTests\LicensePayload::$captured[] = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        return \openssl_public_encrypt($data, $encrypted, $key, $padding);
    }
}
