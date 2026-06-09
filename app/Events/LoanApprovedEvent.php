<?php
// app/Events/LoanApprovedEvent.php

namespace App\Events;

use App\Models\Loan;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanApprovedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $loan;

    public function __construct(Loan $loan)
    {
        $this->loan = $loan;
    }

    public function broadcastOn()
    {
        return new Channel('notifications');
    }

    public function broadcastAs()
    {
        return 'loan-approved';
    }

    public function broadcastWith()
    {
        return [
            'message' => "Votre demande d'emprunt pour le projecteur {$this->loan->projector?->marque} a été approuvée.",
            'loan_id' => $this->loan->id,
            'projector' => $this->loan->projector?->marque,
        ];
    }
}