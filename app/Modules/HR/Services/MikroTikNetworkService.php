<?php

namespace App\Modules\HR\Services;

class MikroTikNetworkService
{
    public function diagnostics(): array
    {
        return [
            'status' => 'not_configured',
            'eligible_for_attendance' => false,
            'reason' => 'RouterOS version, topology and authenticated TLS management access require verification.',
        ];
    }
}
