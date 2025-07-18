<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255);
            $table->string('password', 255);
            $table->string('name', 255);
            $table->string('first_name', 255);
            $table->string('preferred_locale', 3)->nullable();
            $table->timestamp('birth_date')->nullable();
            $table->date('birth_day')->nullable();
            $table->time('birth_hour')->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->float('score')->nullable();
            $table->text('comment')->nullable();
            $table->unsignedTinyInteger('status')->nullable();
            $table->string('favorite_fruits', 15)->nullable();
            $table->boolean('has_consumer_ability')->default(false);
            $table->timestamp('email_verified_at')->nullable();

            if (DB::connection()->getDriverName() != 'pgsql') {
                $table->geometry('positions');
            }
        });
        Schema::create('visibles', function (Blueprint $table) {
            $table->id();
            $table->string('visible', 255);
            $table->string('visible_hidden', 255);
            $table->string('hidden', 255);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('visibles');
    }
};
