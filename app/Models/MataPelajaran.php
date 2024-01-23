<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class MataPelajaran extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'a_mata_pelajaran'; // Nama tabel sesuai dengan skema

    protected $primaryKey = 'id'; // Kolom primary key

    public $incrementing = false; // ID tidak bertambah (UUID)

    protected $keyType = 'string'; // Jenis data primary key

    protected $fillable = [
        'id',
        'subject_name',
        'subject_description',
        'education_level',
        'subject_code',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = Uuid::uuid4()->toString();
        });
    }

    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where('subject_name', 'LIKE', "%$search%")
                         ->orWhere('education_level', 'LIKE', "%$search%")
                         ->orWhere('subject_code', 'LIKE', "%$search%");
        }

        return $query;
    }

}
