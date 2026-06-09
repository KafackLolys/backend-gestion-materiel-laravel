<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use OpenApi\Annotations as OA;
use App\Models\Projector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectorController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/projectors",
     *     operationId="getProjectors",
     *     tags={"Projectors"},
     *     summary="Lister les projecteurs",
     *     description="Récupère la liste des projecteurs disponibles",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer"), description="Numéro de page"),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer"), description="Nombre d'éléments par page"),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="Recherche par marque ou description"),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"ref","marque","etat","created_at"}), description="Champ de tri"),
     *     @OA\Parameter(name="order", in="query", @OA\Schema(type="string", enum={"asc","desc"}), description="Ordre de tri"),
     *     @OA\Response(response=200, description="Liste des projecteurs retournée"),
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
                'sort_by' => 'nullable|string|in:ref,marque,etat,created_at',
                'order' => 'nullable|string|in:asc,desc',
            ]);

            $perPage = $validated['per_page'] ?? 10;
            $search = $validated['search'] ?? null;
            $sortBy = $validated['sort_by'] ?? 'created_at';
            $order = $validated['order'] ?? 'desc';

            $query = Projector::query();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('marque', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            $query->orderBy($sortBy, $order);

            $projecteurs = $query->paginate($perPage);

            return response()->json([
                'data' => $projecteurs->items(),
                'meta' => [
                    'current_page' => $projecteurs->currentPage(),
                    'last_page' => $projecteurs->lastPage(),
                    'per_page' => $projecteurs->perPage(),
                    'total' => $projecteurs->total(),
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
                'message' => 'Erreur lors de la récupération des utilisateurs',
                'error' => $th->getMessage()
            ]);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/projectors/{id}",
     *     operationId="getProjectorById",
     *     tags={"Projectors"},
     *     summary="Détails d'un projecteur",
     *     description="Affiche les informations d'un projecteur spécifique",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Projecteur trouvé"),
     *     @OA\Response(response=404, description="Projecteur introuvable")
     * )
     */
    // Afficher un projecteur
    public function show($id)
    {
        $projecteur = Projector::with('user')->findOrFail($id);
        return response()->json($projecteur);
    }

    /**
     * @OA\Post(
     *     path="/api/projectors",
     *     operationId="createProjector",
     *     tags={"Projectors"},
     *     summary="Créer un projecteur",
     *     description="Ajoute un nouveau projecteur à l'inventaire",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"marque", "etat"},
     *                 @OA\Property(property="marque", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="etat", type="string", enum={"disponible","emprunte","en_maintenance","hors_service"}),
     *                 @OA\Property(property="image", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Projecteur créé"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    // Créer un projecteur
    public function store(Request $request)
    {
        try {
            Log::info('1. Début store', $request->all());

            $validated = $request->validate([
                'marque' => 'required|string|max:255',
                'description' => 'nullable|string',
                'etat' => ['required', Rule::in(['disponible', 'emprunte', 'en_maintenance', 'hors_service'])],
                'image' => 'nullable|image|max:2048',
            ]);

            Log::info('2. Validation OK', $validated);

            $imagePath = null;
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('projecteurs', 'public');
                $imagePath = Storage::url($path);
                Log::info('3. Image stockée', ['path' => $path, 'url' => $imagePath]);
            }

            $projecteur = Projector::create([
                'user_id' => Auth::id(),
                'marque' => $validated['marque'],
                'description' => $validated['description'] ?? null,
                'etat' => $validated['etat'],
                'image' => $imagePath,
            ]);

            Log::info('4. Projecteur créé avec succès', ['id' => $projecteur->id]);

            return response()->json([
                'data' => $projecteur,
                'message' => 'Projecteur créé avec succès',
                'success' => true,
            ], 201);

        } catch (ValidationException $e) {
            Log::error('Erreur validation', $e->errors());
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Erreur générale : ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/projectors/{id}",
     *     operationId="updateProjector",
     *     tags={"Projectors"},
     *     summary="Mettre à jour un projecteur",
     *     description="Met à jour les informations d'un projecteur existant",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="marque", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="etat", type="string", enum={"disponible","emprunte","en_maintenance","hors_service"}),
     *                 @OA\Property(property="image", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Projecteur mis à jour"),
     *     @OA\Response(response=404, description="Projecteur introuvable")
     * )
     */
    public function update(Request $request, $id)
    {
        $projecteur = Projector::findOrFail($id);

        $validated = $request->validate([
            'marque' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'etat' => ['sometimes', Rule::in(['disponible', 'emprunte', 'en_maintenance', 'hors_service'])],
            'image' => 'nullable|image|max:2048',
        ]);

        $data = array_filter($validated, fn($value) => !is_null($value));

        if ($request->hasFile('image')) {
            // Supprimer l'ancienne image si elle existe
            if ($projecteur->image) {
                $oldPath = str_replace('/storage/', '', $projecteur->image);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('image')->store('projecteurs', 'public');
            $data['image'] = Storage::url($path);
        }

        $projecteur->update($data);

        return response()->json([
            'data' => $projecteur,
            'message' => 'Projecteur mis à jour',
            'success' => true,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/projectors/{id}",
     *     operationId="deleteProjector",
     *     tags={"Projectors"},
     *     summary="Supprimer un projecteur",
     *     description="Supprime un projecteur de l'inventaire",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Projecteur supprimé"),
     *     @OA\Response(response=404, description="Projecteur introuvable")
     * )
     */
    // Supprimer un projecteur
    public function destroy($id)
    {
        $projecteur = Projector::findOrFail($id);
        $projecteur->delete();

        return response()->json([
            'message' => 'Projecteur supprimé',
            'success' => true,
        ]);
    }
}

