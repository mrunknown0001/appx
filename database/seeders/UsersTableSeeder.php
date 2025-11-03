<?php

namespace Database\Seeders;

use Filament\Forms\Components\Builder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            // [
            //     'employee_id' => 'EMP0001',
            //     'name' => 'Admin Admin',
            //     'email' => 'admin@app.com',
            //     'password' => bcrypt('password'),
            //     'status' => 'active',
            //     'role' => 'admin'
            // ],
            // [
            //     'employee_id' => 'EMP0002',
            //     'name' => 'Man Nager',
            //     'email' => 'manager@app.com',
            //     'password' => bcrypt('password'),
            //     'status' => 'active',
            //     'role' => 'manager'
            // ],
            [
                'employee_id' => '00000',
                'name' => 'Super Admin',
                'email' => 'super@app.com',
                'password' => bcrypt('password'),
                'status' => 'active',
                'role' => 'superadmin'
            ],
        ]);
    }


}
