<?php

use App\Http\Controllers\Api\Player\GameLogController;
use App\Http\Controllers\Api\Player\TransactionController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\ShootGame\UserController;
use App\Http\Controllers\Api\ShootGame\PythonGameController;
use App\Http\Controllers\Api\TestDBTransactionController;
use App\Http\Controllers\Api\V1\BannerController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DepositRequestController;
use App\Http\Controllers\Api\V1\DigitGame\DigitBetController;
use App\Http\Controllers\Api\V1\DigitGame\DigitSlotController;
use App\Http\Controllers\Api\V1\Game\GSCPlusProviderController;
use App\Http\Controllers\Api\V1\Game\LaunchGameController;
use App\Http\Controllers\Api\V1\Game\FishLaunchTicketController;
use App\Http\Controllers\Api\V1\Game\FishSettlementController;
use App\Http\Controllers\Api\V1\Game\PpSlotWalletController;
use App\Http\Controllers\Api\V1\Game\ShanLaunchGameController;
use App\Http\Controllers\Api\V1\PromotionController;
use App\Http\Controllers\Api\V1\Shan\ShanGetBalanceController;
use App\Http\Controllers\Api\V1\Shan\ShanTransactionController;
use App\Http\Controllers\Api\V1\TwoDigit\TwoDigitBetController;
use App\Http\Controllers\Api\V1\WithDrawRequestController;
use App\Http\Controllers\Api\V2\Shan\ShankomeeGetBalanceController;
use App\Http\Controllers\Api\V1\Game\BuffaloGameController;
use App\Http\Controllers\Api\V1\Game\BuffaloLegacyGameController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/player-change-password', [AuthController::class, 'playerChangePassword']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::post('/test-db-transaction', [TestDBTransactionController::class, 'PurseService']);

// Signed server-to-server fish settlement. Not a browser/player endpoint.
Route::post('/fish/settle', [FishSettlementController::class, 'settle'])->middleware('throttle:120,1');

// External PPSLOT / updatesky wallet protocol; not used by fish1.
Route::prefix('provider/ppslot')->group(function () {
    Route::post('Cash/Get', [PpSlotWalletController::class, 'balance']);
    Route::post('Cash/TransferInOut', [PpSlotWalletController::class, 'transfer']);
});

Route::post('/transactions', [ShanTransactionController::class, 'ShanTransactionCreate'])->middleware('transaction');

