<?php

namespace App\Console\Commands;

use App\Models\GameList;
use App\Models\Product;
use App\Services\GameProviders\ProviderManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PpSlotHealthCheck extends Command
{
    protected $signature = 'provider:ppslot-health {--language=}';

    protected $description = 'Validate PPSLOT credentials, remote catalog access, local catalog state, and seamless-wallet schema';

    public function handle(ProviderManager $providers): int
    {
        $config = (array) config('game_providers.drivers.ppslot', []);
        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $appId = (string) ($config['app_id'] ?? '');
        $appSecret = (string) ($config['app_secret'] ?? '');
        $language = (string) ($this->option('language') ?: ($config['language'] ?? 'my'));

        $failed = false;

        $this->components->twoColumnDetail('PPSLOT base URL', $baseUrl !== '' ? $baseUrl : '<fg=red>missing</>');
        $this->components->twoColumnDetail('PPSLOT AppID', $appId !== '' ? '<fg=green>configured</>' : '<fg=red>missing</>');
        $this->components->twoColumnDetail('PPSLOT AppSecret', $appSecret !== '' ? '<fg=green>configured</>' : '<fg=red>missing</>');

        if ($baseUrl === '' || $appId === '' || $appSecret === '') {
            $failed = true;
        }

        $callbackBase = rtrim((string) config('app.url'), '/').'/api/provider/ppslot';
        $this->components->twoColumnDetail('Callback base', $callbackBase);
        $this->components->twoColumnDetail('Cash/Get', $callbackBase.'/Cash/Get');
        $this->components->twoColumnDetail('Cash/TransferInOut', $callbackBase.'/Cash/TransferInOut');

        if (! Schema::hasTable('provider_wallet_transactions')) {
            $this->components->twoColumnDetail('Wallet transaction table', '<fg=red>missing - run migrations</>');
            $failed = true;
        } else {
            $this->components->twoColumnDetail('Wallet transaction table', '<fg=green>present</>');
        }

        $productCount = Product::query()
            ->whereRaw('LOWER(provider) = ?', ['ppslot'])
            ->count();
        $gameCount = GameList::query()
            ->whereRaw('LOWER(provider) = ?', ['ppslot'])
            ->where('is_active', 1)
            ->count();

        $this->components->twoColumnDetail('Local PPSLOT products', (string) $productCount);
        $this->components->twoColumnDetail('Local active PPSLOT games', (string) $gameCount);

        if ($failed) {
            $this->error('Local PPSLOT configuration is incomplete. Fix the values above before testing the remote API.');

            return self::FAILURE;
        }

        try {
            $games = $providers->driver('ppslot')->games($language);
            $this->components->twoColumnDetail('Remote API authentication', '<fg=green>OK</>');
            $this->components->twoColumnDetail('Remote catalog games', (string) count($games));
        } catch (Throwable $e) {
            $this->components->twoColumnDetail('Remote API authentication', '<fg=red>FAILED</>');
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($gameCount === 0) {
            $this->warn("Remote API works but no local PPSLOT games are active. Run: php artisan provider:sync-games ppslot --language={$language}");
        }

        $this->info('PPSLOT integration health check passed.');

        return self::SUCCESS;
    }
}
