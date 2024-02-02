<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;
use Illuminate\Database\Eloquent\SoftDeletes;

class Semester extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $table = 'a_semester';

   protected $fillable = [
        'id',
        'semester_name',
        'academic_year',
        'start_date',
        'end_date',
        'active_status',
        'description',
    ];
    protected static function boot()
    {
        parent::boot();

        // Generate UUID saat membuat record baru
        static::creating(function ($model) {
            $model->id = Uuid::uuid4()->toString();
        });
    }

    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where('semester_name', 'LIKE', "%$search%")
                         ->orWhere('academic_year', 'LIKE', "%$search%")
                         ->orWhere('active_status', 'LIKE', "%$search%");
        }

        return $query;
    }
}
