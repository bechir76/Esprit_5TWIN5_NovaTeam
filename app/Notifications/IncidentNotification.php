<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification du module incidents : e-mail et centre de notifications (base de données), en queue.
 * Même format que les notifications du module projets (title, message, url) : un seul centre de notifications.
 */
abstract class IncidentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract protected function title(): string;

    abstract protected function message(): string;

    abstract protected function url(): string;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title().' · AquaSecure')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($this->message())
            ->action('Voir le détail', $this->url())
            ->salutation('L\'équipe AquaSecure');
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message(),
            'url' => $this->url(),
        ];
    }
}
