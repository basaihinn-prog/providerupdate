<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed core application data only.
     *
     * External game-provider products and game catalogs are intentionally not
     * seeded here. PPSLOT / updatesky is synchronized from its live API with:
     *
     *   php artisan provider:sync-games ppslot --language=my
     */
    public function run(): void
    {
        $this->call([
            PermissionsTableSeeder::class,
            SubAgentPermissionSeeder::class,
            RolesTableSeeder::class,
            PermissionRoleTableSeeder::class,
            UsersTableSeeder::class,
            RoleUserTableSeeder::class,
            BannerTextSeeder::class,
            PaymentTypeTableSeeder::class,
            BankTableSeeder::class,
            WinnerTextSeeder::class,
            TopTenWithdrawSeeder::class,
            ContactTypeSeeder::class,
            ContactSeeder::class,
            PromotionSeeder::class,
            AdsVedioSeeder::class,

            // Local / first-party game data.
            PoneWineBetsTableSeeder::class,
            PoneWinePlayerBetsTableSeeder::class,
            PoneWineBetInfosTableSeeder::class,
            GameTypeTableSeeder::class,
            HeadCloseSeeder::class,
            ChooseDigitSeeder::class,
            BattleTableSeeder::class,
            OperatorSeeder::class,
            TwoDLimitSeeder::class,
            ShanProductSeeder::class,
        ]);
    }
}
