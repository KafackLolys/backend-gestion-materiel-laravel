<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {

            $table->id();

            // Relations
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('projector_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Dates
            $table->dateTime('loan_date');
            $table->dateTime('return_date')->nullable();

            // Informations salle/classe
            $table->string('classroom')->nullable();
            $table->string('room')->nullable();

            // CNI
            $table->string('cni_number');

            // Statut
            $table->enum('status', [
                'pending',
                'in_progress',
                'completed'
            ])->default('pending');

            // États du projecteur
            $table->text('starting_state')->nullable();
            $table->text('ending_state')->nullable();

            // Enseignant
            $table->string('teacher_name')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};