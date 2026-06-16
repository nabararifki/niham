<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BulkImportSuccessfulNotification extends Notification
{
    use Queueable;

    public int $count;

    /**
     * Create a new notification instance.
     */
    public function __construct(int $count)
    {
        $this->count = $count;
    }

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
        return [
            'action'  => 'import',
            'message' => __('assets.import_success_count', ['count' => $this->count]),
        ];
    }
}
