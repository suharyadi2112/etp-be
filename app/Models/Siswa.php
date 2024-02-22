<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class Siswa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'a_siswa'; // Nama tabel sesuai dengan skema

    protected $primaryKey = 'id'; // Kolom primary key

    public $incrementing = false; // ID tidak bertambah (UUID)

    protected $keyType = 'string'; // Jenis data primary key

    protected $fillable = [
        'id',
        'id_kelas',
        'nis',
        'nama',
        'gender',
        'birth_date',
        'birth_place',
        'address',
        'phone_number',
        'status',
        
        'facebook',
        'instagram',
        'linkedin',
        'photo_name_ori',
        'path_photo_cloud',
        'religion',
        'email',
        'parent_phone_number',

        'created_at',
        'updated_at',
        'deleted_at',
        
    ];

    public function basekelas()
    {
        return $this->belongsTo(BaseKelas::class, 'id_kelas');
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
            return $query->where('nis', 'LIKE', "%$search%")
                         ->orWhere('nama', 'LIKE', "%$search%")
                         ->orWhere('phone_number', 'LIKE', "%$search%")
                         ->orWhere('gender', 'LIKE', "%$search%");
        }

        return $query;
    }
}
