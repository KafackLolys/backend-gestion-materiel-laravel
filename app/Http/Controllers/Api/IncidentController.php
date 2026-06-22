<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class IncidentController extends Controller
{
    /**
     * Lister les incidents
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

            $query = Incident::with('user');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            $query->orderBy($sortBy, $order);

            $incidents = $query->paginate($perPage);

            return response()->json([
                'data' => $incidents->items(),
                'meta' => [
                    'current_page' => $incidents->currentPage(),
                    'last_page' => $incidents->lastPage(),
                    'per_page' => $incidents->perPage(),
                    'total' => $incidents->total(),
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
            ActivityLog::create([
                'title' => 'Erreur lors de la récupération des incidents',
                'description' => $th->getMessage(),
            ]);
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Erreur lors de la récupération des incidents',
                'error' => $th->getMessage()
            ]);
        }
    }

    /**
     * Afficher un incident spécifique
     */
    public function show($id)
    {
        try {
            $incident = Incident::with('user')->findOrFail($id);
            return response()->json($incident);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            ActivityLog::create([
                'title' => 'Incident non trouvé',
                'description' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Incident non trouvé'], 404);
        }
    }

    /**
     * Créer un incident
     */
    public function store(Request $request)
    {
        try {
            Log::info('1. Début store incident', $request->all());

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);

            Log::info('2. Validation OK', $validated);

            $incident = Incident::create([
                'user_id' => Auth::id(),
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
            ]);

            Log::info('3. Incident créé avec succès', ['id' => $incident->id]);

            return response()->json([
                'data' => $incident->load('user'),
                'message' => 'Incident créé avec succès',
                'success' => true,
            ], 201);

        } catch (ValidationException $e) {
            Log::error('Erreur validation', $e->errors());
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ActivityLog::create([
                'title' => 'Erreur lors de la création d\'un incident',
                'description' => $e->getMessage(),
            ]);
            Log::error('Erreur générale : ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mettre à jour un incident
     */
    public function update(Request $request, $id)
    {
        try {
            $incident = Incident::findOrFail($id);

            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
            ]);

            $data = array_filter($validated, fn($value) => !is_null($value));

            $incident->update($data);

            return response()->json([
                'data' => $incident->load('user'),
                'message' => 'Incident mis à jour',
                'success' => true,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            ActivityLog::create([
                'title' => 'Erreur lors de la mise à jour d\'un incident',
                'description' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Incident non trouvé'], 404);
        }
    }

    /**
     * Supprimer un incident
     */
    public function destroy($id)
    {
        try {
            $incident = Incident::findOrFail($id);
            $incident->delete();

            return response()->json([
                'message' => 'Incident supprimé',
                'success' => true,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            ActivityLog::create([
                'title' => 'Erreur lors de la suppression d\'un incident',
                'description' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Incident non trouvé'], 404);
        }
    }
}