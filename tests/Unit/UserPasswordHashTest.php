<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserPasswordHashTest extends TestCase
{
    public function test_postgresql_bcrypt_hash_can_be_checked_by_laravel(): void
    {
        $laravelHash = Hash::make('password123');
        $postgresHash = substr_replace($laravelHash, '2a', 1, 2);

        $user = new User;
        $user->setRawAttributes(['password' => $postgresHash], true);

        $this->assertSame($laravelHash, $user->getAuthPassword());
        $this->assertTrue(Hash::check('password123', $user->getAuthPassword()));
    }
}
