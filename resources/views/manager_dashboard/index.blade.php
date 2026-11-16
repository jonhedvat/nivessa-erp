@extends('layouts.app')
@section('title', 'Manager Dashboard')

@section('content')
@php
    $qs = function (array $over) use ($store, $days, $employeeId, $canSwitch) {
        $p = array_merge(['store' => $canSwitch ? $store : null, 'days' => $days, 'employee' => $employeeId ?: null], $over);
        return url('/manager-dashboard') . '?' . http_build_query(array_filter($p, function ($v) { return $v !== null && $v !== ''; }));
    };
    $num = function ($n) { return number_format((float) $n); };
    $hrs = function ($h) { return $h === null ? '-' : number_format((float) $h, 1) . 'h'; };
    $todayKey = $today->format('Y-m-d');
    $pricedToday = $daily[$todayKey]['total'] ?? 0;
    $lowWhole = array_filter($low, function ($l) { return $l['whole']; });
    $lowGenres = array_filter($low, function ($l) { return !$l['whole']; });
    $fail = '<div class="md-empty">Couldn\'t load this part right now. Refresh in a minute.</div>';
@endphp

<style>
.md-wrap { max-width: 1180px; margin: 0 auto; padding: 8px 6px 48px; color: #1e1f21; }
.md-title { font-size: 24px; font-weight: 600; margin: 6px 0 2px; }
.md-sub { color: #6d6e6f; font-size: 13px; }
.md-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 12px 0 16px; }
.md-pill { display: inline-block; padding: 5px 12px; border: 1px solid #dcd9d7; border-radius: 16px; font-size: 13px; color: #1e1f21 !important; background: #fff; text-decoration: none !important; }
.md-pill.on { background: #1e1f21; border-color: #1e1f21; color: #fff !important; }
.md-gap { width: 12px; }
.md-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin-bottom: 18px; }
.md-card { background: #fff; border: 1px solid #edeae9; border-radius: 8px; padding: 14px 16px; }
.md-card .n { font-size: 26px; font-weight: 600; line-height: 1.1; }
.md-card .l { color: #6d6e6f; font-size: 13px; margin-top: 4px; }
.md-card.warn .n { color: #c0392b; }
.md-box { background: #fff; border: 1px solid #edeae9; border-radius: 8px; margin-bottom: 18px; overflow: hidden; }
.md-h { padding: 14px 16px 4px; font-size: 16px; font-weight: 600; }
.md-note { padding: 0 16px 10px; color: #6d6e6f; font-size: 12.5px; }
.md-scroll { overflow-x: auto; }
.md-t { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.md-t th { text-align: left; font-weight: 500; font-size: 12px; color: #6d6e6f; padding: 8px 12px; border-top: 1px solid #edeae9; border-bottom: 1px solid #edeae9; white-space: nowrap; background: #faf9f8; }
.md-t td { padding: 8px 12px; border-bottom: 1px solid #f1efee; vertical-align: top; }
.md-t tr:last-child td { border-bottom: 0; }
.md-t .r { text-align: right; white-space: nowrap; }
.md-t .nm { font-weight: 500; white-space: nowrap; }
.md-bad { color: #c0392b; font-weight: 600; }
.md-good { color: #2e7d5b; font-weight: 600; }
.md-dim { color: #9a9897; }
.md-chip { display: inline-block; background: #f3f1f0; border-radius: 10px; padding: 2px 8px; margin: 1px 4px 1px 0; font-size: 12px; white-space: nowrap; }
.md-empty { padding: 14px 16px; color: #6d6e6f; font-size: 13px; }
.md-two { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 18px; }
.md-two .md-box { margin-bottom: 0; }
.md-two { margin-bottom: 18px; }
.md-sel { padding: 4px 8px; border: 1px solid #dcd9d7; border-radius: 6px; font-size: 13px; }
</style>

<section class="content">
<div class="md-wrap">

    <h1 class="md-title">Manager Dashboard: {{ $storeLabel }}</h1>
    <div class="md-sub">Your team and your store, {{ $from->format('M j') }} to {{ $today->format('M j') }}. All-time numbers are marked.</div>

    <div class="md-bar">
        @if($canSwitch)
            @foreach($stores as $sk => $sl)
                <a class="md-pill {{ $sk === $store ? 'on' : '' }}" href="{{ $qs(['store' => $sk, 'employee' => null]) }}">{{ $sl }}</a>
            @endforeach
            <span class="md-gap"></span>
        @endif
        @foreach($dayChoices as $dc)
            <a class="md-pill {{ $dc === $days ? 'on' : '' }}" href="{{ $qs(['days' => $dc]) }}">Last {{ $dc }} days</a>
        @endforeach
    </div>

    <div class="md-cards">
        <div class="md-card {{ ($web && $web['orders'] > 0) ? 'warn' : '' }}">
            @if($web)
                <div class="n">{{ $num($web['orders']) }}</div>
                <div class="l">Website orders not shipped ({{ $num($web['items']) }} {{ $web['items'] == 1 ? 'item' : 'items' }}, {{ $num($web['to_pull']) }} still to pull)</div>
            @else
                <div class="n md-dim">-</div>
                <div class="l">Website orders not shipped: couldn't reach the website</div>
            @endif
        </div>
        <div class="md-card">
            <div class="n">{{ $num($pricedToday) }}</div>
            <div class="l">Items priced at {{ $storeLabel }} today</div>
        </div>
        <div class="md-card">
            <div class="n">{{ $num($tasks['done']) }}</div>
            <div class="l">Tasks done in the last {{ $days }} days ({{ $num($tasks['in_progress']) }} in progress, {{ $num($tasks['not_started']) }} not started)</div>
        </div>
        <div class="md-card {{ count($low) ? 'warn' : '' }}">
            <div class="n">{{ count($lowWhole) + count($lowGenres) }}</div>
            <div class="l">Categories low in stock (under {{ $lowWeeks }} weeks left)</div>
        </div>
    </div>

    {{-- Team, in the picked window --}}
    <div class="md-box">
        <div class="md-h">Your team, last {{ $days }} days</div>
        <div class="md-note">Shifts are clock-ins at {{ $storeLabel }}. Late = opened the ERP more than {{ $lateMin }} minutes after their scheduled start. Fixed by shelf count = items whose stock here a shelf count changed. Shipped = items in website and Discogs orders they marked shipped in the ERP; Discogs orders shipped on Discogs.com count for Nick.@if(!$shippedOk) Shipped counts couldn't load from the website.@endif</div>
        <div class="md-scroll">
        <table class="md-t">
            <thead><tr>
                <th>Employee</th>
                <th class="r">Shifts</th>
                <th class="r">Avg shift</th>
                <th class="r">Priced</th>
                <th class="r">Priced / shift</th>
                <th class="r">Signups</th>
                <th class="r">Signups / shift</th>
                <th class="r">Fixed by shelf count</th>
                <th class="r">Shipped web</th>
                <th class="r">Shipped Discogs</th>
                <th class="r">Tasks done</th>
                <th class="r">In progress</th>
                <th class="r">Opened late</th>
                <th class="r">Said "I did it"</th>
                <th class="r">Said "I didn't"</th>
            </tr></thead>
            <tbody>
            @forelse($people as $uid => $p)
                <tr>
                    <td class="nm">{{ $p['name'] }}</td>
                    <td class="r">{{ $p['shifts'] }}</td>
                    <td class="r">{{ $p['shifts'] ? $hrs($p['hours'] / $p['shifts']) : '-' }}</td>
                    <td class="r">{{ $num($p['priced']) }}</td>
                    <td class="r">{{ $p['shifts'] ? number_format($p['priced'] / $p['shifts'], 1) : '-' }}</td>
                    <td class="r">{{ $num($p['signups']) }}</td>
                    <td class="r">{{ $p['shifts'] ? number_format($p['signups'] / $p['shifts'], 1) : '-' }}</td>
                    <td class="r">{{ $num($p['shelf_changed']) }}</td>
                    <td class="r">{{ $p['shipped_web'] === null ? '-' : $num($p['shipped_web']) }}</td>
                    <td class="r">{{ $p['shipped_discogs'] === null ? '-' : $num($p['shipped_discogs']) }}</td>
                    <td class="r">{{ $p['tasks_done'] }}</td>
                    <td class="r">{{ $p['tasks_in_progress'] }}</td>
                    <td class="r">
                        @if($p['late'] > 0)
                            <span class="md-bad">{{ $p['late'] }}</span> <span class="md-dim">of {{ $p['scheduled'] }}</span>
                        @elseif($p['scheduled'] > 0)
                            0 <span class="md-dim">of {{ $p['scheduled'] }}</span>
                        @else
                            <span class="md-dim">-</span>
                        @endif
                    </td>
                    <td class="r">{{ $p['said_done'] }}</td>
                    <td class="r">@if($p['said_not_done'] > 0)<span class="md-bad">{{ $p['said_not_done'] }}</span>@else 0 @endif</td>
                </tr>
            @empty
                <tr><td colspan="15" class="md-dim">No one is set up at {{ $storeLabel }} yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{-- 1. Priced all time --}}
    <div class="md-box">
        <div class="md-h">Priced at {{ $storeLabel }}, all time</div>
        <div class="md-note">Everything each person has put into stock here, by format. Signups are customer accounts they created, all time.</div>
        @if(!empty($errors['priced']))
            {!! $fail !!}
        @else
        <div class="md-scroll">
        <table class="md-t">
            <thead><tr><th>Employee</th><th class="r">Items priced</th><th class="r">Customer signups</th><th>By format</th></tr></thead>
            <tbody>
            @forelse($people as $uid => $p)
                @if($p['priced_all'] > 0 || $p['signups_all'] > 0)
                <tr>
                    <td class="nm">{{ $p['name'] }}</td>
                    <td class="r">{{ $num($p['priced_all']) }}</td>
                    <td class="r">{{ $num($p['signups_all']) }}</td>
                    <td>
                        @foreach(($priced['people'][$uid]['formats'] ?? []) as $fmt => $q)
                            <span class="md-chip">{{ $fmt }} {{ $num($q) }}</span>
                        @endforeach
                    </td>
                </tr>
                @endif
            @empty
                <tr><td colspan="4" class="md-dim">Nothing yet.</td></tr>
            @endforelse
            @if($priced['total'] > 0)
                <tr>
                    <td class="nm">Whole team</td>
                    <td class="r"><strong>{{ $num($priced['total']) }}</strong></td>
                    <td class="r"><strong>{{ $num(array_sum(array_column($people, 'signups_all'))) }}</strong></td>
                    <td>
                        @foreach(array_slice($priced['formats'], 0, 12, true) as $fmt => $q)
                            <span class="md-chip">{{ $fmt }} {{ $num($q) }}</span>
                        @endforeach
                    </td>
                </tr>
            @endif
            </tbody>
        </table>
        </div>
        @endif
    </div>

    {{-- 1b. Sold % and time to sell per genre --}}
    <div class="md-box">
        <div class="md-h">Sold % and time to sell, by genre (all time)</div>
        <div class="md-note">
            Of what was priced at {{ $storeLabel }}, how much has sold and how many days it took on average.
            <form method="get" action="{{ url('/manager-dashboard') }}" style="display:inline-block; margin-left:8px;">
                @if($canSwitch)<input type="hidden" name="store" value="{{ $store }}">@endif
                <input type="hidden" name="days" value="{{ $days }}">
                <select name="employee" class="md-sel" onchange="this.form.submit()">
                    <option value="">Whole store</option>
                    @foreach($roster as $rid => $rname)
                        <option value="{{ $rid }}" {{ $rid === $employeeId ? 'selected' : '' }}>Priced by {{ $rname }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        @if(!empty($errors['genres']))
            {!! $fail !!}
        @elseif(empty($genres))
            <div class="md-empty">Not enough priced yet to show.</div>
        @else
        <div class="md-scroll" style="max-height:520px; overflow-y:auto;">
        <table class="md-t">
            <thead><tr><th>Format</th><th>Genre</th><th class="r">Priced</th><th class="r">Sold</th><th class="r">Sold %</th><th class="r">Avg days to sell</th></tr></thead>
            <tbody>
            @foreach($genres as $g)
                <tr>
                    <td>{{ $g['format'] }}</td>
                    <td class="nm">{{ $g['genre'] }}</td>
                    <td class="r">{{ $num($g['priced']) }}</td>
                    <td class="r">{{ $num($g['sold']) }}</td>
                    <td class="r">{{ $g['pct'] }}%</td>
                    <td class="r">{{ $g['days'] === null ? '-' : number_format($g['days'], 0) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>

    <div class="md-two">
        {{-- 8. Priced per day --}}
        <div class="md-box">
            <div class="md-h">Items priced at {{ $storeLabel }} each day</div>
            @if(!empty($errors['daily']))
                {!! $fail !!}
            @else
            <div class="md-scroll" style="max-height:440px; overflow-y:auto;">
            <table class="md-t">
                <thead><tr><th>Day</th><th class="r">Total</th><th>Who</th></tr></thead>
                <tbody>
                @foreach($daily as $d => $row)
                    <tr>
                        <td class="nm">{{ \Carbon\Carbon::parse($d)->format('D, M j') }}</td>
                        <td class="r">@if($row['total'] > 0)<strong>{{ $num($row['total']) }}</strong>@else<span class="md-dim">0</span>@endif</td>
                        <td>
                            @foreach($row['who'] as $wn => $wq)
                                <span class="md-chip">{{ $wn }} {{ $num($wq) }}</span>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @endif
        </div>

        {{-- 9. Low stock --}}
        <div class="md-box">
            <div class="md-h">Low in stock</div>
            <div class="md-note">Sells about once a week or more at {{ $storeLabel }} and has under {{ $lowWeeks }} weeks left on hand.</div>
            @if(!empty($errors['low']))
                {!! $fail !!}
            @elseif(empty($low))
                <div class="md-empty">Nothing is running low right now.</div>
            @else
            <div class="md-scroll" style="max-height:440px; overflow-y:auto;">
            <table class="md-t">
                <thead><tr><th>Category</th><th class="r">Sells / week</th><th class="r">On hand</th><th class="r">Weeks left</th></tr></thead>
                <tbody>
                @foreach($low as $l)
                    <tr>
                        <td class="nm">{{ $l['format'] }}@if($l['whole']) <span class="md-dim">(whole category)</span>@else<span class="md-dim">:</span> {{ $l['genre'] }}@endif</td>
                        <td class="r">{{ number_format($l['weekly'], 1) }}</td>
                        <td class="r">{{ $num($l['on_hand']) }}</td>
                        <td class="r"><span class="{{ $l['weeks'] < 2 ? 'md-bad' : '' }}">{{ number_format($l['weeks'], 1) }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @endif
        </div>
    </div>

    {{-- 2 + 3. Shifts --}}
    <div class="md-box">
        <div class="md-h">Shifts at {{ $storeLabel }}, last {{ $days }} days</div>
        <div class="md-note">How long each shift was, and what was priced and how many customers were signed up during it.</div>
        @if(!empty($errors['shifts']))
            {!! $fail !!}
        @elseif(empty($shiftRows))
            <div class="md-empty">No clock-ins at {{ $storeLabel }} in this window.</div>
        @else
        <div class="md-scroll" style="max-height:520px; overflow-y:auto;">
        <table class="md-t">
            <thead><tr><th>Day</th><th>Employee</th><th>In</th><th>Out</th><th class="r">Length</th><th class="r">Priced</th><th class="r">Priced / hour</th><th class="r">Signups</th></tr></thead>
            <tbody>
            @foreach($shiftRows as $s)
                <tr>
                    <td class="nm">{{ date('D, M j', $s['in']) }}</td>
                    <td>{{ $s['name'] }}</td>
                    <td>{{ date('g:ia', $s['in']) }}</td>
                    <td>
                        @if($s['open'])
                            <span class="md-good">On shift now</span>
                        @elseif($s['missed'])
                            <span class="md-bad">No clock-out</span>
                        @else
                            {{ date('g:ia', $s['out']) }}
                        @endif
                    </td>
                    <td class="r">{{ $hrs($s['hours']) }}</td>
                    <td class="r">{{ $num($s['priced']) }}</td>
                    <td class="r">{{ $s['per_hour'] === null ? '-' : number_format($s['per_hour'], 1) }}</td>
                    <td class="r">{{ $s['signups'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>

    <div class="md-two">
        {{-- 5. Late opens --}}
        <div class="md-box">
            <div class="md-h">Opened the ERP late</div>
            <div class="md-note">First login or clock-in of the day, more than {{ $lateMin }} minutes after the scheduled start.</div>
            @if(!empty($errors['late']))
                {!! $fail !!}
            @elseif(empty($late['rows']))
                <div class="md-empty">No late starts in the last {{ $days }} days.</div>
            @else
            <div class="md-scroll" style="max-height:400px; overflow-y:auto;">
            <table class="md-t">
                <thead><tr><th>Day</th><th>Employee</th><th>Scheduled</th><th>Opened</th><th class="r">Late by</th></tr></thead>
                <tbody>
                @foreach($late['rows'] as $lr)
                    <tr>
                        <td class="nm">{{ date('D, M j', $lr['start']) }}</td>
                        <td>{{ $lr['name'] }}</td>
                        <td>{{ date('g:ia', $lr['start']) }}</td>
                        <td>{{ date('g:ia', $lr['opened']) }}</td>
                        <td class="r md-bad">{{ $lr['min'] >= 60 ? floor($lr['min'] / 60) . 'h ' . ($lr['min'] % 60) . 'm' : $lr['min'] . ' min' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @endif
        </div>

        {{-- 6. Said they didn't do it --}}
        <div class="md-box">
            <div class="md-h">Tasks marked "I didn't do it" at close</div>
            <div class="md-note">What people answered about their assigned tasks when closing the register.</div>
            @if(!empty($errors['checkins']))
                {!! $fail !!}
            @elseif(empty($checkins['not_done']))
                <div class="md-empty">No "I didn't do it" answers in the last {{ $days }} days.</div>
            @else
            <div class="md-scroll" style="max-height:400px; overflow-y:auto;">
            <table class="md-t">
                <thead><tr><th>Day</th><th>Employee</th><th>Task</th><th>Reason</th></tr></thead>
                <tbody>
                @foreach($checkins['not_done'] as $nd)
                    <tr>
                        <td class="nm">{{ date('D, M j', $nd['at']) }}</td>
                        <td>{{ $nd['name'] }}</td>
                        <td>{{ $nd['task'] }}</td>
                        <td>{{ $nd['reason'] ?: '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @endif
        </div>
    </div>

    {{-- 4. Tasks in progress --}}
    <div class="md-box">
        <div class="md-h">Tasks in progress</div>
        @if(!empty($errors['tasks']))
            {!! $fail !!}
        @elseif(empty($tasks['in_progress_list']))
            <div class="md-empty">Nothing is marked in progress right now.</div>
        @else
        <div class="md-scroll">
        <table class="md-t">
            <thead><tr><th>Task</th><th>Who</th></tr></thead>
            <tbody>
            @foreach($tasks['in_progress_list'] as $ip)
                <tr><td>{{ $ip['title'] }}</td><td>{{ $ip['who'] ?: '-' }}</td></tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
        <div class="md-note" style="padding-top:8px;"><a href="{{ url('/tasks') }}">Open Tasks</a></div>
    </div>

</div>
</section>
@endsection
