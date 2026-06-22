<?php
// app/Notifications/LoanRejectedNotification.php

namespace App\Notifications;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LoanRejectedNotification extends Notification
{
    use Queueable;

    protected $loan;

    public function __construct(Loan $loan)
    {
        $this->loan = $loan;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'loan_rejected',
            'message' => "Votre demande d'emprunt pour le projecteur {$this->loan->projector?->marque} a été rejetée.",
            'loan_id' => $this->loan->id,
            'projector' => $this->loan->projector?->marque,
        ];
    }
}