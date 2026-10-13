<?php

namespace App\Modules\HR\Services;

use Illuminate\Validation\ValidationException;

class AttendanceCryptography
{
    /** @return array{pem: string, fingerprint: string} */
    public function publicKey(string $pem): array
    {
        $key = openssl_pkey_get_public($pem);
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if (! $details || $details['type'] !== OPENSSL_KEYTYPE_EC || ($details['ec']['curve_name'] ?? '') !== 'prime256v1') {
            throw ValidationException::withMessages(['public_key' => 'يلزم مفتاح عام EC P-256 صالح.']);
        }

        return ['pem' => $details['key'], 'fingerprint' => hash('sha256', $details['key'])];
    }

    public function signature(string $payload, string $encoded, string $publicKey): string
    {
        $signature = base64_decode($encoded, true);
        if ($signature === false || strlen($signature) > 128 || openssl_verify($payload, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw ValidationException::withMessages(['signature' => 'التوقيع غير صالح لهذا التحدي والمفتاح.']);
        }

        return hash('sha256', $signature);
    }

    public function payload(string $purpose, string $id, int $employeeId, array $claims, int $expires): string
    {
        return json_encode([
            'protocol' => 'PHC-HR-1', 'purpose' => $purpose, 'id' => $id,
            'employee_id' => $employeeId, 'nonce' => base64_encode(random_bytes(32)),
            'expires_at' => $expires, ...$claims,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public function attestation(array $chain, string $publicKey): void
    {
        if ($chain === []) {
            return;
        }
        $certificate = openssl_x509_read($chain[0]);
        $key = $certificate === false ? false : openssl_pkey_get_public($certificate);
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if (! $details || $details['key'] !== $publicKey) {
            throw ValidationException::withMessages(['attestation_chain' => 'شهادة المفتاح لا تطابق المفتاح المسجل.']);
        }
    }
}
