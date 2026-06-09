<?php
// app/Services/NotificationService.php

namespace App\Services;

use App\Models\Loan;
use App\Models\User;
use App\Events\LoanApprovedEvent;
use App\Events\LoanReturnedEvent;
use App\Events\NewLoanRequestEvent;
use App\Notifications\LoanApprovedNotification;
use App\Notifications\LoanReturnedNotification;
use App\Notifications\NewLoanRequestNotification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Notifier les managers/admins d'une nouvelle demande d'emprunt
     */
    public static function newLoanRequest(Loan $loan): void
    {
        $managers = User::whereIn('role', ['manager', 'admin'])->get();

        foreach ($managers as $manager) {
            $manager->notify(new NewLoanRequestNotification($loan));
        }

        try {
            broadcast(new NewLoanRequestEvent($loan));
        } catch (\Exception $e) {
            Log::warning('WebSocket broadcast failed: ' . $e->getMessage());
        }
    }

    /**
     * Notifier l'emprunteur que sa demande a été approuvée (statut passé à in_progress)
     */
    public static function loanApproved(Loan $loan): void
    {
        $borrower = $loan->user;
        if ($borrower) {
            $borrower->notify(new LoanApprovedNotification($loan));
        }

        try {
            broadcast(new LoanApprovedEvent($loan));
        } catch (\Exception $e) {
            Log::warning('WebSocket broadcast failed: ' . $e->getMessage());
        }
    }

    /**
     * Notifier les managers/admins qu'un retour a été complété
     */
    public static function loanReturned(Loan $loan, User $whoReturned): void
    {
        $managers = User::whereIn('role', ['manager', 'admin'])->get();

        foreach ($managers as $manager) {
            $manager->notify(new LoanReturnedNotification($loan, $whoReturned));
        }

        try {
            broadcast(new LoanReturnedEvent($loan, $whoReturned));
        } catch (\Exception $e) {
            Log::warning('WebSocket broadcast failed: ' . $e->getMessage());
        }
    }
}