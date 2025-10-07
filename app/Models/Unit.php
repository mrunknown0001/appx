<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Unit extends Model implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'abbreviation',
        'description'
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // Accessor for formatted display
    public function getFullNameAttribute(): string
    {
        return "{$this->name} ({$this->abbreviation})";
    }

    // Scope for searching
    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', "%{$search}%")
                    ->orWhere('abbreviation', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
    }

    // Common units seeder data
    public static function getCommonUnits(): array
    {
        return [
            ['name' => 'Pieces', 'abbreviation' => 'pcs', 'description' => 'Individual items or tablets'],
            ['name' => 'Bottles', 'abbreviation' => 'btl', 'description' => 'Liquid medications in bottles'],
            ['name' => 'Strips', 'abbreviation' => 'strip', 'description' => 'Blister packs or strips'],
            ['name' => 'Boxes', 'abbreviation' => 'box', 'description' => 'Packaged items in boxes'],
            ['name' => 'Vials', 'abbreviation' => 'vial', 'description' => 'Small containers for injections'],
            ['name' => 'Tubes', 'abbreviation' => 'tube', 'description' => 'Ointments and creams in tubes'],
            ['name' => 'Sachets', 'abbreviation' => 'sachet', 'description' => 'Powder medications in sachets'],
            ['name' => 'Ampoules', 'abbreviation' => 'amp', 'description' => 'Glass containers for injections'],
            ['name' => 'Milliliters', 'abbreviation' => 'ml', 'description' => 'Volume measurement'],
            ['name' => 'Grams', 'abbreviation' => 'g', 'description' => 'Weight measurement'],
        ];
    }
}