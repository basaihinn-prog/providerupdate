@extends('layouts.app')

@section('content')
<div class="container py-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-1">Fish1 RTP Control & Audit</h1>
            <p class="text-muted mb-0">Monitoring targets and observed round results. Targets do not modify game outcomes.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Please correct the following:</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">Create or update an RTP monitoring target</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.fish-rtp.store') }}" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label for="game_key" class="form-label">Registered game key</label>
                    <input id="game_key" name="game_key" class="form-control" maxlength="100" pattern="[A-Za-z0-9._:-]+" value="{{ old('game_key', 'fish1') }}" required>
                </div>
                <div class="col-md-3">
                    <label for="target_rtp_percent" class="form-label">Target RTP (%)</label>
                    <input id="target_rtp_percent" name="target_rtp_percent" type="number" class="form-control" min="1" max="200" step="0.01" value="{{ old('target_rtp_percent', '96.00') }}" required>
                </div>
                <div class="col-md-2">
                    <label for="enabled" class="form-label">Monitoring</label>
                    <select id="enabled" name="enabled" class="form-select" required>
                        <option value="1" @selected(old('enabled', '1') === '1')>Enabled</option>
                        <option value="0" @selected(old('enabled') === '0')>Disabled</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <label for="reason" class="form-label">Change reason / ticket reference</label>
                    <input id="reason" name="reason" class="form-control" maxlength="500" minlength="10" value="{{ old('reason') }}" placeholder="Required: explain the approved configuration change" required>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Save target and audit</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Observed RTP by game</div>
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Game key</th>
                        <th>Target RTP</th>
                        <th>Actual RTP</th>
                        <th>Rounds</th>
                        <th>Total stakes</th>
                        <th>Gross payouts</th>
                        <th>Monitoring</th>
                        <th>Version</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($configs as $config)
                        <tr>
                            <td>{{ $config->game_key }}</td>
                            <td>{{ number_format(((int) $config->target_rtp_bps) / 100, 2) }}%</td>
                            <td>{{ $config->actual_rtp_percent === null ? 'N/A' : number_format($config->actual_rtp_percent, 4).'%' }}</td>
                            <td>{{ number_format($config->rounds) }}</td>
                            <td>{{ number_format($config->total_bets, 2) }}</td>
                            <td>{{ number_format($config->total_payouts, 2) }}</td>
                            <td>{{ $config->enabled ? 'Enabled' : 'Disabled' }}</td>
                            <td>{{ $config->version }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No RTP configuration exists yet. Save an approved target above to start tracking the game.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer small text-muted">
            Actual RTP is calculated as recorded gross payouts ÷ recorded stakes × 100. Confirm whether the game's gross payout includes returned stake before comparing to a provider/certified RTP.
        </div>
    </div>

    <div class="card">
        <div class="card-header">Latest configuration audit events</div>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr><th>Time</th><th>Game</th><th>From</th><th>To</th><th>Enabled</th><th>Actor</th><th>Reason</th></tr>
                </thead>
                <tbody>
                    @forelse ($audits as $audit)
                        <tr>
                            <td>{{ $audit->created_at }}</td>
                            <td>{{ $audit->game_key }}</td>
                            <td>{{ $audit->from_rtp_bps === null ? '—' : number_format($audit->from_rtp_bps / 100, 2).'%' }}</td>
                            <td>{{ number_format($audit->to_rtp_bps / 100, 2) }}%</td>
                            <td>{{ ($audit->from_enabled === null ? '—' : ($audit->from_enabled ? 'On' : 'Off')).' → '.($audit->to_enabled ? 'On' : 'Off') }}</td>
                            <td>{{ $audit->actor_name ?? ('User #'.$audit->actor_id) }}</td>
                            <td>{{ $audit->reason }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No changes recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
