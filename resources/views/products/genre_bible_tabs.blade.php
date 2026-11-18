@extends('layouts.app')
@section('title', 'Genre Bible: Tabs to make')

@section('content')
<style>
.gb-wrap { max-width: 900px; margin: 0 auto; padding: 24px 16px 60px; color: #1F1B16; }
.gb-wrap h1 { font-size: 26px; font-weight: 700; margin: 0 0 6px; }
.gb-wrap h2 { font-size: 19px; font-weight: 700; margin: 26px 0 6px; }
.gb-tabs { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 0 14px; }
.gb-tabs a { padding: 8px 14px; border-radius: 999px; border: 1px solid #E1D7C4; color: #3B2E2A; font-weight: 600; text-decoration: none; }
.gb-tabs a.on { background: #3B2E2A; color: #fff; border-color: #3B2E2A; }
.gb-intro { color: #6B6155; font-size: 14px; margin: 0 0 12px; }
.gb-table { width: 100%; border-collapse: collapse; font-size: 15px; background: #fff; border: 1px solid #E6DCCF; }
.gb-table th { text-align: left; background: #FAF6EE; padding: 9px 12px; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: #6B6155; }
.gb-table td { padding: 8px 12px; border-top: 1px solid #F3ECE1; vertical-align: middle; }
.gb-futag { display: inline-block; border: 1px solid #1F1B16; padding: 0 5px; font-weight: 700; text-transform: uppercase; font-size: 13px; }
.gb-btn { height: 32px; font-size: 13px; font-weight: 700; border: 0; border-radius: 6px; background: #3B2E2A; color: #fff; padding: 0 12px; }
.gb-btn:disabled { opacity: .5; }
.gb-done td { opacity: .45; }
.gb-select { height: 38px; font-size: 15px; border: 1px solid #E1D7C4; border-radius: 10px; padding: 0 10px; background: #fff; }
.gb-empty { color: #6B6155; font-size: 15px; padding: 12px 0; }
</style>
<div class="gb-wrap">
    <h1>Genre Bible</h1>
    <div class="gb-tabs">
        <a href="/genre-bible">All artists</a>
        <a href="/genre-bible?tab=decide">Needs a decision ({{ $decideCount }})</a>
        <a href="/genre-bible?tab=tabs" class="on">Tabs to make ({{ $toMakeCount }})</a>
    </div>

    <p>
        <label for="gbStore" style="font-weight:600">Store</label>
        <select class="gb-select" id="gbStore">
            <option value="">Both stores</option>
            @foreach ($stores as $st => $label)
                <option value="{{ $st }}">{{ $label }}</option>
            @endforeach
        </select>
    </p>

    <h2>Tabs to make</h2>
    <p class="gb-intro">Artists with a File under who have records in the store but no divider tab there yet. Make the tab, then tick it off.</p>
    @if (count($rows))
        <table class="gb-table" id="gbMake">
            <thead><tr><th>Store</th><th>Tab</th><th>Artist</th><th>In stock</th><th></th></tr></thead>
            <tbody>
            @foreach ($rows as $r)
                <tr data-a="{{ $r['name'] }}" data-s="{{ $r['store'] }}">
                    <td>{{ $r['storeLabel'] }}</td>
                    <td><span class="gb-futag">{{ $r['fileUnder'] }}</span></td>
                    <td><a href="/genre-bible?q={{ urlencode($r['name']) }}">{{ $r['name'] }}</a> <span style="color:#8E8273">({{ $r['genre'] }})</span></td>
                    <td>{{ $r['stock'] }}</td>
                    <td><button type="button" class="gb-btn gb-made">Tab made</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="gb-empty">Nothing to make. Every artist with a File under has a tab in each store that carries them.</div>
    @endif

    @if ($canEdit)
        <h2>Suggested: give them their own tab</h2>
        <p class="gb-intro">
            Artists that sell: {{ $suggestMin }}+ records sold at that store in the last 12 months, with records still in stock,
            and no File under yet. Slow sellers are left out. <b>Make tab</b> sets their File under to their name, so their labels say
            <span class="gb-futag">File under: Artist</span>, and moves them to Tabs to make.
        </p>
        @if (count($suggest))
            <table class="gb-table" id="gbSuggest">
                <thead><tr><th>Store</th><th>Artist</th><th>Sold (12 mo)</th><th>In stock</th><th></th></tr></thead>
                <tbody>
                @foreach ($suggest as $r)
                    <tr data-a="{{ $r['name'] }}" data-s="{{ $r['store'] }}">
                        <td>{{ $r['storeLabel'] }}</td>
                        <td><a href="/genre-bible?q={{ urlencode($r['name']) }}">{{ $r['name'] }}</a></td>
                        <td><b>{{ $r['sold'] }}</b></td>
                        <td>{{ $r['stock'] }}</td>
                        <td style="white-space:nowrap"><button type="button" class="gb-btn gb-own">Make tab</button> <button type="button" class="gb-btn gb-has" style="background:#8E8273">Already has one</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <div class="gb-empty">No suggestions right now.</div>
        @endif
    @endif
</div>
@endsection

@section('javascript')
<script>
(function () {
    var token = (document.querySelector('meta[name="csrf-token"]') || {}).content;
    function post(url, body) {
        return fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.error || ('Error ' + r.status)); return j; }); });
    }
    var store = document.getElementById('gbStore');
    store.addEventListener('change', function () {
        document.querySelectorAll('tr[data-s]').forEach(function (tr) {
            tr.style.display = !store.value || tr.getAttribute('data-s') === store.value ? '' : 'none';
        });
    });
    document.querySelector('.gb-wrap').addEventListener('click', function (e) {
        var b = e.target, tr = b.closest('tr');
        if (!tr || b.tagName !== 'BUTTON') return;
        var name = tr.getAttribute('data-a'), st = tr.getAttribute('data-s'), req;
        if (b.classList.contains('gb-made') || b.classList.contains('gb-has')) {
            req = post('/genre-bible/tab', { name: name, store: st, has: true });
            if (b.classList.contains('gb-has')) {
                // Has a tab already: record their File under as their own name too.
                req = req.then(function () { return post('/genre-bible/file-under', { name: name, file_under: name }); });
            }
        } else if (b.classList.contains('gb-own')) {
            req = post('/genre-bible/file-under', { name: name, file_under: name });
        } else {
            return;
        }
        tr.querySelectorAll('button').forEach(function (x) { x.disabled = true; });
        req.then(function () {
            tr.classList.add('gb-done');
            b.textContent = b.classList.contains('gb-own') ? 'Saved: reload to see it in Tabs to make' : 'Done';
        }).catch(function (err) {
            alert(err.message);
            tr.querySelectorAll('button').forEach(function (x) { x.disabled = false; });
        });
    });
})();
</script>
@endsection
