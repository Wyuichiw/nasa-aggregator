<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Mars', 'Galaxy', 'Telescopes', 'Moon', 'Nebula'] as $name) {
            Tag::firstOrCreate(['name' => $name]);
        }
    }
}
