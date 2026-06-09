<?php
// app/Events/ReturnCompletedEvent.php

namespace App\Events;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReturnCompletedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $loan;
    public $user;

    public function __construct(Loan $loan, User $user)
    {
        $this->loan = $loan;
        $this->user = $user;
    }

    public function broadcastOn()
    {
        // Diffusion sur le canal privé du manager
        return new Channel('notifications');
    }

    public function broadcastAs()
    {
        return 'return-completed';
    }

    public function broadcastWith()
    {
        return [
            'message' => "{$this->user->first_name} {$this->user->last_name} a complété l'état de retour du matériel.",
            'loan_id' => $this->loan->id,
            'user_name' => $this->user->first_name . ' ' . $this->user->last_name,
            'projector' => $this->loan->projector?->marque,
            'ended_at' => now()->format('d/m/Y H:i'),
        ];
    }
}