<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class BaseKelas extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'a_base_kelas'; // Nama tabel sesuai dengan skema

    protected $primaryKey = 'id'; // Kolom primary key

    public $incrementing = false; // ID tidak bertambah (UUID)

    protected $keyType = 'string'; // Jenis data primary key

    protected $fillable = [
        'id',
        'nama_kelas',
        'ruang_kelas',
        'deskripsi',
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
            return $query->where('nama_kelas', 'LIKE', "%$search%")
                         ->orWhere('ruang_kelas', 'LIKE', "%$search%");
        }
        return $query;
    }

}
