<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Mettre à jour les anciens enseignants avant modification
        DB::table('users')
            ->where('role', 'teacher')
            ->update([
                'role' => 'student_delegate'
            ]);

        // Modifier l'enum
        DB::statement("
            ALTER TABLE users
            MODIFY role ENUM(
                'admin',
                'manager',
                'student_delegate'
            ) NOT NULL DEFAULT 'student_delegate'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE users
            MODIFY role ENUM(
                'admin',
                'manager',
                'teacher',
                'student_delegate'
            ) NOT NULL DEFAULT 'student_delegate'
        ");
    }
};