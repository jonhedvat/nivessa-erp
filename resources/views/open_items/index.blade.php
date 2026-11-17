@extends('layouts.app')
@section('title', "Fatteen's Dashboard")

@section('content')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700;800&display=swap">
@include('daily_checklist.partials.styles')

<style>
.open-shell .oi-top { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
.open-shell .oi-total { font-size:15px; font-weight:700; color:var(--d-ink); margin-top:8px; }
.open-shell .oi-total.zero { color:var(--d-good); }
.open-shell .oi-btn { display:inline-block; font-size:13px; font-weight:700; color:var(--d-accent-text); background:var(--d-accent-soft); border:1px solid var(--d-accent-deep); border-radius:999px; padding:8px 14px; text-decoration:none; }
.open-shell .oi-btn:hover { background:var(--d-accent); text-decoration:none; }
.open-shell .oi-card { background:var(--d-surface); border:1px solid var(--d-line); border-radius:var(--d-radius); margin-top:16px; overflow:hidden; }
.open-shell .oi-head { display:flex; justify-content:space-between; align-items:baseline; gap:12px; padding:14px 18px; border-bottom:1px solid var(--d-line); flex-wrap:wrap; }
.open-shell .oi-head h2 { font-size:17px; font-weight:800; margin:0; color:var(--d-ink); }
.open-shell .oi-count { display:inline-block; min-width:28px; text-align:center; font-size:13px; font-weight:800; border-radius:999px; padding:2px 9px; margin-left:8px; background:#FDE2DF; color:#9B1C12; }
.open-shell .oi-count.zero { background:#DDF1DE; color:var(--d-good); }
.open-shell .oi-how { font-size:13px; color:var(--d-ink-2); margin:0; padding:10px 18px; background:var(--d-surface-2); border-bottom:1px solid var(--d-line); }
.open-shell { max-width:1400px; }
.open-shell .oi-row { display:grid; grid-template-columns:minmax(0,1fr) auto auto; grid-template-areas:"who age open" "what what what"; gap:4px 14px; align-items:center; padding:12px 18px; border-bottom:1px solid var(--d-line); font-size:14px; color:var(--d-ink); }
.open-shell .oi-row .oi-who { grid-area:who; white-space:normal; }
.open-shell .oi-row .oi-what { grid-area:what; }
.open-shell .oi-row .oi-todo { grid-column:1 / -1; font-size:13px; color:var(--d-ink); background:var(--d-surface-2); border-radius:8px; padding:6px 10px; margin-top:4px; }
.open-shell .oi-num { font-weight:800; margin-right:2px; }
.open-shell .bk-grid { display:grid; grid-template-columns:repeat(6, minmax(0,1fr)); gap:10px; margin-top:16px; }
.open-shell .bk { display:grid; gap:6px; align-content:start; text-align:left; font:inherit; background:var(--d-surface); border:2px solid var(--d-line); border-radius:12px; padding:12px; cursor:pointer; color:var(--d-ink); text-decoration:none; }
.open-shell .bk:hover { text-decoration:none; border-color:var(--d-accent-deep); }
.open-shell .bk.on { border-color:#3B2E2A; background:#FFFBE6; box-shadow:0 2px 10px rgba(0,0,0,.08); }
.open-shell .bk-step { font-size:12px; font-weight:800; color:var(--d-ink); }
.open-shell .bk-name { font-size:16px; font-weight:800; line-height:1.2; }
.open-shell .bk-left { font-size:13px; font-weight:600; }
.open-shell .bk .sb-bar { height:10px; }
@media (max-width: 1100px) { .open-shell .bk-grid { grid-template-columns:repeat(3, minmax(0,1fr)); } }
@media (max-width: 600px) { .open-shell .bk-grid { grid-template-columns:repeat(2, minmax(0,1fr)); } }
.open-shell .oi-tag { display:inline-block; font-size:11px; font-weight:700; border-radius:999px; padding:2px 8px; margin-right:4px; background:#E3F0FB; color:#174E7C; vertical-align:1px; }
.open-shell .oi-tag-unhappy { background:#FFE3D6; color:#7A2E0E; }
.open-shell .oi-tag-orders, .open-shell .oi-tag-discogs { background:#FFF4C2; color:#5B4A00; }
.open-shell .oi-tag-sellers { background:#DDF1DE; color:#1B5E20; }
.open-shell .oi-row .oi-age { grid-area:age; }
.open-shell .oi-row .oi-open { grid-area:open; }
.open-shell .oi-row:last-child { border-bottom:0; }
.open-shell .oi-who { font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.open-shell .oi-what { color:var(--d-ink-2); }
.open-shell .oi-age { font-weight:700; white-space:nowrap; }
.open-shell .oi-age.late { color:#B3261E; }
.open-shell .oi-open a { font-weight:700; color:var(--d-accent-text); white-space:nowrap; }
.open-shell .oi-empty { padding:12px 18px; font-size:14px; color:var(--d-good); font-weight:600; }
.open-shell .oi-err { padding:12px 18px; font-size:14px; color:var(--d-warn); font-weight:600; }

.open-shell .oi-row { grid-template-columns:minmax(120px,200px) 1fr auto auto; }
.open-shell .oi-row .oi-acts { grid-column:1 / -1; justify-content:flex-start; flex-wrap:wrap; margin-top:6px; }
.open-shell .oi-reopen { grid-column:1 / -1; font-size:13px; font-weight:600; color:#B3261E; }
.open-shell .oi-acts { display:flex; gap:6px; }
.open-shell .oi-acts details { position:relative; }
.open-shell .oi-acts summary { list-style:none; cursor:pointer; font-size:12px; font-weight:700; border-radius:999px; padding:5px 10px; white-space:nowrap; border:1px solid var(--d-line-2); color:var(--d-ink); background:var(--d-surface); }
.open-shell .oi-acts summary::-webkit-details-marker { display:none; }
.open-shell .oi-acts .fix summary { background:#DDF1DE; border-color:#9FD3A4; color:#1B5E20; }
.open-shell .oi-acts .ask summary { background:#E3F0FB; border-color:#A9CDEB; color:#174E7C; }
.open-shell .oi-acts form { position:absolute; right:0; top:32px; z-index:20; width:300px; background:var(--d-surface); border:1px solid var(--d-line-2); border-radius:10px; padding:10px; box-shadow:0 8px 24px rgba(0,0,0,.12); display:grid; gap:8px; }
.open-shell .oi-acts textarea { width:100%; min-height:72px; font:inherit; font-size:13px; border:1px solid var(--d-line-2); border-radius:8px; padding:8px; color:var(--d-ink); background:var(--d-surface); }
.open-shell .oi-acts button { font:inherit; font-size:13px; font-weight:700; border-radius:8px; padding:7px 10px; border:1px solid var(--d-accent-deep); background:var(--d-accent); color:var(--d-accent-text); cursor:pointer; }
.open-shell .oi-rec { grid-column:1 / -1; font-size:13px; color:var(--d-ink); background:#E3F0FB; border-radius:8px; padding:6px 10px; }
.open-shell .oi-msg { margin-top:12px; font-weight:700; color:var(--d-ink); background:#E3F0FB; border-radius:10px; padding:10px 14px; }
.open-shell .oi-done { padding:10px 18px; border-bottom:1px solid var(--d-line); font-size:14px; color:var(--d-ink); }
.open-shell .oi-side { background:var(--d-surface); border:1px solid var(--d-line); border-radius:var(--d-radius); overflow:hidden; }
.open-shell .oi-side .oi-head { padding:12px 16px; }
.open-shell .oi-side-body { padding:12px 16px; display:grid; gap:10px; font-size:14px; color:var(--d-ink); max-height:calc(100vh - 110px); overflow:auto; }
.open-shell .oi-side textarea { width:100%; min-height:150px; font:inherit; font-size:14px; border:1px solid var(--d-line-2); border-radius:8px; padding:10px; color:var(--d-ink); background:var(--d-surface); }
.open-shell .oi-side input[type=text] { width:100%; font:inherit; font-size:13px; border:1px solid var(--d-line-2); border-radius:8px; padding:8px; color:var(--d-ink); }
.open-shell .oi-quote { background:var(--d-surface-2); border-radius:8px; padding:10px; white-space:pre-wrap; max-height:320px; overflow:auto; display:grid; gap:8px; }
.open-shell .oi-bub { max-width:85%; padding:8px 10px; border-radius:12px; font-size:13px; line-height:1.4; white-space:pre-wrap; }
.open-shell .oi-bub.c { background:#fff; border:1px solid var(--d-line); justify-self:start; border-bottom-left-radius:4px; }
.open-shell .oi-bub.s { background:#3B2E2A; color:#fff; justify-self:end; border-bottom-right-radius:4px; }
.open-shell .oi-bub small { display:block; font-size:11px; opacity:.75; margin-top:3px; }
.open-shell .oi-chans { display:flex; flex-wrap:wrap; gap:6px; padding:12px 20px; border-bottom:1px solid var(--d-line); }
.open-shell .oi-chan { font-size:12px; font-weight:700; border-radius:999px; padding:4px 10px; text-decoration:none; }
.open-shell .oi-chan.on { background:#DDF1DE; color:#1B5E20; }
.open-shell a.oi-chan.off, .open-shell span.oi-chan.off { background:#F1ECE6; color:#4A3D38; border:1px dashed #C9BFB5; }
.open-shell .oi-pills { display:flex; flex-wrap:wrap; gap:6px; }
.open-shell .oi-pill { font:inherit; font-size:12px; font-weight:700; border-radius:999px; padding:5px 10px; border:1px solid #A9CDEB; background:#E3F0FB; color:#174E7C; cursor:pointer; }
.open-shell .oi-go { font:inherit; font-size:13px; font-weight:700; border-radius:8px; padding:8px 12px; border:1px solid var(--d-accent-deep); background:var(--d-accent); color:var(--d-accent-text); cursor:pointer; }
.open-shell .oi-acts .ai summary { background:#FFF4C2; border-color:#E8D27A; color:#5B4A00; }
.open-shell .oi-acts .ai { list-style:none; cursor:pointer; font-size:12px; font-weight:700; border-radius:999px; padding:5px 10px; white-space:nowrap; border:1px solid #E8D27A; background:#FFF4C2; color:#5B4A00; }
.open-shell .oi-row.picked { background:#FFFBE6; }
.open-shell .sb { margin-top:16px; background:var(--d-surface); border:1px solid var(--d-line); border-radius:var(--d-radius); padding:16px 18px; display:grid; gap:12px; }
.open-shell .sb-grid { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:10px; }
.open-shell .sb-tile { background:var(--d-surface-2); border-radius:10px; padding:10px 12px; }
.open-shell .sb-tile .k { font-size:12px; font-weight:700; color:var(--d-ink); text-transform:uppercase; letter-spacing:.5px; }
.open-shell .sb-tile .v { font-size:24px; font-weight:800; color:var(--d-ink); font-variant-numeric:tabular-nums; line-height:1.2; }
.open-shell .sb-tile .s { font-size:12px; color:var(--d-ink); }
.open-shell .sb-bar { height:12px; background:var(--d-surface-2); border-radius:999px; overflow:hidden; }
.open-shell .sb-bar span { display:block; height:100%; background:#F2C94C; border-radius:999px; transition:width .6s; }
.open-shell .sb-bar.hit span { background:#3FA35B; }
.open-shell .sb-feed { font-size:13px; color:var(--d-ink); display:flex; flex-wrap:wrap; gap:6px; }
.open-shell .sb-feed span { background:#E3F0FB; color:#174E7C; border-radius:999px; padding:3px 10px; font-weight:600; }
.open-shell .sb-feed span.neg { background:#FDE2E1; color:#8A1C17; }
.open-shell .oi-acts .fix summary b { color:#1B5E20; margin-left:4px; }
@media (max-width: 700px) { .open-shell .sb-grid { grid-template-columns:repeat(2, minmax(0,1fr)); } }

@media (max-width: 700px) {
  .open-shell .oi-acts form { right:auto; left:0; width:260px; }
  .open-shell .oi-row { grid-template-columns:minmax(0,1fr) auto; grid-template-areas:"who open" "age age" "what what"; }
}
.open-shell .oi-layout { display:grid; grid-template-columns:250px minmax(0,1fr); gap:20px; align-items:start; margin-top:16px; }
.open-shell .oi-nav { position:sticky; top:12px; display:grid; gap:8px; }
.open-shell .oi-goal { background:#3B2E2A; color:#fff; border-radius:12px; padding:14px; display:grid; gap:8px; }
.open-shell .oi-goal-n { font-size:28px; font-weight:800; line-height:1; color:#FFEE94; }
.open-shell .oi-goal-n span { font-size:14px; font-weight:600; color:#fff; }
.open-shell .oi-goal-s { font-size:13px; font-weight:600; }
.open-shell .oi-goal .sb-bar { background:rgba(255,255,255,.2); height:10px; }
.open-shell .bk { display:grid; gap:8px; text-align:left; font:inherit; background:var(--d-surface); border:1px solid var(--d-line); border-radius:10px; padding:12px 14px; cursor:pointer; color:var(--d-ink); }
.open-shell .bk:hover { border-color:var(--d-accent-deep); }
.open-shell .bk.on { border:2px solid #3B2E2A; padding:11px 13px; background:#FFFBE6; }
.open-shell .bk-top { display:flex; justify-content:space-between; align-items:center; gap:8px; }
.open-shell .bk-name { font-size:15px; font-weight:700; }
.open-shell .bk-n { font-size:13px; font-weight:800; background:#FFE3D6; color:#7A2E0E; border-radius:999px; padding:2px 9px; }
.open-shell .bk.clear .bk-n { background:#DDF1DE; color:#1B5E20; }
.open-shell .bk .sb-bar { height:6px; }
.open-shell .oi-panel { background:var(--d-surface); border:1px solid var(--d-line); border-radius:var(--d-radius); overflow:hidden; }
.open-shell .oi-panel > .oi-card { border:0; margin:0; border-radius:0; }
.open-shell .oi-panel-head { display:flex; justify-content:space-between; align-items:baseline; gap:10px; padding:16px 20px; border-bottom:1px solid var(--d-line); }
.open-shell .oi-panel-head h2 { font-size:20px; font-weight:800; margin:0; color:var(--d-ink); }
.open-shell .oi-panel-head span { font-size:14px; font-weight:700; color:var(--d-ink); }
.open-shell .oi-group { padding:14px 20px 6px; background:var(--d-surface-2); border-bottom:1px solid var(--d-line); }
.open-shell .oi-group h3 { font-size:14px; font-weight:800; margin:0; color:var(--d-ink); }
.open-shell .oi-group h3 span { font-weight:700; margin-left:4px; }
.open-shell .oi-group p { margin:2px 0 8px; font-size:13px; color:var(--d-ink); }
.open-shell .oi-row { display:grid; grid-template-columns:minmax(0,1fr) auto; grid-template-areas:"who age" "what what" "acts acts"; gap:4px 14px; padding:12px 20px; border-bottom:1px solid var(--d-line); }
.open-shell .oi-row .oi-who { grid-area:who; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.open-shell .oi-row .oi-age { grid-area:age; }
.open-shell .oi-row .oi-what { grid-area:what; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:var(--d-ink); }
.open-shell .oi-row .oi-acts { grid-area:acts; justify-content:flex-start; margin-top:6px; position:relative; flex-wrap:wrap; }
.open-shell a.oi-act, .open-shell a.oi-act:visited { font-size:12px; font-weight:700; border-radius:999px; padding:5px 12px; background:#3B2E2A !important; color:#FFEE94 !important; text-decoration:none; white-space:nowrap; }
.open-shell a.oi-act:hover { text-decoration:none; color:#fff !important; }
.open-shell .oi-side { position:fixed; left:auto; right:16px; bottom:16px; top:auto; width:min(440px, calc(100vw - 32px)); z-index:1050; display:none; box-shadow:0 10px 30px rgba(0,0,0,.25); margin:0; }
.open-shell .oi-side.open { display:block; }
.open-shell .oi-side-body { max-height:75vh; }
@media (max-width: 900px) { .open-shell .oi-layout { grid-template-columns:1fr; } .open-shell .oi-nav { position:static; grid-template-columns:repeat(2, minmax(0,1fr)); } .open-shell .oi-goal { grid-column:1 / -1; } }
.open-shell .oi-wins { margin-top:16px; background:linear-gradient(135deg,#3B2E2A,#5A4038); color:#fff; border-radius:14px; padding:18px 20px; display:grid; grid-template-columns:minmax(0,340px) minmax(0,1fr); gap:20px; }
.open-shell .oi-wins-stats { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
.open-shell .oi-wins-stats div { background:rgba(255,255,255,.08); border-radius:10px; padding:10px 12px; }
.open-shell .oi-wins-stats b { display:block; font-size:30px; line-height:1.1; color:#FFEE94; font-variant-numeric:tabular-nums; }
.open-shell .oi-wins-stats span { font-size:13px; font-weight:600; }
.open-shell .oi-wins-title { font-size:15px; font-weight:800; color:#FFEE94; margin-bottom:6px; letter-spacing:.5px; text-transform:uppercase; }
.open-shell .oi-wins-list { display:grid; align-content:start; gap:6px; max-height:220px; overflow:auto; }
.open-shell .oi-win { display:grid; grid-template-columns:24px minmax(0,1fr) auto; gap:8px; align-items:start; font-size:14px; }
.open-shell .oi-win em { font-style:normal; font-size:12px; opacity:.85; white-space:nowrap; }
.open-shell .oi-check { display:inline-grid; place-items:center; width:22px; height:22px; border-radius:50%; background:#3FA35B; color:#fff; font-weight:800; font-size:13px; }
.open-shell .oi-win-empty { font-size:15px; font-weight:600; }
.open-shell .bk-done { font-size:12px; font-weight:800; color:#1B5E20; }
.open-shell #oi-flash { background:#DDF1DE; color:#1B5E20; font-size:16px; }
.oi-confetti { position:fixed; top:-12px; width:10px; height:14px; z-index:2000; border-radius:2px; animation:oiFall 1.8s ease-in forwards; pointer-events:none; }
@keyframes oiFall { to { transform:translateY(105vh) rotate(720deg); opacity:.9; } }
@media (max-width: 900px) { .open-shell .oi-wins { grid-template-columns:1fr; } }
</style>

<div class="open-shell">
    <div class="oi-top">
        <div>
            <h1>Fatteen's Dashboard</h1>
            @php $hr = (int) now('America/Los_Angeles')->format('G'); @endphp
            <p style="font-size:16px;font-weight:600">{{ $hr < 12 ? 'Good morning' : ($hr < 17 ? 'Good afternoon' : 'Good evening') }}, {{ $me }}! {{ $sb['week'] > 0 ? 'You have finished ' . $sb['week'] . ' this week. Keep it going!' : 'Let\'s get your first win of the week.' }}</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="oi-btn" href="{{ url('/open-items?refresh=1') }}">Refresh</a>
            <a class="oi-btn" href="{{ url('/daily-checklist') }}">Daily checklist</a>
        </div>
    </div>

    @if(session('oi_msg'))<div class="oi-msg" id="oi-flash">{{ session('oi_msg') }}</div>@endif

    @php $wins = array_values(array_filter($done, fn ($a) => ($a['action'] ?? '') === 'fixed')); @endphp
    <div class="oi-wins">
        <div class="oi-wins-stats">
            <div><b>{{ $sb['today'] }}</b><span>done today</span></div>
            <div><b>{{ $sb['week'] }}</b><span>this week</span></div>
            <div><b>{{ $sb['streak'] }}</b><span>day streak</span></div>
            <div><b>{{ max($sb['best'], $sb['today']) }}</b><span>best day</span></div>
        </div>
        <div class="oi-wins-list">
            <div class="oi-wins-title">Recent wins</div>
            @forelse($sb['recent'] as $w)
                @php $wa = \Carbon\Carbon::parse($w['at'])->setTimezone('America/Los_Angeles'); @endphp
                <div class="oi-win"><span class="oi-check">&#10003;</span><span><strong>{{ \Illuminate\Support\Str::contains($w['label'], ': ') ? \Illuminate\Support\Str::after($w['label'], ': ') : $w['label'] }}</strong> {{ $w['note'] }}</span><em>{{ $wa->isToday() ? $wa->format('g:i A') : $wa->format('m/d/y') }}</em></div>
            @empty
                <div class="oi-win-empty">Your first win is one click away. Start with box 1.</div>
            @endforelse
        </div>
    </div>



    @php
        // Buckets in the order he works them. Each list section belongs to one.
        $bucketName = ['customers' => 'Happy customers', 'sellers' => 'Buy collections', 'orders' => 'Orders out the door', 'store' => 'Store tasks'];
        $bucketKeys = ['customers' => ['unhappy', 'customers', 'contact'], 'sellers' => ['sellers'], 'orders' => ['orders', 'discogs'], 'store' => ['events', 'staff', 'bookings', 'buys']];
        $bucketOf = [];
        foreach ($bucketKeys as $bk => $ks) { foreach ($ks as $k) { $bucketOf[$k] = $bk; } }
        $failed = [];
        $open = array_fill_keys(array_keys($bucketName), 0);
        $titleToKey = [];
        foreach ($sections as $sk => $sec) {
            $titleToKey[$sec['title']] = $sk;
            if (!empty($sec['error'])) { $failed[] = $sec['title']; }
            $open[$bucketOf[$sk] ?? 'store'] += count($sec['rows']);
        }
        $doneB = array_fill_keys(array_keys($bucketName), 0);
        foreach ($done as $a) {
            $title = trim(explode(':', (string) ($a['label'] ?? ''))[0]);
            $bb = $bucketOf[$titleToKey[$title] ?? ''] ?? null;
            if ($bb) { $doneB[$bb]++; }
        }
        $startB = 'week';
        foreach ($bucketName as $bk => $nm) { if ($open[$bk] > 0) { $startB = $bk; break; } }
        $goalPct = min(100, (int) round($sb['today'] * 100 / max(1, $sb['target'])));
    @endphp

    <div class="oi-layout">
        <nav class="oi-nav" id="oi-buckets">
            <div class="oi-goal">
                <div class="oi-goal-n">{{ $sb['today'] }} <span>of {{ $sb['target'] }} today</span></div>
                <div class="sb-bar {{ $goalPct >= 100 ? 'hit' : '' }}"><span style="width:{{ $goalPct }}%"></span></div>
                <div class="oi-goal-s">Streak: {{ $sb['streak'] }} {{ $sb['streak'] === 1 ? 'day' : 'days' }}</div>
            </div>
            @foreach($bucketName as $bk => $nm)
                @php $tot = $open[$bk] + $doneB[$bk]; $p = $tot ? (int) round($doneB[$bk] * 100 / $tot) : 100; @endphp
                <button type="button" class="bk {{ $open[$bk] === 0 ? 'clear' : '' }}" data-b="{{ $bk }}">
                    <span class="bk-top"><span class="bk-name">{{ $loop->iteration }}. {{ $nm }}</span><span class="bk-n">{{ $open[$bk] === 0 ? 'Done' : $open[$bk] }}</span></span>
                    <span class="sb-bar {{ $open[$bk] === 0 ? 'hit' : '' }}"><span style="width:{{ $p }}%"></span></span>
                    @if($doneB[$bk] > 0)<span class="bk-done">{{ $doneB[$bk] }} done today</span>@endif
                </button>
            @endforeach
            <button type="button" class="bk" data-b="week"><span class="bk-top"><span class="bk-name">5. Once a week</span></span></button>
            <button type="button" class="bk" data-b="time"><span class="bk-top"><span class="bk-name">6. If you have time</span></span></button>
        </nav>

        <div class="oi-main" id="oi-list">
            @if($failed)<div class="oi-err">Could not load: {{ implode(', ', $failed) }}. Click Refresh in a few minutes.</div>@endif
            @foreach($bucketName as $bk => $nm)
                @php $tot = $open[$bk] + $doneB[$bk]; @endphp
                <section class="oi-panel" data-b="{{ $bk }}" style="display:none">
                    <div class="oi-panel-head"><h2>{{ $nm }}</h2><span>{{ $doneB[$bk] }} of {{ $tot }} done</span></div>
                    @if($bk === 'customers')
                        <div class="oi-chans">
                            @foreach(\App\Utils\OpenItemsUtil::channelStatus() as $ch)
                                @if(!empty($ch['gmail']))<a class="oi-chan on" href="https://mail.google.com/mail/u/?authuser=hello@nivessa.com" target="_blank">Email (hello@): answer in Gmail</a>
                                @elseif($ch['on'])<span class="oi-chan on">{{ $ch['name'] }}: on</span>
                                @elseif($isOwner && $ch['setup'])<a class="oi-chan off" href="{{ url($ch['setup']) }}">{{ $ch['name'] }}: connect</a>
                                @else<span class="oi-chan off">{{ $ch['name'] }}: not connected</span>@endif
                            @endforeach
                        </div>
                    @endif
                    @if($bk === 'store')
                        @php $yday = now('America/Los_Angeles')->subDay()->format('Y-m-d'); $rn = ((array) (\App\Utils\RegisterReconUtil::settings()['day_notes'] ?? []))[$yday] ?? null; @endphp
                        <div class="oi-group"><h3>Register recon for yesterday</h3><p>{{ $rn ? 'Done. Your summary is saved.' : 'Read the flags, find out why, and write your summary.' }}</p></div>
                        <div class="oi-row"><div class="oi-who">{{ now('America/Los_Angeles')->subDay()->format('l m/d/y') }}</div><div class="oi-age" style="{{ $rn ? 'color:#1B5E20' : '' }}">{{ $rn ? 'Done' : 'To do' }}</div><div class="oi-acts"><a class="oi-act" href="{{ url('/register-recon?date=' . $yday) }}">Open</a></div></div>
                    @endif
                    @if($open[$bk] === 0)
                        <div class="oi-empty">All done! Click the next box on the left.</div>
                    @endif
                    @foreach($bucketKeys[$bk] as $key)
                        @php $s = $sections[$key] ?? null; @endphp
                        @if($s && count($s['rows']))
                            <div class="oi-group"><h3>{{ $s['title'] }} <span>{{ count($s['rows']) }}</span></h3>@if($s['how'])<p>{{ $s['how'] }}</p>@endif</div>
                            @foreach($s['rows'] as $r)
                                <div class="oi-row">
                                    <div class="oi-who" title="{{ $r['who'] }}">{{ $r['who'] }}</div>
                                    <div class="oi-age {{ ($r['late'] ?? ($r['hours'] >= 72)) ? 'late' : '' }}" title="{{ $r['since'] }}">{{ $r['age'] }}</div>
                                    <div class="oi-what">{{ $r['what'] }}</div>
                                    <div class="oi-acts">
                                        <a class="oi-act" href="{{ $r['link'] }}">Open</a>
                                        @if(!empty($r['reply']))
                                            <button type="button" class="ai" data-reply="{{ json_encode(array_merge($r['reply'], ['who' => $r['who'], 'key' => $r['key'], 'section' => $key, 'hours' => (int) $r['hours'], 'label' => $s['title'] . ': ' . $r['who'], 'link' => $r['link']])) }}">Reply with Claude</button>
                                        @endif
                            @if(!empty($r['key']))
                            <details class="fix"><summary>Fixed it</summary>
                                <form method="POST" action="{{ route('open-items.act') }}">
                                    {{ csrf_field() }}
                                    <input type="hidden" name="key" value="{{ $r['key'] }}">
                                    <input type="hidden" name="action" value="fixed">
                                    <input type="hidden" name="section" value="{{ $key }}">
                                    <input type="hidden" name="hours" value="{{ (int) $r['hours'] }}">
                                    <input type="hidden" name="label" value="{{ $s['title'] }}: {{ $r['who'] }}">
                                    <textarea name="note" required minlength="8" placeholder="What did you do? e.g. Called her, refunded $24.50, she is happy"></textarea>
                                    <button type="submit">Save</button>
                                </form>
                            </details>
                            <details class="ask"><summary>ERP bug</summary>
                                <form method="POST" action="{{ route('open-items.act') }}">
                                    {{ csrf_field() }}
                                    <input type="hidden" name="key" value="{{ $r['key'] }}">
                                    <input type="hidden" name="action" value="bug">
                                    <input type="hidden" name="section" value="{{ $key }}">
                                    <input type="hidden" name="link" value="{{ $r['link'] }}">
                                    <input type="hidden" name="label" value="{{ $s['title'] }}: {{ $r['who'] }}">
                                    <textarea name="note" required minlength="8" placeholder="What in the ERP is broken or missing? e.g. Refund button errors on this order"></textarea>
                                    <button type="submit">Report bug</button>
                                </form>
                            </details>
                            @endif
                                    </div>
                                    @if(!empty($r['reopened']))<div class="oi-reopen">{{ $r['reopened'] }}</div>@endif
                                </div>
                            @endforeach
                        @endif
                    @endforeach
                </section>
            @endforeach
            <section class="oi-panel" data-b="week" style="display:none"></section>
            <section class="oi-panel" data-b="time" style="display:none"></section>
            <div style="display:none">@include('open_items.partials.focus')</div>
        </div>

    <aside class="oi-side" id="oi-side">
        <div class="oi-head"><h2>Reply with Claude</h2><button type="button" class="oi-pill" id="oi-side-close">Close</button></div>
        <div class="oi-side-body" id="oi-side-body">
            <div id="oi-side-empty">Click <strong>Reply with Claude</strong> on a row.</div>
            <details><summary style="cursor:pointer;font-weight:700">Reply tips</summary>@include('open_items.partials.text_tips')</details>
            <div id="oi-side-work" style="display:none;gap:10px">
                <div><strong id="oi-side-who"></strong> <span id="oi-side-via" style="font-size:12px;font-weight:700"></span></div>
                <div class="oi-quote" id="oi-side-msg"></div>
                <textarea id="oi-side-draft" placeholder="Claude is writing..."></textarea>
                <div class="oi-pills">
                    <button type="button" class="oi-pill" data-ask="make it shorter">Shorter</button>
                    <button type="button" class="oi-pill" data-ask="make it warmer and friendlier">Warmer</button>
                    <button type="button" class="oi-pill" data-ask="make it more apologetic">More sorry</button>
                    <button type="button" class="oi-pill" data-ask="offer a swap for a similar record">Offer a swap</button>
                </div>
                <div style="display:flex;gap:6px"><input type="text" id="oi-side-ask" placeholder="Tell Claude what to change"><button type="button" class="oi-pill" id="oi-side-redo">Redo</button></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button type="button" class="oi-go" id="oi-side-send" style="display:none">Send</button>
                    <button type="button" class="oi-go" id="oi-side-copy">Copy</button>
                    <a class="oi-btn" id="oi-side-open" href="#">Open</a>
                </div>
                <div id="oi-side-status" style="font-weight:700"></div>
            </div>
        </div>
    </aside>
    </div>

</div>
<script>
(function () {
    var f = document.getElementById('oi-flash');
    if (!f || !/^Nice\./.test(f.textContent.trim())) return;
    var colors = ['#FFEE94', '#3FA35B', '#F2C94C', '#174E7C', '#E8735A'];
    for (var i = 0; i < 70; i++) {
        var c = document.createElement('div');
        c.className = 'oi-confetti';
        c.style.left = Math.random() * 100 + 'vw';
        c.style.background = colors[i % colors.length];
        c.style.animationDelay = (Math.random() * 0.6) + 's';
        document.body.appendChild(c);
        setTimeout(function (el) { return function () { el.remove(); }; }(c), 2800);
    }
})();
(function () {
    var week = document.querySelector('.oi-panel[data-b="week"]');
    var time = document.querySelector('.oi-panel[data-b="time"]');
    ['oi-social'].forEach(function (id) { var el = document.getElementById(id); if (el && week) week.appendChild(el); });
    ['oi-bigger', 'oi-claude'].forEach(function (id) { var el = document.getElementById(id); if (el && time) time.appendChild(el); });
    var tiles = document.querySelectorAll('#oi-buckets .bk');
    function pick(b) {
        tiles.forEach(function (x) { x.classList.toggle('on', x.getAttribute('data-b') === b); });
        document.querySelectorAll('.oi-panel').forEach(function (p) { p.style.display = p.getAttribute('data-b') === b ? '' : 'none'; });
        try { sessionStorage.setItem('oiPanel', b); } catch (e) {}
    }
    tiles.forEach(function (x) { x.addEventListener('click', function () { pick(x.getAttribute('data-b')); window.scrollTo({top: 0, behavior: 'smooth'}); }); });
    var saved = null; try { saved = sessionStorage.getItem('oiPanel'); } catch (e) {}
    pick(saved && document.querySelector('.oi-panel[data-b="' + saved + '"]') ? saved : '{{ $startB }}');
})();
(function () {
    var side = document.getElementById('oi-side');
    if (!side) return;
    var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var cur = null, picked = null;
    var $ = function (id) { return document.getElementById(id); };
    function status(t) { $('oi-side-status').textContent = t || ''; }
    function post(url, body) {
        return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify(body) })
            .then(function (r) { return r.json(); });
    }
    function draft(ask) {
        if (!cur) return;
        var prev = $('oi-side-draft').value;
        $('oi-side-draft').value = ''; $('oi-side-draft').placeholder = 'Claude is writing...';
        status('');
        post('{{ route('open-items.draft') }}', { kind: cur.kind, who: cur.who, text: cur.text, via: cur.via, draft: ask ? prev : '', ask: ask || '' })
            .then(function (d) { if (d.ok) { $('oi-side-draft').value = d.draft; } else { $('oi-side-draft').value = prev; status(d.msg || 'Something went wrong.'); } })
            .catch(function () { $('oi-side-draft').value = prev; status('Could not reach Claude right now.'); });
    }
    document.querySelectorAll('.oi-acts .ai').forEach(function (b) {
        b.addEventListener('click', function () {
            cur = JSON.parse(b.getAttribute('data-reply'));
            if (picked) picked.classList.remove('picked');
            picked = b.closest('.oi-row'); if (picked) picked.classList.add('picked');
            $('oi-side-empty').style.display = 'none';
            $('oi-side-work').style.display = 'grid';
            $('oi-side-who').textContent = cur.who;
            $('oi-side-via').textContent = cur.via ? '(' + cur.via + ')' : '';
            $('oi-side-msg').innerHTML = '';
            var one = document.createElement('div'); one.className = 'oi-bub c'; one.textContent = cur.text; $('oi-side-msg').appendChild(one);
            $('oi-side-open').href = cur.link;
            $('oi-side-send').style.display = cur.canSend ? '' : 'none';
            $('oi-side-send').textContent = cur.canSend ? ('Send by ' + cur.via) : 'Send';
            $('oi-side-copy').textContent = cur.canSend ? 'Copy' : 'Copy, then reply in the inbox';
            side.classList.add('open'); $('oi-side-close').style.display = '';
            if (cur.commId) {
                // Full conversation, both sides, oldest first; Claude drafts from all of it.
                fetch('/communications/' + cur.commId + '/thread', { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (!d || !d.success || !(d.entries || []).length) { draft(''); return; }
                        var box = $('oi-side-msg'); box.innerHTML = '';
                        var lines = [];
                        d.entries.forEach(function (e) {
                            if (!e.text) return;
                            var b2 = document.createElement('div');
                            b2.className = 'oi-bub ' + (e.who === 'staff' ? 's' : 'c');
                            b2.textContent = e.text;
                            var sm = document.createElement('small'); sm.textContent = (e.who === 'staff' ? 'Us' : 'Customer') + (e.time_label ? ', ' + e.time_label : ''); b2.appendChild(sm);
                            box.appendChild(b2);
                            lines.push((e.who === 'staff' ? 'Nivessa: ' : 'Customer: ') + e.text);
                        });
                        box.scrollTop = box.scrollHeight;
                        cur.text = 'Full conversation so far (oldest first):\n' + lines.join('\n').slice(-3500);
                        draft('');
                    })
                    .catch(function () { draft(''); });
            } else {
                draft('');
            }
        });
    });
    document.querySelectorAll('#oi-side .oi-pill[data-ask]').forEach(function (p) {
        p.addEventListener('click', function () { draft(p.getAttribute('data-ask')); });
    });
    $('oi-side-redo').addEventListener('click', function () { var a = $('oi-side-ask').value.trim(); if (a) { draft(a); $('oi-side-ask').value = ''; } });
    $('oi-side-close').addEventListener('click', function () { side.classList.remove('open'); });
    $('oi-side-copy').addEventListener('click', function () {
        var t = $('oi-side-draft').value;
        (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () { status('Copied. Paste it in the inbox, send it, then click Fixed it on the row.'); }, function () { $('oi-side-draft').select(); status('Press Cmd+C to copy.'); });
    });
    $('oi-side-send').addEventListener('click', function () {
        var t = $('oi-side-draft').value.trim();
        if (!cur || !cur.commId || !t) return;
        if (/\[[^\]]+\]/.test(t)) { status('Fill in the [brackets] first.'); return; }
        var btn = this; btn.disabled = true; status('Sending...');
        post('/communications/' + cur.commId + '/send-reply', { message: t }).then(function (d) {
            btn.disabled = false;
            if (!d.success) { status(d.msg || 'Did not send.'); return; }
            post('{{ route('open-items.act') }}', { key: cur.key, action: 'fixed', note: 'Replied by ' + cur.via + ': ' + t, label: cur.label, section: cur.section, hours: cur.hours, link: cur.link })
                .then(function (r) { status('Sent and marked fixed. Refresh to see your count.'); });
            status('Sent and marked fixed.');
            if (picked) picked.style.display = 'none';
        }).catch(function () { btn.disabled = false; status('Did not send. Try again.'); });
    });
})();
</script>
@endsection
