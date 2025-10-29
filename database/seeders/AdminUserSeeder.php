<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // نتأكد ما يتكرر المستخدم لو انعمل seeding أكثر من مرة
        if (!User::where('name', 'onn')->exists()) {
            User::create([
                'name'      => 'onn',
                'password'  => Hash::make('onn'),
                'privilege' => 4, // نخليه سوبر أدمن مثلاً
               
            ]);
        }
    }
}
