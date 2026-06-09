<?php
// app/Events/NewLoanRequestEvent.php

namespace App\Events;

use App\Models\Loan;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewLoanRequestEvent implements ShouldBroadcast
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
        return 'new-loan-request';
    }

    public function broadcastWith()
    {
        return [
            'message' => "Nouvelle demande d'emprunt de {$this->loan->user->first_name} {$this->loan->user->last_name}",
            'loan_id' => $this->loan->id,
            'projector' => $this->loan->projector?->marque,
        ];
    }
}