<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FishRtpController extends Controller
{
    public function index(Request $request)
    {
        $this->requireOwner($request);

        $configs = DB::table('fish_rtp_configs')->orderBy('game_key')->get()->map(function ($config) {
            $totals = DB::table('fish_game_rounds')
                ->where('game_key', $config->game_key)
                ->selectRaw('COUNT(*) as rounds, COALESCE(SUM(bet_amount), 0) as total_bets, COALESCE(SUM(payout_amount), 0) as total_payouts')
                ->first();

            $bets = (float) $totals->total_bets;
            $payouts = (float) $totals->total_payouts;
            $config->rounds = (int) $totals->rounds;
            $config->total_bets = $bets;
            $config->total_payouts = $payouts;
            $config->actual_rtp_percent = $bets > 0 ? round(($payouts / $bets) * 100, 4) : null;

            return $config;
        });

        $audits = DB::table('fish_rtp_config_audits')
            ->leftJoin('users', 'users.id', '=', 'fish_rtp_config_audits.actor_id')
            ->select('fish_rtp_config_audits.*', 'users.user_name as actor_name')
            ->orderByDesc('fish_rtp_config_audits.id')
            ->limit(50)
            ->get();

        return view('admin.fish-rtp.index', compact('configs', 'audits'));
    }

    public function store(Request $request)
    {
        $this->requireOwner($request);

        $data = $request->validate([
            'game_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'target_rtp_percent' => ['required', 'numeric', 'min:1', 'max:200'],
            'enabled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $targetBps = (int) round(((float) $data['target_rtp_percent']) * 100);
        $enabled = (bool) $data['enabled'];
        $actor = $request->user();

        DB::transaction(function () use ($data, $targetBps, $enabled, $actor) {
            $current = DB::table('fish_rtp_configs')
                ->where('game_key', $data['game_key'])
                ->lockForUpdate()
                ->first();

            $fromBps = $current ? (int) $current->target_rtp_bps : null;
            $fromEnabled = $current ? (bool) $current->enabled : null;
            $version = $current ? ((int) $current->version + 1) : 1;

            if ($current) {
                DB::table('fish_rtp_configs')->where('game_key', $data['game_key'])->update([
                    'target_rtp_bps' => $targetBps,
                    'enabled' => $enabled,
                    'version' => $version,
                    'updated_by' => $actor->id,
                    'change_reason' => $data['reason'],
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('fish_rtp_configs')->insert([
                    'game_key' => $data['game_key'],
                    'target_rtp_bps' => $targetBps,
                    'enabled' => $enabled,
                    'version' => 1,
                    'updated_by' => $actor->id,
                    'change_reason' => $data['reason'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('fish_rtp_config_audits')->insert([
                'game_key' => $data['game_key'],
                'from_rtp_bps' => $fromBps,
                'to_rtp_bps' => $targetBps,
                'from_enabled' => $fromEnabled,
                'to_enabled' => $enabled,
                'actor_id' => $actor->id,
                'reason' => $data['reason'],
                'created_at' => now(),
            ]);
        });

        return redirect()->route('admin.fish-rtp.index')->with('status', 'Fish RTP target saved with audit history.');
    }

    private function requireOwner(Request $request): void
    {
        abort_unless($request->user() && (int) $request->user()->type === UserType::Owner->value, 403);
    }
}
