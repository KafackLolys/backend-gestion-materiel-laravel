<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use OpenApi\Annotations as OA;
use App\Models\Accessory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AccessoryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'search' => 'nullable|string|max:255',
                'sort_by' => 'nullable|string|in:name,quantity,created_at',
                'order' => 'nullable|string|in:asc,desc',
            ]);

            $perPage = $validated['per_page'] ?? 10;
            $search = $validated['search'] ?? null;
            $sortBy = $validated['sort_by'] ?? 'created_at';
            $order = $validated['order'] ?? 'desc';

            $query = Accessory::query();

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            $query->orderBy($sortBy, $order);

            $accessories = $query->paginate($perPage);

            return response()->json([
                'data' => $accessories->items(),
                'meta' => [
                    'current_page' => $accessories->currentPage(),
                    'last_page' => $accessories->lastPage(),
                    'per_page' => $accessories->perPage(),
                    'total' => $accessories->total(),
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
                'title' => 'Erreur lors de la récupération des accessoires',
                'description' => $th->getMessage(),
            ]);
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Erreur lors de la récupération des accessoires',
                'error' => $th->getMessage()
            ]);
        }
    }
    public function show($id)
    {
        try {
            $accessory = Accessory::with('user')->findOrFail($id);
            return response()->json($accessory);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            ActivityLog::create([
                'title' => 'Accessoire non trouvé',
                'description' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Accessoire non trouvé'], 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/accessories",
     *     operationId="createAccessory",
     *     tags={"Accessories"},
     *     summary="Créer un accessoire",
     *     description="Ajoute un nouvel accessoire à l'inventaire",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name", "quantity"},
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="quantity", type="integer"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="image", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Accessoire créé"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function store(Request $request)
    {
        try {
            Log::info('1. Début store accessoire', $request->all());

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'quantity' => 'required|integer|min:1',
                'description' => 'nullable|string',
                'image' => 'nullable|image|max:2048',
            ]);

            Log::info('2. Validation OK', $validated);

            $imagePath = null;
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('accessories', 'public');
                $imagePath = Storage::url($path);
                Log::info('3. Image stockée', ['path' => $path, 'url' => $imagePath]);
            }

            $accessory = Accessory::create([
                'user_id' => Auth::id(),
                'name' => $validated['name'],
                'quantity' => $validated['quantity'],
                'description' => $validated['description'] ?? null,
                'image' => $imagePath,
            ]);

            Log::info('4. Accessoire créé avec succès', ['id' => $accessory->id]);

            return response()->json([
                'data' => $accessory,
                'message' => 'Accessoire créé avec succès',
                'success' => true,
            ], 201);

        } catch (ValidationException $e) {
            Log::error('Erreur validation', $e->errors());
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            ActivityLog::create([
                'title' => 'Erreur lors de la création d\'un accessoire',
                'description' => $e->getMessage(),
            ]);
            Log::error('Erreur générale : ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/accessories/{id}",
     *     operationId="updateAccessory",
     *     tags={"Accessories"},
     *     summary="Mettre à jour un accessoire",
     *     description="Modifie les données d'un accessoire existant",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="quantity", type="integer"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="image", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Accessoire mis à jour"),
     *     @OA\Response(response=404, description="Accessoire introuvable")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $accessory = Accessory::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'quantity' => 'sometimes|integer|min:1',
                'description' => 'nullable|string',
                'image' => 'nullable|image|max:2048',
            ]);

            $data = array_filter($validated, fn($value) => !is_null($value));

            if ($request->hasFile('image')) {
                // Supprimer l'ancienne image si elle existe
                if ($accessory->image) {
                    $oldPath = str_replace('/storage/', '', $accessory->image);
                    Storage::disk('public')->delete($oldPath);
                }
                $path = $request->file('image')->store('accessories', 'public');
                $data['image'] = Storage::url($path);
            }

            $accessory->update($data);

            return response()->json([
                'data' => $accessory,
                'message' => 'Accessoire mis à jour',
                'success' => true,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            ActivityLog::create([
                'title' => 'Erreur lors de la mise à jour d\'un accessoire',
                'description' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Accessoire non trouvé'], 404);
        }

    }

    /**
     * @OA\Delete(
     *     path="/api/accessories/{id}",
     *     operationId="deleteAccessory",
     *     tags={"Accessories"},
     *     summary="Supprimer un accessoire",
     *     description="Supprime un accessoire de l'inventaire",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Accessoire supprimé"),
     *     @OA\Response(response=404, description="Accessoire introuvable")
     * )
     */
    // Supprimer un accessoire
    public function destroy($id)
    {
        try {
            $accessory = Accessory::findOrFail($id);
            $accessory->delete();

            return response()->json([
                'message' => 'Accessoire supprimé',
                'success' => true,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            ActivityLog::create([
                'title' => 'Erreur lors de la suppression d\'un accessoire',
                'description' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Accessoire non trouvé'], 404);
        }
    }
}
