<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'projector_id',
        'loan_date',
        'return_date',
        'classroom',
        'room',
        'cni_number',
        'status',
        'starting_state',
        'ending_state',
        'teacher_name',
    ];

    protected $casts = [
        'loan_date' => 'datetime',
        'return_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function projector()
    {
        return $this->belongsTo(Projector::class);
    }

    public function accessories()
    {
        return $this->belongsToMany(Accessory::class, 'accessory_loan')
            ->withPivot('quantity')
            ->withTimestamps();
    }
}
