<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('borrowings', function (Blueprint $table) {
            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('projector_id')
                  ->constrained('projectors')
                  ->cascadeOnDelete();

            $table->primary(['user_id', 'projector_id']);

            $table->dateTime('borrowed_at');
            $table->dateTime('returned_at')->nullable();

            $table->json('accessory_ids')->nullable();   // list of accessory IDs
            $table->string('class');         // class
            $table->string('course');        // course
            $table->string('teacher')->nullable();       // teacher
            $table->string('student')->nullable();       // student
            $table->string('id_card_image')->nullable(); // student ID image

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowings');
    }
};