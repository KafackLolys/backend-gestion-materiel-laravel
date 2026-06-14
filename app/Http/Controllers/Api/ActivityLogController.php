<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class ActivityLogController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/activity-logs",
     *     operationId="getActivityLogs",
     *     tags={"ActivityLogs"},
     *     summary="Lister les journaux d'activité",
     *     description="Récupère la liste paginée des journaux d'activité avec recherche et tri",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer"), description="Numéro de page"),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer"), description="Nombre d'éléments par page"),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="Recherche par titre ou description"),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"title","created_at"}), description="Champ de tri"),
     *     @OA\Parameter(name="order", in="query", @OA\Schema(type="string", enum={"asc","desc"}), description="Ordre de tri"),
     *     @OA\Response(response=200, description="Liste des journaux d'activité retournée"),
     *     @OA\Response(response=500, description="Erreur serveur")
     * )
     */
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'search' => 'nullable|string|max:255',
                'sort_by' => 'nullable|string|in:title,created_at',
                'order' => 'nullable|string|in:asc,desc',
            ]);

            $perPage = $validated['per_page'] ?? 10;
            $search = $validated['search'] ?? null;
            $sortBy = $validated['sort_by'] ?? 'created_at';
            $order = $validated['order'] ?? 'desc';

            $query = ActivityLog::query();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            $query->orderBy($sortBy, $order);

            $logs = $query->paginate($perPage);

            return response()->json([
                'data' => $logs->items(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                ],
                'filters' => [
                    'search' => $search,
                    'sort_by' => $sortBy,
                    'order' => $order,
                ],
                'status' => 200,
                'success' => true,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Erreur lors de la récupération des journaux d\'activité',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
