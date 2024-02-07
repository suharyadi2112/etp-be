<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('a_siswa', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nis')->unique();
            $table->string('nama')->notNullable();
            $table->enum('gender', ['Laki-laki', 'Perempuan'])->notNullable();
            $table->date('birth_date')->notNullable();
            $table->text('birth_place')->notNullable();
            $table->text('address')->nullable();
            $table->string('phone_number')->nullable();
            $table->enum('status', ['Active', 'Non-Active']);
            $table->uuid('id_kelas'); // Foreign key ke tabel kelas
            $table->timestamps();
            $table->softDeletes(); // deleted_at
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('a_siswa');
    }
};
