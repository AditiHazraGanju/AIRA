<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the reference data Aira needs: the state list shown on the
     * registration form. Users sign up through the OTP login, so none are
     * seeded here.
     */
    public function run(): void
    {
        $this->call(StatesTableSeeder::class);
    }
}
