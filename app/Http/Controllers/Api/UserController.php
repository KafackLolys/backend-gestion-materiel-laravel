<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use OpenApi\Annotations as OA;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/users",
     *     operationId="getUsers",
     *     tags={"Users"},
     *     summary="Lister les utilisateurs",
     *     description="Récupère la liste de tous les utilisateurs",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des utilisateurs"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès refusé"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'sort_by' => 'nullable|string',
            'order' => 'nullable|string|in:asc,desc',
        ]);

        $perPage = $validated['per_page'] ?? 10;
        $search = $validated['search'] ?? null;
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $order = $validated['order'] ?? 'desc';

        try {
            $query = User::query();

            // SEARCH GLOBAL
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'LIKE', "%{$search}%")
                        ->orWhere('last_name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%")
                        ->orWhere('role', 'LIKE', "%{$search}%");
                });
            }

            // ORDER BY DYNAMIQUE
            $query->orderBy($sortBy, $order);

            $users = $query->paginate($perPage);

            return UserResource::collection($users)
                ->additional([
                    'status' => 200,
                    'success' => true,
                    'filters' => [
                        'search' => $search,
                        'sort_by' => $sortBy,
                        'order' => $order,
                    ],
                    'meta' => [
                        'total' => $users->total(),
                        'per_page' => $users->perPage(),
                        'current_page' => $users->currentPage(),
                        'last_page' => $users->lastPage(),
                    ]
                ]);

        } catch (\Throwable $th) {
            ActivityLog::create([
                'title' => 'Erreur lors de la récupération des utilisateurs',
                'description' => $th->getMessage(),
            ]);
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
     *     path="/api/users/{id}",
     *     operationId="getUserById",
     *     tags={"Users"},
     *     summary="Détails d'un utilisateur",
     *     description="Affiche les détails d'un utilisateur spécifique",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de l'utilisateur"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Utilisateur introuvable"
     *     )
     * )
     */
    public function show($id)
    {
        try {
            $user = User::findOrFail($id);
            return response()->json($user);
        } catch (\Throwable $th) {
            ActivityLog::create([
                'title' => 'Erreur lors de la récupération d\'un utilisateur',
                'description' => $th->getMessage(),
            ]);
            return response()->json(['message' => 'Utilisateur introuvable'], 404);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     operationId="deleteUser",
     *     tags={"Users"},
     *     summary="Supprimer un utilisateur",
     *     description="Supprime un utilisateur spécifique par son ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Utilisateur supprimé"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Utilisateur introuvable"
     *     )
     * )
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->delete();

            return response()->json(['message' => 'Utilisateur supprimé']);
        } catch (\Throwable $th) {
            ActivityLog::create([
                'title' => 'Erreur lors de la suppression d\'un utilisateur',
                'description' => $th->getMessage(),
            ]);
            return response()->json(['message' => 'Utilisateur introuvable'], 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/users/{id}",
     *     operationId="updateUser",
     *     tags={"Users"},
     *     summary="Mettre à jour un utilisateur",
     *     description="Met à jour les informations d'un utilisateur existant",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="first_name", type="string"),
     *             @OA\Property(property="last_name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="role", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Utilisateur mis à jour"),
     *     @OA\Response(response=404, description="Utilisateur introuvable")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $user->update($request->all());

            return response()->json($user);
        } catch (\Throwable $th) {
            ActivityLog::create([
                'title' => 'Erreur lors de la mise à jour d\'un utilisateur',
                'description' => $th->getMessage(),
            ]);
            return response()->json(['message' => 'Utilisateur introuvable'], 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     operationId="createUser",
     *     tags={"Users"},
     *     summary="Créer un utilisateur",
     *     description="Crée un nouvel utilisateur dans le système",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"first_name", "last_name", "email", "password", "role"},
     *             @OA\Property(property="first_name", type="string", example="Alice"),
     *             @OA\Property(property="last_name", type="string", example="Dupont"),
     *             @OA\Property(property="email", type="string", format="email", example="alice@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="secret123"),
     *             @OA\Property(property="role", type="string", example="manager")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Utilisateur créé"),
     *     @OA\Response(response=422, description="Erreur de validation")
     * )
     */
    public function store(Request $request)
    {
        try {
            $user = User::create($request->all());

            return response()->json($user);
        } catch (\Throwable $th) {
            ActivityLog::create([
                'title' => 'Erreur lors de la création d\'un utilisateur',
                'description' => $th->getMessage(),
            ]);
            return response()->json(['message' => 'Impossible de créer l\'utilisateur'], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/user/profile",
     *     operationId="updateUserProfile",
     *     tags={"Auth"},
     *     summary="Mettre à jour le profil courant",
     *     description="Permet à l'utilisateur connecté de modifier ses informations",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="first_name", type="string"),
     *             @OA\Property(property="last_name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profil mis à jour"
     *     )
     * )
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();
            $data = $request->validate([
                'first_name' => 'required|string',
                'last_name' => 'required|string',
                'email' => 'required|email|unique:users,email,' . $user->id,
            ]);
            $user->update($data);
            return response()->json($user);
        } catch (\Throwable $th) {
            ActivityLog::create([
                'title' => 'Erreur lors de la mise à jour du profil',
                'description' => $th->getMessage(),
            ]);
            return response()->json(['message' => 'Impossible de mettre à jour le profil'], 500);
        }
    }


    /**
     * @OA\Put(
     *     path="/api/user/password",
     *     operationId="updatePassword",
     *     tags={"Users"},
     *     summary="Mettre à jour le mot de passe",
     *     description="Permet de changer le mot de passe de l'utilisateur connecté",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password", "new_password", "new_password_confirmation"},
     *             @OA\Property(property="current_password", type="string", format="password", example="oldSecret123"),
     *             @OA\Property(property="new_password", type="string", format="password", example="newSecret123"),
     *             @OA\Property(property="new_password_confirmation", type="string", format="password", example="newSecret123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Le mot de passe a été mis à jour"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation"
     *     )
     * )
     */
    public function updatePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => 'required|current_password',
                'new_password' => 'required|min:8|confirmed',
            ]);
            $request->user()->update(['password' => Hash::make($request->new_password)]);
            return response()->json(['message' => 'Le mot de passe a été mis à jour']);
        } catch (\Throwable $th) {
            ActivityLog::create([
                'title' => 'Erreur lors de la mise à jour du mot de passe',
                'description' => $th->getMessage(),
            ]);
            return response()->json(['message' => 'Impossible de mettre à jour le mot de passe'], 500);
        }
    }


    /**
     * @OA\Delete(
     *     path="/api/me",
     *     operationId="deleteMe",
     *     tags={"Auth"},
     *     summary="Supprimer son compte",
     *     description="Permet à l'utilisateur connecté de supprimer son propre compte",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Compte supprimé avec succès"
     *     )
     * )
     */
    public function deleteMe(Request $request)
    {
        try {
            $user = $request->user();

            $user->tokens()->delete();
            $user->delete();

            return response()->json([
                'message' => 'Compte supprimé avec succès'
            ]);
        } catch (\Throwable $th) {
            ActivityLog::create([
                'title' => 'Erreur lors de la suppression du compte',
                'description' => $th->getMessage(),
            ]);
            return response()->json(['message' => 'Impossible de supprimer le compte'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/user/image_profile",
     *     operationId="updateImage",
     *     tags={"Users"},
     *     summary="Mettre à jour l'image de profil",
     *     description="Permet de changer la photo de profil de l'utilisateur",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"image_profil"},
     *                 @OA\Property(
     *                     property="image_profil",
     *                     type="string",
     *                     format="binary",
     *                     description="L'image de profil à uploader"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image mise à jour avec succès"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation (fichier trop lourd ou mauvais format)"
     *     )
     * )
     */
    public function updateImage(Request $request)
    {
        try {
            $request->validate([
                'image_profil' => 'required|image|max:2048',
            ]);

            $user = $request->user();

            if ($user->image_profil) {
                $oldPath = str_replace('/storage/', '', $user->image_profil);
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('image_profil')->store('image_profil', 'public');

            $user->update([
                'image_profil' => Storage::url($path),
            ]);

            return response()->json([
                'image_profil' => $user->image_profil,
            ]);
        } catch (\Throwable $th) {
            ActivityLog::create([
                'title' => 'Erreur lors de la mise à jour de l\'image de profil',
                'description' => $th->getMessage(),
            ]);
            return response()->json(['message' => 'Impossible de mettre à jour l\'image de profil'], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/me",
     *     operationId="updateMe",
     *     tags={"Auth"},
     *     summary="Mettre à jour le profil courant via /api/me",
     *     description="Identique à /api/user/profile. Permet à l'utilisateur connecté de modifier ses informations",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="first_name", type="string", example="Jane"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="jane@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profil mis à jour"
     *     )
     * )
     */
    public function updateMe(Request $request)
    {
        return $this->updateProfile($request);
    }
}