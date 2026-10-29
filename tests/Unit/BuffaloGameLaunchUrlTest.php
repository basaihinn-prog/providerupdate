<?php

namespace Tests\\Unit;

use App\\Models\\User;
use App\\Services\\BuffaloGameService;
use RuntimeException;
use Tests\\TestCase;

class BuffaloGameLaunchUrlTest extends TestCase
{
    public function test_launch_url_fails_closed_when_local_asset_url_is_missing(): void
    {
        config(['services.buffalo.game_url' => null]);

        $this->expectException(RuntimeException::class);

        BuffaloGameService::generateGameUrl(new User(), 1, 'https://provider.example.test');
    }

    public function test_launch_url_uses_local_cocos_legacy_parameter_names(): void
    {
        config([
            'app.url' => 'https://api.example.test',
            'services.buffalo.game_url' => 'https://games.example.test/buffalo/index.html',
            'services.buffalo.game_code' => 'Buffalo',
        ]);

        $user = new User();
        $user->id = 42;
        $user->user_name = 'launch-test-user';

        $url = BuffaloGameService::generateGameUrl($user, 2, 'https://api.example.test');
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('https://games.example.test/buffalo/index.html', strtok($url, '?'));
        $this->assertSame('23', $query['gameId']);
        $this->assertSame('2', $query['roomId']);
        $this->assertSame('launch-test-user', BuffaloGameService::extractUserNameFromUid($query['id']));
        $this->assertNotEmpty($query['passcode']);
        $this->assertSame('Buffalo', $query['game']);
        $this->assertSame('api.example.test', $query['domain']);
        $this->assertArrayNotHasKey('uid', $query);
        $this->assertArrayNotHasKey('token', $query);
    }

    public function test_non_https_game_url_is_rejected_outside_local_testing(): void
    {
        app()->detectEnvironment(fn () => 'production');
        config(['services.buffalo.game_url' => 'http://games.example.test/buffalo/index.html']);

        $user = new User();
        $user->id = 42;
        $user->user_name = 'launch-test-user';

        $this->expectException(RuntimeException::class);

        BuffaloGameService::generateGameUrl($user, 1, 'https://api.example.test');
    }
}
