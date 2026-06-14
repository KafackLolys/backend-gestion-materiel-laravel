<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accessory;
use App\Models\ActivityLog;
use OpenApi\Annotations as OA;
use App\Models\Projector;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/stats",
     *     operationId="getStats",
     *     tags={"Stats"},
     *     summary="Récupérer les statistiques globales",
     *     description="Retourne des métriques sur les utilisateurs, projecteurs, accessoires et emprunts",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Statistiques récupérées avec succès"),
     *     @OA\Response(response=500, description="Erreur serveur")
     * )
     */
    public function index(Request $request)
    {
        try {
            $totalUsers = User::count();
            $totalProjectors = Projector::count();
            $totalAccessories = Accessory::count();
            $totalLoans = DB::table('loans')->count();

            $projectorsByStatus = Projector::select('etat', DB::raw('count(*) as count'))
                ->groupBy('etat')
                ->pluck('count', 'etat')
                ->toArray();

            $loansByStatus = DB::table('loans')
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $projectorStatuses = [
                'disponible' => 0,
                'emprunte' => 0,
                'en_maintenance' => 0,
                'hors_service' => 0,
            ];

            foreach ($projectorStatuses as $status => $count) {
                $projectorStatuses[$status] = $projectorsByStatus[$status] ?? 0;
            }

            $loanStatuses = [
                'pending' => 0,
                'in_progress' => 0,
                'completed' => 0,
            ];

            foreach ($loanStatuses as $status => $count) {
                $loanStatuses[$status] = $loansByStatus[$status] ?? 0;
            }

            return response()->json([
                'status' => 200,
                'success' => true,
                'data' => [
                    'users' => [
                        'total' => $totalUsers,
                    ],
                    'projectors' => [
                        'total' => $totalProjectors,
                        'by_status' => $projectorStatuses,
                    ],
                    'accessories' => [
                        'total' => $totalAccessories,
                    ],
                    'loans' => [
                        'total' => $totalLoans,
                        'by_status' => $loanStatuses,
                    ],
                ],
            ]);
        } catch (\Throwable $th) {
            ActivityLog::create([
                'title' => 'Erreur lors de la récupération des statistiques',
                'description' => $th->getMessage(),
            ]);
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
