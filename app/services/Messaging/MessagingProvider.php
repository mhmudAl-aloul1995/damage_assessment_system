<?php

declare(strict_types=1);

namespace App\services\Messaging;

use App\Models\CommitteeDecision;
use App\Models\User;

interface MessagingProvider
{
    public function sendCommitteeDecision(CommitteeDecision $decision, ?User $recipient = null): array;
}
