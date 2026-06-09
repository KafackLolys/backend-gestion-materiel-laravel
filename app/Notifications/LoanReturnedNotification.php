<?php
// app/Notifications/LoanReturnedNotification.php

namespace App\Notifications;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LoanReturnedNotification extends Notification
{
    use Queueable;

    protected $loan;
    protected $whoReturned;

    public function __construct(Loan $loan, User $whoReturned)
    {
        $this->loan = $loan;
        $this->whoReturned = $whoReturned;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'loan_returned',
            'message' => "{$this->whoReturned->first_name} {$this->whoReturned->last_name} a complété le retour du matériel.",
            'loan_id' => $this->loan->id,
            'projector' => $this->loan->projector?->marque,
            'user_name' => $this->whoReturned->first_name . ' ' . $this->whoReturned->last_name,
        ];
    }
}