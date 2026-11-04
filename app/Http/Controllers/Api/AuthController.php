<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Notifications\ResetPasswordNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OAAttributes;
use OpenApi\Annotations as OA;

class AuthController extends Controller
{

    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required',
            ], [
                'email.required' => 'L\'email est obligatoire',
                'email.email' => 'Format d\'email invalide',
                'password.required' => 'Le mot de passe est obligatoire',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'message' => 'Aucun compte trouvé avec cet email'
                ], 401);
            }

            if (!Hash::check($request->password, $user->getAuthPassword())) {
                return response()->json([
                    'message' => 'Mot de passe incorrect'
                ], 401);
            }

            // Supprimer anciens tokens (optionnel)
            $user->tokens()->delete();

            // Créer token Sanctum
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'class' => $user->class,
                ],
                'token' => $token
            ]);
        } catch (\Exception $e) {
            ActivityLog::create([
                'title' => 'Erreur lors de la tentative de connexion',
                'description' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Une erreur est survenue lors de la tentative de connexion',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function me(Request $request)
    {
        try {
            return response()->json($request->user());
        } catch (\Exception $e) {
            ActivityLog::create([
                'title' => 'Erreur lors de la récupération des informations utilisateur',
                'description' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Une erreur est survenue lors de la récupération des informations utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     operationId="logoutUser",
     *     tags={"Auth"},
     *     summary="Déconnexion",
     *     description="Déconnecte l'utilisateur courant et révoque son token",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Déconnecté avec succès"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié"
     *     )
     * )
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Déconnecté avec succès'
            ]);
        } catch (\Exception $e) {
            ActivityLog::create([
                'title' => 'Erreur lors de la tentative de déconnexion',
                'description' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Une erreur est survenue lors de la tentative de déconnexion',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function forgotPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ], [
                'email.required' => "L'email est obligatoire",
                'email.email' => "Format d'email invalide",
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'message' => 'Aucun compte trouvé avec cet email'
                ], 404);
            }

            // Générer un code à 6 chiffres
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $user->reset_code = $code;
            $user->reset_code_expires_at = Carbon::now()->addMinutes(10);
            $user->save();

            // Envoyer le code par email
            $user->notify(new ResetPasswordNotification($code));

            return response()->json([
                'message' => 'Un code de réinitialisation vous a été envoyé par email',
            ]);
        } catch (\Exception $e) {
            ActivityLog::create([
                'title' => 'Erreur lors de la demande de réinitialisation de mot de passe',
                'description' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Une erreur est survenue lors de la demande de réinitialisation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyResetCode(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'code' => 'required|string|size:6',
            ], [
                'email.required' => "L'email est obligatoire",
                'email.email' => "Format d'email invalide",
                'code.required' => "Le code de vérification est obligatoire",
                'code.size' => "Le code doit contenir 6 caractères",
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'message' => 'Aucun compte trouvé avec cet email'
                ], 404);
            }

            if (!$user->reset_code || $user->reset_code !== $request->code) {
                return response()->json([
                    'message' => 'Code de vérification invalide'
                ], 400);
            }

            if (!$user->reset_code_expires_at || Carbon::now()->greaterThan($user->reset_code_expires_at)) {
                return response()->json([
                    'message' => 'Le code de vérification a expiré'
                ], 400);
            }

            return response()->json([
                'message' => 'Code de vérification valide',
                'verified' => true
            ]);
        } catch (\Exception $e) {
            ActivityLog::create([
                'title' => 'Erreur lors de la vérification du code de réinitialisation',
                'description' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Une erreur est survenue lors de la vérification du code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'code' => 'required|string|size:6',
                'password' => 'required|string|min:6',
                'password_confirmation' => 'required|string|same:password',
            ], [
                'email.required' => "L'email est obligatoire",
                'email.email' => "Format d'email invalide",
                'code.required' => "Le code de vérification est obligatoire",
                'code.size' => "Le code doit contenir 6 caractères",
                'password.required' => "Le mot de passe est obligatoire",
                'password.min' => "Le mot de passe doit contenir au moins 6 caractères",
                'password_confirmation.required' => "La confirmation du mot de passe est obligatoire",
                'password_confirmation.same' => "Les mots de passe ne correspondent pas",
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'message' => 'Aucun compte trouvé avec cet email'
                ], 404);
            }

            if (!$user->reset_code || $user->reset_code !== $request->code) {
                return response()->json([
                    'message' => 'Code de vérification invalide'
                ], 400);
            }

            if (!$user->reset_code_expires_at || Carbon::now()->greaterThan($user->reset_code_expires_at)) {
                return response()->json([
                    'message' => 'Le code de vérification a expiré'
                ], 400);
            }

            // Réinitialiser le mot de passe (le mutator setPasswordAttribute s'occupe du hash)
            $user->password = $request->password;
            $user->reset_code = null;
            $user->reset_code_expires_at = null;
            $user->save();

            // Supprimer tous les tokens de l'utilisateur pour le déconnecter de partout
            $user->tokens()->delete();

            return response()->json([
                'message' => 'Mot de passe réinitialisé avec succès'
            ]);
        } catch (\Exception $e) {
            ActivityLog::create([
                'title' => 'Erreur lors de la réinitialisation du mot de passe',
                'description' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Une erreur est survenue lors de la réinitialisation du mot de passe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|unique:users',
                'password' => 'required',
                'first_name' => 'required',
                'last_name' => 'required',
                'role' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'role' => $request->role,
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'class' => $user->class,
                ],
                'token' => $token
            ]);
        } catch (\Exception $e) {
            ActivityLog::create([
                'title' => 'Erreur lors de la tentative d\'inscription',
                'description' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Une erreur est survenue lors de la tentative d\'inscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}