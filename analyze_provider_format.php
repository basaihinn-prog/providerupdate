<?php
/**
 * Analyze Provider URL Format
 * Compare our generated URLs with provider examples
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\BuffaloGameService;
use App\Models\User;

echo "🔍 Buffalo Provider URL Format Analysis\n";
echo "=======================================\n\n";

echo "1. Provider Example URLs:\n";
$providerExamples = [
    'Room 1 (50): http://prime7.wlkfkskakdf.com/?gameId=23&roomId=1&uid=gamffe9f7f3d520492ac89d3aac481f&token=7bb9dc9fe15e3c1370f036887fa02b36b6ba6dbb0406413a4067cd0f59b2a043&lobbyUrl=https%3A%2F%2Fafricanbuffalo.vip',
    'Room 2 (500): http://prime7.wlkfkskakdf.com/?gameId=23&roomId=2&uid=gamffe9f7f3d520492ac89d3aac481f&token=7bb9dc9fe15e3c1370f036887fa02b36b6ba6dbb0406413a4067cd0f59b2a043&lobbyUrl=https%3A%2F%2Fafricanbuffalo.vip',
    'Room 3 (5000): http://prime7.wlkfkskakdf.com/?gameId=23&roomId=3&uid=gamffe9f7f3d520492ac89d3aac481f&token=7bb9dc9fe15e3c1370f036887fa02b36b6ba6dbb0406413a4067cd0f59b2a043&lobbyUrl=https%3A%2F%2Fafricanbuffalo.vip',
    'Room 4 (10000): http://prime7.wlkfkskakdf.com/?gameId=23&roomId=4&uid=gamffe9f7f3d520492ac89d3aac481f&token=7bb9dc9fe15e3c1370f036887fa02b36b6ba6dbb0406413a4067cd0f59b2a043&lobbyUrl=https%3A%2F%2Fafricanbuffalo.vip'
];

foreach ($providerExamples as $example) {
    echo "   " . $example . "\n";
}

echo "\n2. Provider URL Structure Analysis:\n";
$providerUrl = 'http://prime7.wlkfkskakdf.com/?gameId=23&roomId=1&uid=gamffe9f7f3d520492ac89d3aac481f&token=7bb9dc9fe15e3c1370f036887fa02b36b6ba6dbb0406413a4067cd0f59b2a043&lobbyUrl=https%3A%2F%2Fafricanbuffalo.vip';
parse_str(parse_url($providerUrl, PHP_URL_QUERY), $providerParams);

echo "   Base URL: http://prime7.wlkfkskakdf.com/\n";
echo "   Game ID: " . $providerParams['gameId'] . "\n";
echo "   Room ID: " . $providerParams['roomId'] . "\n";
echo "   UID: " . $providerParams['uid'] . " (Length: " . strlen($providerParams['uid']) . ")\n";
echo "   Token: " . substr($providerParams['token'], 0, 20) . "... (Length: " . strlen($providerParams['token']) . ")\n";
echo "   Lobby URL: " . urldecode($providerParams['lobbyUrl']) . "\n\n";

echo "3. Our Generated URLs:\n";
$testUser = User::first();
if ($testUser) {
    $auth = BuffaloGameService::generateBuffaloAuth($testUser);
    
    for ($roomId = 1; $roomId <= 4; $roomId++) {
        $gameUrl = BuffaloGameService::generateGameUrl($testUser, $roomId);
        $fullUrl = $gameUrl . '&uid=' . $auth['uid'] . '&token=' . $auth['token'];
        
        echo "   Room $roomId: " . $fullUrl . "\n";
    }
    
    echo "\n4. Our URL Structure Analysis:\n";
    $ourUrl = $fullUrl;
    parse_str(parse_url($ourUrl, PHP_URL_QUERY), $ourParams);
    
    echo "   Base URL: " . parse_url($ourUrl, PHP_URL_SCHEME) . '://' . parse_url($ourUrl, PHP_URL_HOST) . "\n";
    echo "   Game ID: " . $ourParams['gameId'] . "\n";
    echo "   Room ID: " . $ourParams['roomId'] . "\n";
    echo "   UID: " . $ourParams['uid'] . " (Length: " . strlen($ourParams['uid']) . ")\n";
    echo "   Token: " . substr($ourParams['token'], 0, 20) . "... (Length: " . strlen($ourParams['token']) . ")\n";
    echo "   Lobby URL: " . urldecode($ourParams['lobbyUrl']) . "\n\n";
    
    echo "5. Format Comparison:\n";
    echo "   ✅ Base URL: " . (parse_url($ourUrl, PHP_URL_HOST) === 'prime7.wlkfkskakdf.com' ? 'MATCH' : 'DIFFERENT') . "\n";
    echo "   ✅ Game ID: " . ($ourParams['gameId'] === $providerParams['gameId'] ? 'MATCH' : 'DIFFERENT') . "\n";
    echo "   ✅ Room ID: " . ($ourParams['roomId'] === $providerParams['roomId'] ? 'MATCH' : 'DIFFERENT') . "\n";
    echo "   ✅ Lobby URL: " . (urldecode($ourParams['lobbyUrl']) === urldecode($providerParams['lobbyUrl']) ? 'MATCH' : 'DIFFERENT') . "\n";
    echo "   ✅ UID Length: " . (strlen($ourParams['uid']) === strlen($providerParams['uid']) ? 'MATCH' : 'DIFFERENT') . "\n";
    echo "   ✅ Token Length: " . (strlen($ourParams['token']) === strlen($providerParams['token']) ? 'MATCH' : 'DIFFERENT') . "\n\n";
    
    echo "6. Provider UID/Token Analysis:\n";
    echo "   Provider UID: " . $providerParams['uid'] . "\n";
    echo "   Provider Token: " . $providerParams['token'] . "\n";
    echo "   Our UID: " . $auth['uid'] . "\n";
    echo "   Our Token: " . $auth['token'] . "\n\n";
    
    echo "📝 Note: Provider uses specific UID/token values for examples.\n";
    echo "   Our system generates unique UID/token for each user (which is correct).\n";
    echo "   The format and length should match, but values will be different per user.\n";
    
} else {
    echo "❌ No users found in database\n";
}
