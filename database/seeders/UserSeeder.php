<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Superadmin ──────────────────────────────────────────────────────
        User::create([
            'name'        => 'Super Admin',
            'email'       => 'superadmin@pos.com',
            'password'    => Hash::make('password123'),
            'role'        => UserRole::SUPERADMIN,
            'is_active'   => true,
            'business_id' => null,
            'outlet_id'   => null,
        ]);

        // ─── Business 1: Retail Fashion ──────────────────────────────────────
        $fashion = Business::create(['name' => 'Retail Fashion', 'code' => 'RTL']);

        $fashionOutlet1 = Outlet::create(['business_id' => $fashion->id, 'name' => 'Fashion - Pusat',   'code' => 'RTL-01']);
        $fashionOutlet2 = Outlet::create(['business_id' => $fashion->id, 'name' => 'Fashion - Selatan', 'code' => 'RTL-02']);
        $fashionOutlet3 = Outlet::create(['business_id' => $fashion->id, 'name' => 'Fashion - Timur',   'code' => 'RTL-03']);

        User::create([
            'name' => 'Owner Fashion', 'email' => 'owner@fashion.com',
            'password' => Hash::make('password123'), 'role' => UserRole::OWNER,
            'is_active' => true, 'business_id' => $fashion->id, 'outlet_id' => null,
        ]);

        // Admin per outlet
        foreach ([
            ['Admin Fashion Pusat',   'admin.pusat@fashion.com',   $fashionOutlet1->id],
            ['Admin Fashion Selatan', 'admin.selatan@fashion.com', $fashionOutlet2->id],
            ['Admin Fashion Timur',   'admin.timur@fashion.com',   $fashionOutlet3->id],
        ] as [$name, $email, $outletId]) {
            User::create([
                'name' => $name, 'email' => $email,
                'password' => Hash::make('password123'), 'role' => UserRole::ADMIN,
                'is_active' => true, 'business_id' => $fashion->id, 'outlet_id' => $outletId,
            ]);
        }

        // Kasir per outlet (2 kasir per outlet)
        foreach ([
            ['Kasir Fashion A1', 'kasir.a1@fashion.com', '111111', $fashionOutlet1->id],
            ['Kasir Fashion A2', 'kasir.a2@fashion.com', '111112', $fashionOutlet1->id],
            ['Kasir Fashion B1', 'kasir.b1@fashion.com', '111121', $fashionOutlet2->id],
            ['Kasir Fashion B2', 'kasir.b2@fashion.com', '111122', $fashionOutlet2->id],
            ['Kasir Fashion C1', 'kasir.c1@fashion.com', '111131', $fashionOutlet3->id],
            ['Kasir Fashion C2', 'kasir.c2@fashion.com', '111132', $fashionOutlet3->id],
        ] as [$name, $email, $pin, $outletId]) {
            User::create([
                'name' => $name, 'email' => $email,
                'password' => Hash::make('password123'), 'pin' => Hash::make($pin),
                'role' => UserRole::CASHIER, 'is_active' => true,
                'business_id' => $fashion->id, 'outlet_id' => $outletId,
            ]);
        }

        // ─── Business 2: Elektronik Store ────────────────────────────────────
        $elektro = Business::create(['name' => 'Elektronik Store', 'code' => 'ELK']);

        $elektroOutlet1 = Outlet::create(['business_id' => $elektro->id, 'name' => 'Elektro - Mall Utama', 'code' => 'ELK-01']);
        $elektroOutlet2 = Outlet::create(['business_id' => $elektro->id, 'name' => 'Elektro - Ruko Barat', 'code' => 'ELK-02']);

        User::create([
            'name' => 'Owner Elektro', 'email' => 'owner@elektro.com',
            'password' => Hash::make('password123'), 'role' => UserRole::OWNER,
            'is_active' => true, 'business_id' => $elektro->id, 'outlet_id' => null,
        ]);

        foreach ([
            ['Admin Elektro Mall', 'admin.mall@elektro.com', $elektroOutlet1->id],
            ['Admin Elektro Ruko', 'admin.ruko@elektro.com', $elektroOutlet2->id],
        ] as [$name, $email, $outletId]) {
            User::create([
                'name' => $name, 'email' => $email,
                'password' => Hash::make('password123'), 'role' => UserRole::ADMIN,
                'is_active' => true, 'business_id' => $elektro->id, 'outlet_id' => $outletId,
            ]);
        }

        foreach ([
            ['Kasir Elektro A1', 'kasir.a1@elektro.com', '222111', $elektroOutlet1->id],
            ['Kasir Elektro A2', 'kasir.a2@elektro.com', '222112', $elektroOutlet1->id],
            ['Kasir Elektro B1', 'kasir.b1@elektro.com', '222121', $elektroOutlet2->id],
        ] as [$name, $email, $pin, $outletId]) {
            User::create([
                'name' => $name, 'email' => $email,
                'password' => Hash::make('password123'), 'pin' => Hash::make($pin),
                'role' => UserRole::CASHIER, 'is_active' => true,
                'business_id' => $elektro->id, 'outlet_id' => $outletId,
            ]);
        }

        // ─── Business 3: Beauty & Skincare ───────────────────────────────────
        $beauty = Business::create(['name' => 'Beauty & Skincare', 'code' => 'BTY']);

        $beautyOutlet1 = Outlet::create(['business_id' => $beauty->id, 'name' => 'Beauty - Cabang Utara',   'code' => 'BTY-01']);
        $beautyOutlet2 = Outlet::create(['business_id' => $beauty->id, 'name' => 'Beauty - Cabang Selatan', 'code' => 'BTY-02']);

        User::create([
            'name' => 'Owner Beauty', 'email' => 'owner@beauty.com',
            'password' => Hash::make('password123'), 'role' => UserRole::OWNER,
            'is_active' => true, 'business_id' => $beauty->id, 'outlet_id' => null,
        ]);

        foreach ([
            ['Admin Beauty Utara',   'admin.utara@beauty.com',   $beautyOutlet1->id],
            ['Admin Beauty Selatan', 'admin.selatan@beauty.com', $beautyOutlet2->id],
        ] as [$name, $email, $outletId]) {
            User::create([
                'name' => $name, 'email' => $email,
                'password' => Hash::make('password123'), 'role' => UserRole::ADMIN,
                'is_active' => true, 'business_id' => $beauty->id, 'outlet_id' => $outletId,
            ]);
        }

        foreach ([
            ['Kasir Beauty A1', 'kasir.a1@beauty.com', '333111', $beautyOutlet1->id],
            ['Kasir Beauty A2', 'kasir.a2@beauty.com', '333112', $beautyOutlet1->id],
            ['Kasir Beauty B1', 'kasir.b1@beauty.com', '333121', $beautyOutlet2->id],
            ['Kasir Beauty B2', 'kasir.b2@beauty.com', '333122', $beautyOutlet2->id],
        ] as [$name, $email, $pin, $outletId]) {
            User::create([
                'name' => $name, 'email' => $email,
                'password' => Hash::make('password123'), 'pin' => Hash::make($pin),
                'role' => UserRole::CASHIER, 'is_active' => true,
                'business_id' => $beauty->id, 'outlet_id' => $outletId,
            ]);
        }
    }
}