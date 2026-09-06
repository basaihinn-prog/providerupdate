<?php

namespace App\Console\Commands;

use App\Models\GameList;
use App\Models\Product;
use App\Services\GameProviders\ProviderManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SyncPpSlotGames extends Command
{
    protected $signature = 'provider:sync-games {provider=ppslot} {--language=en}';

    protected $description = 'Sync a configured provider game catalog into game_lists';

    public function handle(ProviderManager $providers): int
    {
        $providerKey = strtolower((string) $this->argument('provider'));

        if ($providerKey !== 'ppslot') {
            $this->error('Catalog normalization is not configured for provider ['.$providerKey.'] yet.');

            return self::FAILURE;
        }

        $config = config('game_providers.drivers.ppslot');
        $driver = $providers->driver('ppslot');
        $games = $driver->games((string) $this->option('language'));

        $product = $this->resolveProduct($config);
        $typeMap = $config['game_type_map'] ?? [];
        $productCode = (int) ($config['product_code'] ?? 900001);
        $currency = (string) ($config['currency'] ?? 'MMK');

        $synced = 0;
        $skipped = 0;
        $seenCodes = [];

        DB::transaction(function () use ($games, $product, $typeMap, $productCode, $currency, &$synced, &$skipped, &$seenCodes) {
            foreach ($games as $index => $remote) {
                $gameCode = trim((string) ($remote['ID'] ?? ''));
                $gameName = trim((string) ($remote['Name'] ?? ''));
                $remoteType = isset($remote['Type']) ? (int) $remote['Type'] : null;
                $gameTypeId = $remoteType !== null ? ($typeMap[$remoteType] ?? null) : null;

                if ($gameCode === '' || $gameName === '' || ! $gameTypeId) {
                    $skipped++;
                    continue;
                }

                $seenCodes[] = $gameCode;

                GameList::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'game_code' => $gameCode,
                    ],
                    [
                        'game_name' => $gameName,
                        'game_type' => $this->typeName($remoteType),
                        'image_url' => (string) ($remote['IconUrl'] ?? ''),
                        'provider_product_id' => $product->provider_product_id,
                        'game_type_id' => $gameTypeId,
                        'product_code' => $productCode,
                        'support_currency' => $currency,
                        'status' => 'ACTIVATED',
                        'is_active' => true,
                        'provider' => 'ppslot',
                        'order' => $index,
                        'game_list_status' => true,
                    ]
                );

                $this->ensureProductGameType($product->id, $gameTypeId);
                $synced++;
            }

            if ($seenCodes !== []) {
                GameList::where('product_id', $product->id)
                    ->whereNotIn('game_code', $seenCodes)
                    ->update(['is_active' => false]);
            }
        }, 3);

        $this->info("PPSLOT sync complete: {$synced} synced, {$skipped} skipped, product_id={$product->id}.");

        return self::SUCCESS;
    }

    private function resolveProduct(array $config): Product
    {
        $configuredId = (int) ($config['product_id'] ?? 0);

        if ($configuredId > 0) {
            $product = Product::find($configuredId);

            if (! $product) {
                throw new RuntimeException("Configured PPSLOT_PRODUCT_ID [{$configuredId}] does not exist.");
            }

            if (strtolower((string) $product->provider) !== 'ppslot') {
                $product->provider = 'ppslot';
                $product->save();
            }

            return $product;
        }

        $productCode = (string) ($config['product_code'] ?? '900001');

        return Product::firstOrCreate(
            ['provider' => 'ppslot', 'product_code' => $productCode],
            [
                'currency' => (string) ($config['currency'] ?? 'MMK'),
                'status' => '1',
                'provider_id' => 0,
                'provider_product_id' => 0,
                'product_name' => 'PPSLOT',
                'game_type' => 'ALL',
                'product_title' => 'PPSLOT',
                'short_name' => 'PPSLOT',
                'order' => 0,
                'game_list_status' => 1,
            ]
        );
    }

    private function ensureProductGameType(int $productId, int $gameTypeId): void
    {
        $exists = DB::table('game_type_product')
            ->where('product_id', $productId)
            ->where('game_type_id', $gameTypeId)
            ->exists();

        if (! $exists) {
            DB::table('game_type_product')->insert([
                'product_id' => $productId,
                'game_type_id' => $gameTypeId,
                'image' => 'default.png',
                'rate' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function typeName(?int $type): string
    {
        return match ($type) {
            0 => 'SLOT',
            1 => 'FISHING',
            3 => 'CARD',
            4 => 'LOTTERY',
            default => 'OTHER',
        };
    }
}
