<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    protected string $code;

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Code de réinitialisation de mot de passe')
            ->greeting('Bonjour ' . $notifiable->first_name . ' ' . $notifiable->last_name . ',')
            ->line('Vous avez demandé la réinitialisation de votre mot de passe.')
            ->line('Voici votre code de réinitialisation :')
            ->line('**' . $this->code . '**')
            ->line('Ce code est valable pendant 10 minutes.')
            ->line('Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet email.')
            ->salutation('Cordialement, l\'équipe de gestion de matériel.');
    }

    public function toArray($notifiable)
    {
        return [
            'code' => $this->code,
        ];
    }
}