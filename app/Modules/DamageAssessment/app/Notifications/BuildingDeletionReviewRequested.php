<?php

namespace App\Notifications;

use App\Models\BuildingDeletionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BuildingDeletionReviewRequested extends Notification
{
    use Queueable;

    public function __construct(
        private readonly BuildingDeletionRequest $deletionRequest,
        private readonly string $stage,
        private readonly string $event = 'review_requested',
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $request = $this->deletionRequest;

        return [
            'title' => $this->title(),
            'message' => sprintf(
                '%s ObjectID: %s، طلب رقم DEL-%s.',
                $this->messagePrefix(),
                $request->building_objectid ?? '-',
                str_pad((string) $request->id, 5, '0', STR_PAD_LEFT),
            ),
            'building_deletion_request_id' => $request->id,
            'building_objectid' => $request->building_objectid,
            'building_globalid' => $request->building_globalid,
            'stage' => $this->stage,
            'event' => $this->event,
            'action_url' => route('building-deletions.show', $request),
        ];
    }

    private function title(): string
    {
        return match ($this->event) {
            'returned' => 'تم إرجاع طلب حذف مبنى',
            'rejected' => 'تم رفض طلب حذف مبنى',
            default => 'طلب حذف مبنى بانتظار موافقتك',
        };
    }

    private function messagePrefix(): string
    {
        if ($this->event === 'returned') {
            return 'تم إرجاع طلب حذف المبنى';
        }

        if ($this->event === 'rejected') {
            return 'تم رفض طلب حذف المبنى';
        }

        return match ($this->stage) {
            'team_leader' => 'يرجى مراجعة طلب حذف المبنى كقائد فريق.',
            'area_manager' => 'يرجى مراجعة طلب حذف المبنى كقائد منطقة.',
            'gis' => 'يرجى مراجعة طلب حذف المبنى كـ GIS.',
            default => 'يرجى مراجعة طلب حذف المبنى.',
        };
    }
}
