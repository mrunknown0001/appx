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
            [
                'first_name' => 'Admin',
                'last_name' => 'Admin',
                'email' => 'admin@app.com',
                'password' => bcrypt('password'),
                'is_admin' => 1,
                'is_active' => 1,
                'role' => 'admin'
            ]
        ]);
    }


}
