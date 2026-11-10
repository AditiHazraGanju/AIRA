<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatesTableSeeder extends Seeder
{
    public function run()
    {
        $states = [
            // Union Territories
            ['state_name' => 'Andaman and Nicobar Islands', 'state_code' => 'IN-AN'],
            ['state_name' => 'Chandigarh', 'state_code' => 'IN-CH'],
            ['state_name' => 'Dadra and Nagar Haveli and Daman and Diu', 'state_code' => 'IN-DH'],
            ['state_name' => 'Delhi', 'state_code' => 'IN-DL'],
            ['state_name' => 'Jammu and Kashmir', 'state_code' => 'IN-JK'],
            ['state_name' => 'Ladakh', 'state_code' => 'IN-LA'],
            ['state_name' => 'Lakshadweep', 'state_code' => 'IN-LD'],
            ['state_name' => 'Puducherry', 'state_code' => 'IN-PY'],

            // States
            ['state_name' => 'Andhra Pradesh', 'state_code' => 'IN-AP'],
            ['state_name' => 'Arunachal Pradesh', 'state_code' => 'IN-AR'],
            ['state_name' => 'Assam', 'state_code' => 'IN-AS'],
            ['state_name' => 'Bihar', 'state_code' => 'IN-BR'],
            ['state_name' => 'Chhattisgarh', 'state_code' => 'IN-CG'],
            ['state_name' => 'Goa', 'state_code' => 'IN-GA'],
            ['state_name' => 'Gujarat', 'state_code' => 'IN-GJ'],
            ['state_name' => 'Haryana', 'state_code' => 'IN-HR'],
            ['state_name' => 'Himachal Pradesh', 'state_code' => 'IN-HP'],
            ['state_name' => 'Jharkhand', 'state_code' => 'IN-JH'],
            ['state_name' => 'Karnataka', 'state_code' => 'IN-KA'],
            ['state_name' => 'Kerala', 'state_code' => 'IN-KL'],
            ['state_name' => 'Madhya Pradesh', 'state_code' => 'IN-MP'],
            ['state_name' => 'Maharashtra', 'state_code' => 'IN-MH'],
            ['state_name' => 'Manipur', 'state_code' => 'IN-MN'],
            ['state_name' => 'Meghalaya', 'state_code' => 'IN-ML'],
            ['state_name' => 'Mizoram', 'state_code' => 'IN-MZ'],
            ['state_name' => 'Nagaland', 'state_code' => 'IN-NL'],
            ['state_name' => 'Odisha', 'state_code' => 'IN-OD'],
            ['state_name' => 'Punjab', 'state_code' => 'IN-PB'],
            ['state_name' => 'Rajasthan', 'state_code' => 'IN-RJ'],
            ['state_name' => 'Sikkim', 'state_code' => 'IN-SK'],
            ['state_name' => 'Tamil Nadu', 'state_code' => 'IN-TN'],
            ['state_name' => 'Telangana', 'state_code' => 'IN-TS'],
            ['state_name' => 'Tripura', 'state_code' => 'IN-TR'],
            ['state_name' => 'Uttar Pradesh', 'state_code' => 'IN-UP'],
            ['state_name' => 'Uttarakhand', 'state_code' => 'IN-UK'],
            ['state_name' => 'West Bengal', 'state_code' => 'IN-WB'],
        ];

        // Idempotent: safe to run again on a database that already has the states.
        foreach ($states as $state) {
            DB::table('saathi_states')->updateOrInsert(
                ['state_code' => $state['state_code']],
                ['state_name' => $state['state_name']]
            );
        }
    }
}
