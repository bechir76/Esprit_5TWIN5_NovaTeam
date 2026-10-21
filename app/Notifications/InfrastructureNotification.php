<?php

namespace App\Notifications;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification du module infrastructures : e-mail et centre de notifications (base de données), en queue.
 * Même format que les modules incidents et projets (title, message, url) : un seul centre de notifications.
 */
abstract class InfrastructureNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract protected function title(): string;

    abstract protected function message(): string;

    abstract protected function url(): string;

    /**
     * Destinataires : administrateurs et gestionnaires actifs.
     *
     * @return Collection<int, User>
     */
    public static function recipients(): Collection
    {
        return User::query()->whereIn('role', [Role::Admin->value, Role::Gestionnaire->value])->where('is_active', true)->get();
    }

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
            ->action('Voir l\'ouvrage', $this->url())
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
