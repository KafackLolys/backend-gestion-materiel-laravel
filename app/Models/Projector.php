<?php

namespace App\Models;

use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Projector extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ref',
        'marque',
        'description',
        'etat',
        'image',
    ];

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    // Optionnel mais propre : constantes pour éviter les fautes
    const ETAT_DISPONIBLE = 'disponible';
    const ETAT_EMPRUNTE = 'emprunte';
    const ETAT_MAINTENANCE = 'en_maintenance';
    const ETAT_HORS_SERVICE = 'hors_service';

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}