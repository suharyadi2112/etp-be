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
        Schema::create('a_guru', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nip')->unique()->comment('Nomor Unik Pegawai');
            $table->string('nuptk')->unique()->nullable()->comment('Nomor Unik Pendidik dan Tenaga Kependidikan');
            $table->string('nama')->notNullable();
            $table->enum('gender', ['Laki-laki', 'Perempuan'])->notNullable();
            $table->date('birth_date')->notNullable();
            $table->text('birth_place')->notNullable();
            $table->text('address')->nullable();
            $table->string('phone_number')->nullable();
            $table->enum('status', ['Active', 'Non-Active']);

            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->string('linkedin')->nullable();
            $table->longText('photo_profile')->nullable();
            $table->text('photo_name_ori')->nullable();
            $table->string('religion')->nullable();
            $table->string('email')->nullable();
            $table->string('parent_phone_number')->nullable();

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
        Schema::dropIfExists('a_guru');
    }
};
