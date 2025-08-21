<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Unit;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            [
                'name' => 'Pieces',
                'abbreviation' => 'pcs',
                'description' => 'Individual items, tablets, or capsules'
            ],
            [
                'name' => 'Bottles',
                'abbreviation' => 'btl',
                'description' => 'Liquid medications in bottles'
            ],
            [
                'name' => 'Strips',
                'abbreviation' => 'strip',
                'description' => 'Blister packs or strips of tablets'
            ],
            [
                'name' => 'Boxes',
                'abbreviation' => 'box',
                'description' => 'Packaged items in boxes'
            ],
            [
                'name' => 'Vials',
                'abbreviation' => 'vial',
                'description' => 'Small containers for injections'
            ],
            [
                'name' => 'Tubes',
                'abbreviation' => 'tube',
                'description' => 'Ointments and creams in tubes'
            ],
            [
                'name' => 'Sachets',
                'abbreviation' => 'sachet',
                'description' => 'Powder medications in sachets'
            ],
            [
                'name' => 'Ampoules',
                'abbreviation' => 'amp',
                'description' => 'Glass containers for injections'
            ],
            [
                'name' => 'Milliliters',
                'abbreviation' => 'ml',
                'description' => 'Volume measurement for liquids'
            ],
            [
                'name' => 'Grams',
                'abbreviation' => 'g',
                'description' => 'Weight measurement for powders'
            ],
            [
                'name' => 'Liters',
                'abbreviation' => 'l',
                'description' => 'Large volume measurement'
            ],
            [
                'name' => 'Kilograms',
                'abbreviation' => 'kg',
                'description' => 'Large weight measurement'
            ],
            [
                'name' => 'Packs',
                'abbreviation' => 'pack',
                'description' => 'Multi-item packages'
            ],
            [
                'name' => 'Drops',
                'abbreviation' => 'drops',
                'description' => 'Eye drops or ear drops'
            ],
            [
                'name' => 'Inhalers',
                'abbreviation' => 'inhaler',
                'description' => 'Respiratory medication devices'
            ],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(
                ['abbreviation' => $unit['abbreviation']],
                $unit
            );
        }

        $this->command->info('Units seeded successfully!');
    }
}