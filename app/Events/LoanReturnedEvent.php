<?php
// app/Events/LoanReturnedEvent.php

namespace App\Events;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoanReturnedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $loan;
    public $whoReturned;

    public function __construct(Loan $loan, User $whoReturned)
    {
        $this->loan = $loan;
        $this->whoReturned = $whoReturned;
    }

    public function broadcastOn()
    {
        return new Channel('notifications');
    }

    public function broadcastAs()
    {
        return 'loan-returned';
    }

    public function broadcastWith()
    {
        return [
            'message' => "{$this->whoReturned->first_name} {$this->whoReturned->last_name} a complété le retour du matériel.",
            'loan_id' => $this->loan->id,
            'projector' => $this->loan->projector?->marque,
        ];
    }
}