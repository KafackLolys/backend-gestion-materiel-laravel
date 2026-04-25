<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ADMIN
        User::create([
            'first_name' => 'Admin',
            'last_name'  => 'System',
            'email'      => 'admin@test.com',
            'password'   => 'password123', // hash automatique via mutator
            'role'       => User::ROLE_ADMIN,
        ]);

        // MANAGER
        User::create([
            'first_name' => 'Manager',
            'last_name'  => 'User',
            'email'      => 'manager@test.com',
            'password'   => 'password123',
            'role'       => User::ROLE_MANAGER,
        ]);

        // TEACHER
        User::create([
            'first_name' => 'John',
            'last_name'  => 'Teacher',
            'email'      => 'teacher@test.com',
            'password'   => 'password123',
            'role'       => User::ROLE_TEACHER,
        ]);

        // STUDENT DELEGATE
        User::create([
            'first_name' => 'Student',
            'last_name'  => 'Delegate',
            'email'      => 'student@test.com',
            'password'   => 'password123',
            'role'       => User::ROLE_STUDENT_DELEGATE,
        ]);
    }
}