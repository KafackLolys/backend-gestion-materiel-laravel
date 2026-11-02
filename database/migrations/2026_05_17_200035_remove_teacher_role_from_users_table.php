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

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("
                ALTER TABLE users
                ADD CONSTRAINT users_role_check
                CHECK (role IN ('admin', 'manager', 'student_delegate'))
            ");
        } else {
            DB::statement("
                ALTER TABLE users
                MODIFY role ENUM(
                    'admin',
                    'manager',
                    'student_delegate'
                ) NOT NULL DEFAULT 'student_delegate'
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("
                ALTER TABLE users
                ADD CONSTRAINT users_role_check
                CHECK (role IN ('admin', 'manager', 'teacher', 'student_delegate'))
            ");
        } else {
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
    }
};