Route::middleware('auth:sanctum')->group(function () {
    // Dedicated fish1 session bootstrap. It does not settle gameplay transactions.
    Route::post('/fish/launch-ticket', [FishLaunchTicketController::class, 'issue'])->middleware('throttle:10,1');

    // Opt-in server-owned simulation. Wallet settlement remains disabled until canonical wallet integration is tested.
    Route::post('/fish/engine/sessions', [FishEngineController::class, 'start'])->middleware('throttle:30,1');
    Route::get('/fish/engine/sessions/{sessionId}', [FishEngineController::class, 'state'])->middleware('throttle:120,1');
    Route::post('/fish/engine/sessions/{sessionId}/commands', [FishEngineController::class, 'command'])->middleware('throttle:120,1');

    // Owner-only RTP reporting/configuration. Targets are audit metadata, not outcome overrides.
    Route::get('/fish/admin/rtp', [FishSettlementController::class, 'report'])->middleware('throttle:30,1');
    Route::put('/fish/admin/rtp/{gameKey}', [FishSettlementController::class, 'updateTarget'])
        ->where('gameKey', '[A-Za-z0-9._:-]+')->middleware('throttle:10,1');

    Route::post('/user/balance', [UserController::class, 'updateBalance']);
    Route::post('/game/log', [PythonGameController::class, 'storeGameLog']);
    Route::post('/game/stats', [PythonGameController::class, 'updateGameStats']);

    // Provider-agnostic external provider launch endpoints.
    Route::post('/games/launch', [LaunchGameController::class, 'launchGame']);
    Route::post('/seamless/launch-game', [LaunchGameController::class, 'launchGame']);

    Route::post('exchange-main-to-game', [TransactionController::class, 'MainToGame']);
    Route::post('exchange-game-to-main', [TransactionController::class, 'GameToMain']);
    Route::get('exchange-transactions-log', [TransactionController::class, 'exchangeTransactionLog']);

    Route::get('user', [AuthController::class, 'getUser']);
    Route::get('/banks', [GSCPlusProviderController::class, 'banks']);

    Route::post('depositfinicial', [DepositRequestController::class, 'FinicialDeposit']);
    Route::get('depositlogfinicial', [DepositRequestController::class, 'log']);
    Route::get('paymentTypefinicial', [GSCPlusProviderController::class, 'paymentType']);
    Route::post('withdrawfinicial', [WithDrawRequestController::class, 'FinicalWithdraw']);
    Route::get('withdrawlogfinicial', [WithDrawRequestController::class, 'log']);

    Route::get('/player/game-logs', [GameLogController::class, 'index']);

    Route::post('/twod-bet', [TwoDigitBetController::class, 'store']);
    Route::get('/twod-bet-slips', [TwoDigitBetController::class, 'myBetSlips']);
    Route::get('/evening-twod-bet-slips', [TwoDigitBetController::class, 'eveningSessionSlip']);
    Route::get('/two-d-daily-winners', [TwoDigitBetController::class, 'dailyWinners']);

    Route::post('shan-launch-game', [ShanLaunchGameController::class, 'launch']);
    Route::get('contact', [ContactController::class, 'get']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    Route::post('/digitbet', [DigitBetController::class, 'store']);
    Route::get('/digitbet/history', [DigitBetController::class, 'history']);
    Route::post('/digit-slot/bet', [DigitSlotController::class, 'bet']);
});

Route::get('promotion', [PromotionController::class, 'index']);
Route::get('winnerText', [BannerController::class, 'winnerText']);
Route::get('banner_Text', [BannerController::class, 'bannerText']);
Route::get('popup-ads-banner', [BannerController::class, 'AdsBannerIndex']);
Route::get('banner', [BannerController::class, 'index']);
Route::get('videoads', [BannerController::class, 'ApiVideoads']);
Route::get('toptenwithdraw', [BannerController::class, 'TopTen']);

Route::get('/game_types', [GSCPlusProviderController::class, 'gameTypes']);
Route::get('/providers/{type}', [GSCPlusProviderController::class, 'providers']);
Route::get('/game_lists/{type}/{provider}', [GSCPlusProviderController::class, 'gameLists']);
Route::get('/game_lists/by-product-code/{type}/{productcode}', [GSCPlusProviderController::class, 'NewgameLists']);
Route::get('/hot_game_lists', [GSCPlusProviderController::class, 'hotGameLists']);

Route::prefix('shan')->group(function () {
    Route::post('getbalance', [ShanGetBalanceController::class, 'getBalance']);
});

Route::prefix('provider/shan')->group(function () {
    Route::post('ShanGetBalances', [ShankomeeGetBalanceController::class, 'shangetbalance']);
    Route::post('ShanLaunchGame', [ShankomeeGetBalanceController::class, 'LaunchGame']);
});

// Legacy Buffalo Cocos contract. Bet is explicitly fail-closed until authoritative settlement is integrated.
Route::prefix('buffalo/legacy')->middleware('throttle:120,1')->group(function () {
    Route::post('/start_info', [BuffaloLegacyGameController::class, 'startInfo']);
    Route::post('/ping', [BuffaloLegacyGameController::class, 'ping']);
    Route::post('/bet', [BuffaloLegacyGameController::class, 'bet']);
    Route::post('/exit', [BuffaloLegacyGameController::class, 'exit']);
});

// Buffalo API remains isolated and is not part of fish1 integration.
Route::prefix('buffalo')->group(function () {
    Route::post('/get-user-balance', [BuffaloGameController::class, 'getUserBalance']);
    Route::post('/change-balance', [BuffaloGameController::class, 'changeBalance']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/game-auth', [BuffaloGameController::class, 'generateGameAuth']);
        Route::post('/game-url', [BuffaloGameController::class, 'generateGameUrl']);
        Route::post('/launch-game', [BuffaloGameController::class, 'launchGame']);
    });
});
