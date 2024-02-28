<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class OrangTua extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'a_parents'; // Nama tabel sesuai dengan skema

    protected $primaryKey = 'id'; // Kolom primary key

    public $incrementing = false; // ID tidak bertambah (UUID)

    protected $keyType = 'string'; // Jenis data primary key

    protected $fillable = [
        'id',
        'id_siswa',
        'name',
        'address',
        'phone_number',
        'email',
        'relationship',
        'date_of_birth',
        'place_of_birth',
        'occupation',
        'emergency_contact',
        'additional_notes',

        'created_at',
        'updated_at',
        'deleted_at',
        
    ];

    public function siswa()
    {
        return $this->hasMany(Siswa::class, 'id_siswa');
    }

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
            return $query->where('name', 'LIKE', "%$search%");
        }

        return $query;
    }
}
