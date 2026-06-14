<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accessory;
use App\Models\ActivityLog;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Events\ReturnCompletedEvent;
use App\Notifications\ReturnCompletedNotification;
use App\Events\NewLoanRequestEvent;
use App\Notifications\NewLoanRequestNotification;
use App\Services\NotificationService;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:pending,in_progress,completed',
            'projector_id' => 'nullable|integer|exists:projectors,id',
            'sort_by' => 'nullable|string|in:loan_date,return_date,status,created_at',
            'order' => 'nullable|string|in:asc,desc',
        ]);

        $perPage = $validated['per_page'] ?? 10;
        $search = $validated['search'] ?? null;
        $status = $validated['status'] ?? null;
        $projectorId = $validated['projector_id'] ?? null;
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $order = $validated['order'] ?? 'desc';

        $query = Loan::with(['user', 'projector', 'accessories']);

        // ✅ Correction : Vérifier le rôle directement
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'manager'])) {
            $query->where('user_id', Auth::id());
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('teacher_name', 'LIKE', "%{$search}%")
                    ->orWhere('classroom', 'LIKE', "%{$search}%")
                    ->orWhere('room', 'LIKE', "%{$search}%")
                    ->orWhere('cni_number', 'LIKE', "%{$search}%")
                    ->orWhere('status', 'LIKE', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($projectorId) {
            $query->where('projector_id', $projectorId);
        }

        $loans = $query->orderBy($sortBy, $order)->paginate($perPage);

        return response()->json([
            'data' => $loans->items(),
            'meta' => [
                'current_page' => $loans->currentPage(),
                'last_page' => $loans->lastPage(),
                'per_page' => $loans->perPage(),
                'total' => $loans->total(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'projector_id' => $projectorId,
                'sort_by' => $sortBy,
                'order' => $order,
            ],
            'status' => 200,
            'success' => true,
        ]);
    }

    public function show($id)
    {
        $loan = Loan::with(['user', 'projector', 'accessories'])->findOrFail($id);
        $this->authorizeLoanAccess($loan);

        return response()->json($loan);
    }

    public function store(Request $request)
    {
        try {
            // Vérifier si l'utilisateur a déjà un emprunt actif
            $activeLoan = Loan::where('user_id', Auth::id())
                ->whereNotIn('status', ['completed'])
                ->first();

            if ($activeLoan) {
                return response()->json([
                    'error' => 'Vous avez déjà un emprunt en cours. Veuillez le retourner avant d\'en faire un nouveau.',
                    'active_loan_id' => $activeLoan->id,
                    'status' => $activeLoan->status,
                ], 403);
            }

            $validated = $request->validate([
                'projector_id' => 'required|integer|exists:projectors,id',
                'loan_date' => 'required|date',
                'return_date' => 'nullable|date|after_or_equal:loan_date',
                'classroom' => 'nullable|string|max:255',
                'room' => 'nullable|string|max:255',
                'cni_number' => 'required|string|max:100',
                'status' => 'nullable|in:pending,in_progress,completed',
                'starting_state' => 'nullable|string',
                'ending_state' => 'nullable|string',
                'teacher_name' => 'nullable|string|max:255',
                'accessories' => 'nullable|array',
                'accessories.*.accessory_id' => 'required_with:accessories|integer|exists:accessories,id',
                'accessories.*.quantity' => 'required_with:accessories|integer|min:1',
            ]);

            $loan = Loan::create([
                'user_id' => Auth::id(),
                'projector_id' => $validated['projector_id'] ?? null,
                'loan_date' => $validated['loan_date'],
                'return_date' => $validated['return_date'] ?? null,
                'classroom' => $validated['classroom'] ?? null,
                'room' => $validated['room'] ?? null,
                'cni_number' => $validated['cni_number'],
                'status' => $validated['status'] ?? 'pending',
                'starting_state' => $validated['starting_state'] ?? null,
                'ending_state' => $validated['ending_state'] ?? null,
                'teacher_name' => $validated['teacher_name'] ?? null,
            ]);

            if (!empty($validated['accessories'])) {
                $loan->accessories()->sync(array_reduce($validated['accessories'], function ($carry, $item) {
                    $carry[$item['accessory_id']] = ['quantity' => $item['quantity']];
                    return $carry;
                }, []));
            }

            // Notifier les managers d'une nouvelle demande
            NotificationService::newLoanRequest($loan);

            return response()->json([
                'data' => $loan->load(['user', 'projector', 'accessories']),
                'message' => 'Emprunt créé avec succès',
                'success' => true,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $th) {
            ActivityLog::create([
                'title' => 'Erreur lors de la création d\'un emprunt',
                'description' => $th->getMessage(),
            ]);
            Log::error('Erreur création emprunt', ['error' => $th->getMessage(), 'trace' => $th->getTraceAsString()]);
            return response()->json(['error' => 'Impossible de créer l\'emprunt'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $loan = Loan::findOrFail($id);
        $this->authorizeLoanAccess($loan);

        $validated = $request->validate([
            'projector_id' => 'nullable|integer|exists:projectors,id',
            'loan_date' => 'nullable|date',
            'return_date' => 'nullable|date|after_or_equal:loan_date',
            'classroom' => 'nullable|string|max:255',
            'room' => 'nullable|string|max:255',
            'cni_number' => 'nullable|string|max:100',
            'status' => 'nullable|in:pending,in_progress,completed',
            'starting_state' => 'nullable|string',
            'ending_state' => 'nullable|string',
            'teacher_name' => 'nullable|string|max:255',
            'accessories' => 'nullable|array',
            'accessories.*.accessory_id' => 'required_with:accessories|integer|exists:accessories,id',
            'accessories.*.quantity' => 'required_with:accessories|integer|min:1',
            'is_completed' => 'nullable|boolean',
        ]);

        // Vérifier si le statut passe de pending à in_progress (approbation)
        $wasPending = $loan->status === 'pending';
        $nowInProgress = isset($validated['status']) && $validated['status'] === 'in_progress';
        $shouldNotifyApproval = $wasPending && $nowInProgress;

        // Vérifier si c'est un retour complet (is_completed = true)
        $wasNotCompleted = $loan->status !== 'completed';
        $isNowCompleted = isset($validated['is_completed']) && $validated['is_completed'] === true;
        $shouldNotifyReturn = $wasNotCompleted && $isNowCompleted;

        $loan->update(array_filter([
            'projector_id' => $validated['projector_id'] ?? null,
            'loan_date' => $validated['loan_date'] ?? null,
            'return_date' => $validated['return_date'] ?? null,
            'classroom' => $validated['classroom'] ?? null,
            'room' => $validated['room'] ?? null,
            'cni_number' => $validated['cni_number'] ?? null,
            'status' => $validated['status'] ?? null,
            'starting_state' => $validated['starting_state'] ?? null,
            'ending_state' => $validated['ending_state'] ?? null,
            'teacher_name' => $validated['teacher_name'] ?? null,
        ], fn($value) => !is_null($value)));

        if (array_key_exists('accessories', $validated)) {
            $loan->accessories()->sync(array_reduce($validated['accessories'], function ($carry, $item) {
                $carry[$item['accessory_id']] = ['quantity' => $item['quantity']];
                return $carry;
            }, []));
        }

        // ✅ Notification d'approbation à l'emprunteur
        if ($shouldNotifyApproval) {
            NotificationService::loanApproved($loan);
        }

        // ✅ Notification de retour aux managers/admins
        if ($shouldNotifyReturn) {
            // Mettre à jour le statut et la date de retour
            $loan->status = 'completed';
            $loan->return_date = now();
            $loan->save();

            // Mettre à jour l'état du projecteur
            if ($loan->projector) {
                $loan->projector->update(['etat' => 'disponible']);
            }

            NotificationService::loanReturned($loan, Auth::user());
        }


        return response()->json([
            'data' => $loan->load(['user', 'projector', 'accessories']),
            'message' => $shouldNotifyReturn ? 'Retour enregistré avec succès' : 'Emprunt mis à jour',
            'success' => true,
        ]);
    }

    public function destroy($id)
    {
        $loan = Loan::findOrFail($id);
        $this->authorizeLoanAccess($loan);

        $loan->accessories()->detach();
        $loan->delete();

        return response()->json([
            'message' => 'Emprunt supprimé',
            'success' => true,
        ]);
    }

    /**
     * Autoriser l'accès à l'emprunt selon le rôle (version unique et correcte)
     */
    private function authorizeLoanAccess(Loan $loan)
    {
        $user = Auth::user();

        // Admin et manager peuvent tout voir/modifier
        if (in_array($user->role, ['admin', 'manager'])) {
            return true;
        }

        // Les utilisateurs normaux ne peuvent modifier que leurs propres emprunts
        if ($loan->user_id !== $user->id) {
            abort(403, 'Accès non autorisé à cet emprunt');
        }

        return true;
    }

    /**
     * Récupérer les emprunts de l'utilisateur connecté
     */
    public function getUserLoans(Request $request)
    {
        try {
            $user = Auth::user();

            $query = Loan::with(['projector', 'accessories'])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            // Emprunt actif (non terminé)
            $activeLoan = (clone $query)->whereNotIn('status', ['completed'])->first();

            // Historique des emprunts (terminés)
            $history = (clone $query)->where('status', 'completed')->paginate(10);

            return response()->json([
                'success' => true,
                'data' => [
                    'active_loan' => $activeLoan,
                    'history' => $history->items(),
                    'history_meta' => [
                        'current_page' => $history->currentPage(),
                        'last_page' => $history->lastPage(),
                        'per_page' => $history->perPage(),
                        'total' => $history->total(),
                    ],
                ],
                'message' => 'Emprunts récupérés avec succès',
            ], 200);
        } catch (\Throwable $th) {
            Log::error('Erreur récupération emprunts utilisateur', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Impossible de récupérer vos emprunts'
            ], 500);
        }
    }

    /**
     * Vérifier si l'utilisateur a un emprunt actif
     */
    public function checkActiveLoan(Request $request)
    {
        try {
            $user = Auth::user();

            $activeLoan = Loan::where('user_id', $user->id)
                ->whereNotIn('status', ['completed'])
                ->first();

            return response()->json([
                'success' => true,
                'has_active_loan' => !is_null($activeLoan),
                'active_loan' => $activeLoan,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'error' => 'Impossible de vérifier votre statut'
            ], 500);
        }
    }
}
