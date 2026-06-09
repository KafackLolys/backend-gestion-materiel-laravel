<?php
// app/Notifications/NewLoanRequestNotification.php

namespace App\Notifications;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewLoanRequestNotification extends Notification
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
            'message' => "Nouvelle demande d'emprunt de {$this->loan->user->first_name} {$this->loan->user->last_name}",
            'loan_id' => $this->loan->id,
            'projector' => $this->loan->projector?->marque,
            'user_name' => $this->loan->user->first_name . ' ' . $this->loan->user->last_name,
            'loan_date' => $this->loan->loan_date,
            'type' => 'new_loan_request',
        ];
    }

    public function toArray($notifiable)
    {
        return $this->toDatabase($notifiable);
    }
}