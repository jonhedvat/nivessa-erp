@extends('layouts.app')
@section('title', 'Open Items')

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
.open-shell .oi-row { display:grid; grid-template-columns:minmax(120px,200px) 1fr auto auto; gap:12px; align-items:center; padding:10px 18px; border-bottom:1px solid var(--d-line); font-size:14px; color:var(--d-ink); }
.open-shell .oi-row:last-child { border-bottom:0; }
.open-shell .oi-who { font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.open-shell .oi-what { color:var(--d-ink-2); }
.open-shell .oi-age { font-weight:700; white-space:nowrap; }
.open-shell .oi-age.late { color:#B3261E; }
.open-shell .oi-open a { font-weight:700; color:var(--d-accent-text); white-space:nowrap; }
.open-shell .oi-empty { padding:12px 18px; font-size:14px; color:var(--d-good); font-weight:600; }
.open-shell .oi-err { padding:12px 18px; font-size:14px; color:var(--d-warn); font-weight:600; }

.open-shell .oi-row { grid-template-columns:minmax(120px,200px) 1fr auto auto; }
.open-shell .oi-row .oi-acts { grid-column:1 / -1; justify-content:flex-end; flex-wrap:wrap; }
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
.open-shell .oi-layout { display:grid; grid-template-columns:minmax(0,1fr) 380px; gap:20px; align-items:start; }
.open-shell .oi-side { position:sticky; top:12px; margin-top:16px; background:var(--d-surface); border:1px solid var(--d-line); border-radius:var(--d-radius); overflow:hidden; }
.open-shell .oi-side .oi-head { padding:12px 16px; }
.open-shell .oi-side-body { padding:12px 16px; display:grid; gap:10px; font-size:14px; color:var(--d-ink); max-height:calc(100vh - 110px); overflow:auto; }
.open-shell .oi-side textarea { width:100%; min-height:150px; font:inherit; font-size:14px; border:1px solid var(--d-line-2); border-radius:8px; padding:10px; color:var(--d-ink); background:var(--d-surface); }
.open-shell .oi-side input[type=text] { width:100%; font:inherit; font-size:13px; border:1px solid var(--d-line-2); border-radius:8px; padding:8px; color:var(--d-ink); }
.open-shell .oi-quote { background:var(--d-surface-2); border-radius:8px; padding:10px; white-space:pre-wrap; max-height:180px; overflow:auto; }
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
@media (max-width: 1100px) {
  .open-shell .oi-layout { grid-template-columns:1fr; }
  .open-shell .oi-side { position:fixed; left:auto; right:8px; bottom:8px; top:auto; width:min(420px, calc(100vw - 16px)); z-index:1050; display:none; box-shadow:0 10px 30px rgba(0,0,0,.25); }
  .open-shell .oi-side.open { display:block; }
  .open-shell .oi-side-body { max-height:70vh; }
}
@media (max-width: 700px) {
  .open-shell .oi-acts form { right:auto; left:0; width:260px; }
  .open-shell .oi-row { grid-template-columns:1fr auto; }
  .open-shell .oi-what { grid-column:1 / -1; }
}
</style>

<div class="open-shell">
    <div class="oi-top">
        <div>
            <h1>Open Items</h1>
            <p>Everything from the last 2 weeks still waiting on someone. Updated {{ $asOf }}.</p>
            <div class="oi-total {{ $total === 0 ? 'zero' : '' }}">{{ $total === 0 ? 'Nothing open. Nice work.' : $total . ' open' }}</div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="oi-btn" href="{{ url('/open-items?refresh=1') }}">Refresh</a>
            <a class="oi-btn" href="{{ url('/daily-checklist') }}">Daily checklist</a>
        </div>
    </div>

    @if(session('oi_msg'))<div class="oi-msg">{{ session('oi_msg') }}</div>@endif

    @php
        $pct = min(100, (int) round($sb['today'] * 100 / max(1, $sb['target'])));
        $toGo = max(0, $sb['target'] - $sb['today']);
        $lvlPct = $sb['next'] ? (int) round(($sb['total'] - $sb['levelFrom']) * 100 / max(1, $sb['next']['at'] - $sb['levelFrom'])) : 100;
    @endphp
    <div class="sb" id="oi-score">
        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px;flex-wrap:wrap">
            <h2 style="font-size:17px;font-weight:800;margin:0;color:var(--d-ink)">{{ $me }}'s score today: {{ $sb['today'] }} / {{ $sb['target'] }}</h2>
            <strong style="color:var(--d-ink)">{{ $toGo > 0 ? $toGo . ' points to hit today\'s goal' : 'Goal hit. Streak safe.' }}</strong>
        </div>
        <div class="sb-bar {{ $toGo === 0 ? 'hit' : '' }}"><span style="width:{{ $pct }}%"></span></div>
        <div class="sb-grid">
            <div class="sb-tile"><div class="k">Streak</div><div class="v">{{ $sb['streak'] }} {{ $sb['streak'] === 1 ? 'day' : 'days' }}</div><div class="s">Days in a row at {{ $sb['target'] }}+</div></div>
            <div class="sb-tile"><div class="k">Best day</div><div class="v">{{ $sb['best'] }}</div><div class="s">{{ $sb['today'] > $sb['best'] && $sb['best'] > 0 ? 'New record today!' : 'Beat it today' }}</div></div>
            <div class="sb-tile"><div class="k">This week</div><div class="v">{{ $sb['week'] }}</div><div class="s">Last week: {{ $sb['lastWeek'] }}</div></div>
            <div class="sb-tile"><div class="k">Level</div><div class="v">{{ $sb['level'] }}</div><div class="s">{{ $sb['next'] ? ($sb['next']['at'] - $sb['total']) . ' to ' . $sb['next']['name'] : 'Top level' }}</div><div class="sb-bar" style="height:6px;margin-top:6px"><span style="width:{{ $lvlPct }}%"></span></div></div>
        </div>
        @if(count($sb['feed']))
            <div class="sb-feed">@foreach($sb['feed'] as $e)<span class="{{ $e['pts'] < 0 ? 'neg' : '' }}">{{ $e['pts'] > 0 ? '+' : '' }}{{ $e['pts'] }} {{ \Illuminate\Support\Str::limit($e['why'], 40) }}</span>@endforeach</div>
        @endif
        <p style="margin:0;font-size:13px;color:var(--d-ink)">Points: unhappy customer 20, seller lead or stuck order 15, most others 10, quick replies 5. Fixed within a day of coming in: +{{ \App\Utils\OpenItemsUtil::FAST_BONUS }}. Clear the whole board: +{{ \App\Utils\OpenItemsUtil::CLEAR_BONUS }}. If a "fixed" item comes back, you lose those points plus 5.</p>
    </div>

    <div class="oi-card" id="oi-start">
        <div class="oi-head"><h2>How to use this page</h2></div>
        <div class="oi-done"><strong>1.</strong> Start with the first box and go down. Each row is one problem to solve today.</div>
        <div class="oi-done"><strong>2.</strong> Click <strong>Open</strong> on the row. Do what the line under the box title says (reply, ship, call, fix the order). To answer a customer, click <strong>Reply with Claude</strong> on the row: it writes the reply on the right, you check it and send it.</div>
        <div class="oi-done"><strong>3.</strong> When the problem is gone, click <strong>Fixed it</strong> and write one line about what you did, e.g. "Called her, sent a swap, she is happy". It disappears.</div>
        <div class="oi-done"><strong>4.</strong> If the ERP is broken or missing something you need, click <strong>ERP bug</strong> and write what happened. Do not leave it sitting.</div>
        <div class="oi-done"><strong>5.</strong> When every box says All clear, do today's social post, then the bigger jobs below.</div>
        <p class="oi-how">"Fixed it" only counts if the problem is really gone. Anything still open 48 hours later comes back here with your note.</p>
    </div>

    <div class="oi-layout"><div class="oi-main">
    @foreach($sections as $key => $s)
        <div class="oi-card" id="oi-{{ $key }}">
            <div class="oi-head">
                <h2>{{ $s['title'] }} <span class="oi-count {{ count($s['rows']) === 0 ? 'zero' : '' }}">{{ count($s['rows']) }}</span></h2>
                @if($s['link'])<a class="oi-btn" href="{{ $s['link'] }}">Open page</a>@endif
            </div>
            @if($s['how'])<p class="oi-how">{{ $s['how'] }}</p>@endif
            @if(!empty($s['older']))<p class="oi-how" style="background:var(--d-surface)">Plus {{ $s['older'] }} older than 2 weeks, not shown here. @if($s['link'])<a href="{{ $s['link'] }}" style="color:var(--d-accent-text);font-weight:700">See them</a>@endif</p>@endif
            @if($s['error'])
                <div class="oi-err">{{ $s['error'] }}</div>
            @elseif(count($s['rows']) === 0)
                <div class="oi-empty">All clear.</div>
            @else
                @foreach($s['rows'] as $r)
                    <div class="oi-row">
                        <div class="oi-who" title="{{ $r['who'] }}">{{ $r['who'] }}</div>
                        <div class="oi-what">{{ $r['what'] }}</div>
                        <div class="oi-age {{ ($r['late'] ?? ($r['hours'] >= 72)) ? 'late' : '' }}" title="{{ $r['since'] }}">{{ $r['age'] }}</div>
                        <div class="oi-open"><a href="{{ $r['link'] }}">Open</a></div>
                        <div class="oi-acts">
                            @if(!empty($r['reply']))
                                <button type="button" class="ai" data-reply="{{ json_encode(array_merge($r['reply'], ['who' => $r['who'], 'key' => $r['key'], 'section' => $key, 'hours' => (int) $r['hours'], 'label' => $s['title'] . ': ' . $r['who'], 'link' => $r['link']])) }}">Reply with Claude</button>
                            @endif
                            @if(!empty($r['key']))
                            <details class="fix"><summary>Fixed it <b>+{{ \App\Utils\OpenItemsUtil::pointsFor($key, (int) $r['hours']) }}</b></summary>
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
        </div>
    @endforeach

    @include('open_items.partials.focus')
    </div>

    <aside class="oi-side" id="oi-side">
        <div class="oi-head"><h2>Reply with Claude</h2><button type="button" class="oi-pill" id="oi-side-close" style="display:none">Close</button></div>
        <div class="oi-side-body" id="oi-side-body">
            <div id="oi-side-empty">Click <strong>Reply with Claude</strong> on any customer row. Claude writes a reply here, you check it, fix anything in [brackets], then send it.</div>
            <div id="oi-side-work" style="display:none;gap:10px">
                <div><strong id="oi-side-who"></strong> wrote:</div>
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

    <div class="oi-card" id="oi-done">
        <div class="oi-head"><h2>Fixed or reported today <span class="oi-count zero">{{ count($done) }}</span></h2></div>
        @forelse($done as $a)
            <div class="oi-done"><strong>{{ $a['label'] ?? '' }}</strong> ({{ $a['by'] ?? '' }}, {{ \Carbon\Carbon::parse($a['at'])->setTimezone('America/Los_Angeles')->format('g:i A') }}): {{ $a['note'] ?? '' }}</div>
        @empty
            <div class="oi-done">Nothing marked fixed yet today.</div>
        @endforelse
    </div>
</div>
<script>
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
            $('oi-side-msg').textContent = cur.text;
            $('oi-side-open').href = cur.link;
            $('oi-side-send').style.display = cur.canSend ? '' : 'none';
            $('oi-side-send').textContent = cur.canSend ? ('Send by ' + cur.via) : 'Send';
            $('oi-side-copy').textContent = cur.canSend ? 'Copy' : 'Copy, then reply in the inbox';
            side.classList.add('open'); $('oi-side-close').style.display = '';
            draft('');
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
                .then(function (r) { status('Sent and marked fixed. +' + ((r && r.pts) || 0) + ' points. Refresh to see your score.'); });
            status('Sent and marked fixed.');
            if (picked) picked.style.display = 'none';
        }).catch(function () { btn.disabled = false; status('Did not send. Try again.'); });
    });
})();
</script>
@endsection
