<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
     *     description="Récupère la liste de certains utilisateurs (admin, manager, teacher, student_delegate)",
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
    public function index()
    {
        return User::whereIn('role', ['admin', 'manager', 'teacher', 'student_delegate'])
            ->get();
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
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Utilisateur introuvable'], 404);
        }

        return response()->json($user);
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
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'Utilisateur introuvable'], 404);
        }

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé']);
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
        $user = $request->user();
        $data = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);
        $user->update($data);
        return response()->json($user);
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
        $request->validate([
            'current_password' => 'required|current_password',
            'new_password' => 'required|min:8|confirmed',
        ]);
        $request->user()->update(['password' => Hash::make($request->new_password)]);
        return response()->json(['message' => 'Le mot de passe a été mis à jour']);
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
        $user = $request->user();

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Compte supprimé avec succès'
        ]);
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
        $request->validate([
            'image_profil' => 'required|image|max:2048',
        ]);

        $user = $request->user();

        // Supprimer l'ancienne image si elle existe
        if ($user->image_profil) {
            // Enlever le préfixe "/storage/" si présent
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

    /**
     * @OA\Get(
     *     path="/api/loans",
     *     operationId="getAllLoans",
     *     tags={"Loans"},
     *     summary="Lister tous les emprunts",
     *     description="Récupère la liste de tous les emprunts",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste de tous les emprunts"
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
    public function get_all_loans(Request $request)
    {
        // TODO: Implémenter la logique
        return response()->json([]);
    }
}