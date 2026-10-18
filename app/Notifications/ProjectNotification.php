<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification du module projets envoyée aux donateurs : e-mail et centre de notifications (base de données),
 * en queue. Les sous-classes donnent le titre, le message et le lien.
 */
abstract class ProjectNotification extends Notification implements ShouldQueue
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
