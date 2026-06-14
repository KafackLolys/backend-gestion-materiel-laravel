<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Loan;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    /**
     * Champs autorisés pour insertion (mass assignment)
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'class',
        'image_profil',
        'reset_code',
        'reset_code_expires_at',
    ];

    /**
     * Champs cachés dans les réponses JSON
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts (types automatiques)
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Valeurs possibles pour le rôle
     */
    const ROLE_ADMIN = 'admin';
    const ROLE_MANAGER = 'manager';
    const ROLE_STUDENT_DELEGATE = 'student_delegate';

    /**
     * Accessor : nom complet
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Mutator : hash automatique du mot de passe
     */
    public function setPasswordAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['password'] = bcrypt($value);
        }
    }

    /**
     * Vérifier le rôle
     */
    public function isAdmin()
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isManager()
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isStudentDelegate()
    {
        return $this->role === self::ROLE_STUDENT_DELEGATE;
    }

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }
}