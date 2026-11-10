<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * The admin panel keeps no data of its own: everything it shows comes from
     * the Aira API, and the database schema is set up from the API project
     * (database/sql/aira_schema.sql). Nothing to seed here.
     */
    public function run(): void
    {
        //
    }
}
