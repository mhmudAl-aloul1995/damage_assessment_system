<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function open(DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable_id === auth()->id(), 403);

        $notification->markAsRead();

        $actionUrl = data_get($notification->data, 'action_url');

        return redirect()->to(is_string($actionUrl) && $actionUrl !== '' ? $actionUrl : route('dashboard'));
    }
}
