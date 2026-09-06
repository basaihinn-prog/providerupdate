<?php

namespace App\Http\Controllers\Api\V1\Game;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BankResource;
use App\Http\Resources\Api\GameListResource;
use App\Http\Resources\Api\GameProviderResource;
use App\Http\Resources\Api\GameTypeResource;
use App\Models\Admin\Bank;
use App\Models\GameList;
use App\Models\GameType;
use App\Models\PaymentType;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GSCPlusProviderController extends Controller
{
    use HttpResponses;

    public function gameTypes()
    {
        $providerKeys = $this->configuredProviderKeys();

        $types = GameType::with(['products' => function ($query) use ($providerKeys) {
            $query->where('status', 1)
                ->whereIn(DB::raw('LOWER(provider)'), $providerKeys)
                ->orderBy('order', 'asc');
        }])
            ->where('status', 1)
            ->whereHas('products', function ($query) use ($providerKeys) {
                $query->where('status', 1)
                    ->whereIn(DB::raw('LOWER(provider)'), $providerKeys);
            })
            ->get();

        return $this->success(GameTypeResource::collection($types));
    }

    public function providers($type)
    {
        $providerKeys = $this->configuredProviderKeys();

        $providers = GameType::with(['products' => function ($query) use ($providerKeys) {
            $query->where('game_list_status', 1)
                ->whereIn(DB::raw('LOWER(provider)'), $providerKeys)
                ->orderBy('order', 'asc');
        }])
            ->where('code', $type)
            ->where('status', 1)
            ->whereHas('products', function ($query) use ($providerKeys) {
                $query->where('game_list_status', 1)
                    ->whereIn(DB::raw('LOWER(provider)'), $providerKeys);
            })
            ->first();

        if ($providers) {
            return $this->success(new GameProviderResource($providers));
        }

        return $this->error('', 'Providers Not Found', 404);
    }

    public function gameLists($type, $provider, Request $request)
    {
        $providerKeys = $this->configuredProviderKeys();

        $gameLists = GameList::with('product')
            ->where('product_id', $provider)
            ->where('game_type_id', $type)
            ->where('is_active', 1)
            ->whereHas('product', function ($query) use ($providerKeys) {
                $query->whereIn(DB::raw('LOWER(provider)'), $providerKeys);
            })
            ->orderBy('order', 'asc')
            ->where('game_name', 'like', '%'.($request->game_name ?? '').'%')
            ->paginate(20);

        return $this->success(GameListResource::collection($gameLists));
    }

    public function NewgameLists($type, $provider, Request $request)
    {
        $providerKeys = $this->configuredProviderKeys();

        $gameLists = GameList::with('product')
            ->where('product_code', $provider)
            ->where('game_type_id', $type)
            ->where('is_active', 1)
            ->whereHas('product', function ($query) use ($providerKeys) {
                $query->whereIn(DB::raw('LOWER(provider)'), $providerKeys);
            })
            ->orderBy('order', 'asc')
            ->where('game_name', 'like', '%'.($request->game_name ?? '').'%')
            ->paginate(20);

        return $this->success(GameListResource::collection($gameLists));
    }

    public function hotGameLists()
    {
        $providerKeys = $this->configuredProviderKeys();

        $hotGames = GameList::with('product')
            ->where('hot_status', '1')
            ->where('is_active', 1)
            ->whereHas('product', function ($query) use ($providerKeys) {
                $query->whereIn(DB::raw('LOWER(provider)'), $providerKeys);
            })
            ->get();

        return $this->success(GameListResource::collection($hotGames));
    }

    public function banks()
    {
        $player = Auth::user();
        $data = Bank::where('agent_id', $player->agent_id)->get();

        return $this->success(BankResource::collection($data), 'Payment Type list successfule');
    }

    public function paymentType()
    {
        $data = PaymentType::all();

        return $this->success($data);
    }

    private function configuredProviderKeys(): array
    {
        $keys = array_map(
            static fn ($key) => strtolower((string) $key),
            array_keys(config('game_providers.drivers', []))
        );

        return $keys === [] ? ['__none__'] : $keys;
    }
}
