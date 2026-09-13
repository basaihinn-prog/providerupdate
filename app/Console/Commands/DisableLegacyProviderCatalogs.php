<?php

namespace App\Console\Commands;

use App\Models\GameList;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DisableLegacyProviderCatalogs extends Command
{
    protected $signature = 'provider:disable-legacy
        {--apply : Persist the changes; without this flag the command is a dry run}
        {--keep=ppslot,shankomee : Comma-separated provider keys that must remain enabled}';

    protected $description = 'Disable legacy external provider catalog records without deleting historical data';

    public function handle(): int
    {
        $keepProviders = array_values(array_unique(array_filter(array_map(
            static fn ($provider) => strtolower(trim((string) $provider)),
            explode(',', (string) $this->option('keep'))
        ))));

        if (! in_array('ppslot', $keepProviders, true)) {
            $keepProviders[] = 'ppslot';
        }

        $products = Product::query()
            ->whereNotNull('provider')
            ->where('provider', '<>', '')
            ->whereNotIn(DB::raw('LOWER(provider)'), $keepProviders)
            ->get(['id', 'provider', 'product_code', 'product_name', 'status', 'game_list_status']);

        $productIds = $products->pluck('id')->all();

        $gameCount = $productIds === []
            ? 0
            : GameList::query()->whereIn('product_id', $productIds)->count();

        $this->info('Legacy external provider catalog audit');
        $this->line('Providers preserved: '.implode(', ', $keepProviders));
        $this->line('Products to disable: '.$products->count());
        $this->line('Games to disable: '.$gameCount);

        if ($products->isNotEmpty()) {
            $this->table(
                ['ID', 'Provider', 'Product Code', 'Product Name', 'Status', 'Game List Status'],
                $products->map(fn ($product) => [
                    $product->id,
                    $product->provider,
                    $product->product_code,
                    $product->product_name,
                    $product->status,
                    $product->game_list_status,
                ])->all()
            );
        }

        if (! $this->option('apply')) {
            $this->warn('Dry run only. Re-run with --apply after reviewing the product list.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($productIds): void {
            if ($productIds === []) {
                return;
            }

            Product::query()
                ->whereIn('id', $productIds)
                ->update([
                    'status' => 0,
                    'game_list_status' => 0,
                ]);

            GameList::query()
                ->whereIn('product_id', $productIds)
                ->update([
                    'is_active' => false,
                    'game_list_status' => false,
                ]);
        }, 3);

        $this->info('Legacy provider catalog records were disabled. Historical records were not deleted.');

        return self::SUCCESS;
    }
}
