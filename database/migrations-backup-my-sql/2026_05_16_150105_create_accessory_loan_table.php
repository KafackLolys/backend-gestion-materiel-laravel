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
        Schema::create('accessory_loan', function (Blueprint $table) {

            $table->id();

            // Relation vers loans
            $table->foreignId('loan_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();


            // Relation vers accessories
            $table->foreignId('accessory_id')->nullable()
                ->constrained()
                ->nullOnDelete();


            // Quantité empruntée
            $table->integer('quantity')->default(1);

            $table->timestamps();

            // Empêche les doublons
            $table->unique(['loan_id', 'accessory_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accessory_loan');
    }
};