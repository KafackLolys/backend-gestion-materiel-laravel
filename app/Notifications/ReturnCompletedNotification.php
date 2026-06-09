<?php
// app/Notifications/ReturnCompletedNotification.php

namespace App\Notifications;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReturnCompletedNotification extends Notification
{
    use Queueable;

    protected $loan;
    protected $user;

    public function __construct(Loan $loan, User $user)
    {
        $this->loan = $loan;
        $this->user = $user;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => "{$this->user->first_name} {$this->user->last_name} a complété l'état de retour du matériel.",
            'loan_id' => $this->loan->id,
            'projector' => $this->loan->projector?->marque,
            'user_name' => $this->user->first_name . ' ' . $this->user->last_name,
            'ended_at' => now()->toDateTimeString(),
            'type' => 'return_completed',
        ];
    }

    public function toArray($notifiable)
    {
        return $this->toDatabase($notifiable);
    }
}