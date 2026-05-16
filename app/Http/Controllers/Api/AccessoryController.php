<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'Erreur lors de la récupération des accessoires',
                'error' => $th->getMessage()
            ]);
        }
    }

    // Afficher un accessoire
    public function show($id)
    {
        $accessory = Accessory::with('user')->findOrFail($id);
        return response()->json($accessory);
    }

    // Créer un accessoire
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
            Log::error('Erreur générale : ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
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
    }

    // Supprimer un accessoire
    public function destroy($id)
    {
        $accessory = Accessory::findOrFail($id);
        $accessory->delete();

        return response()->json([
            'message' => 'Accessoire supprimé',
            'success' => true,
        ]);
    }
}
