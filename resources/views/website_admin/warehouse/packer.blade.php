@extends('layouts.app')

@section('title', 'Packer View')

@section('content')
@include('sale_pos.partials._redesign_v2')
<script>document.body.classList.add('pos-v2');</script>
@include('events.partials._styles')
@include('website_admin.warehouse._styles')

<style>
  .pk-pills { display:flex; flex-wrap:wrap; gap:6px; }
  .pk-pills a { padding:10px 16px; border-radius:999px; font-size:14px; font-weight:600; background:#f3f4f6; color:#374151; text-decoration:none; }
  .pk-pills a.on { background:#dbeafe; color:#1e40af; }
  .pk-zone { margin-bottom:22px; }
  .pk-zone-head { display:flex; justify-content:space-between; align-items:center; gap:12px; background:#eef2ff; border:1px solid #c7d2fe; border-radius:12px; padding:12px 16px; margin-bottom:10px; position:sticky; top:0; z-index:5; }
  .pk-zone-head h2 { margin:0; font-size:19px; font-weight:700; color:#312e81; }
  .pk-zone-head .s { font-size:13px; color:#4338ca; margin-top:2px; }
  .pk-zone-head .p { font-size:24px; font-weight:800; color:#312e81; text-align:right; line-height:1; }
  .pk-zone-head .p small { display:block; font-size:11px; font-weight:600; color:#6366f1; margin-top:3px; }
  .pk-item { display:grid; grid-template-columns:96px 1fr auto; gap:14px; align-items:center; background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:12px; margin-bottom:8px; }
  .pk-item.st-picked { background:#f0fdf4; border-color:#bbf7d0; }
  .pk-item.st-cant { background:#fef2f2; border-color:#fecaca; }
  .pk-img { width:96px; height:96px; border-radius:10px; object-fit:cover; background:#e5e7eb; display:block; cursor:zoom-in; border:0; padding:0; position:relative; }
  .pk-img img { width:96px; height:96px; border-radius:10px; object-fit:cover; display:block; }
  .pk-img .g { position:absolute; top:-6px; right:-6px; background:#eab308; color:#fff; font-size:11px; font-weight:700; border-radius:999px; padding:2px 6px; }
  .pk-name { font-size:16px; font-weight:700; color:#111827; line-height:1.3; }
  .pk-artist { font-size:14px; font-weight:600; color:#4b5563; line-height:1.3; }
  .pk-loc { display:inline-block; background:#111827; color:#fff; font-weight:800; font-size:15px; border-radius:7px; padding:3px 10px; margin-right:6px; letter-spacing:.3px; }
  .pk-loc.none { background:#fee2e2; color:#991b1b; font-size:13px; font-weight:700; }
  .pk-cat { font-size:13px; color:#374151; margin-top:4px; }
  .pk-note { margin-top:8px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:8px 10px; font-size:14px; color:#78350f; }
  .pk-note .by { font-size:11.5px; color:#92400e; margin-top:2px; }
  .pk-note-btn { margin-top:8px; background:none; border:1px dashed #9ca3af; color:#374151; border-radius:8px; padding:6px 12px; font-size:13px; font-weight:600; cursor:pointer; }
  .pk-note-edit { margin-top:8px; display:none; }
  .pk-note-edit textarea { width:100%; min-height:60px; font-size:15px; padding:8px; border:1px solid #d1d5db; border-radius:8px; }
  .pk-note-edit .row { display:flex; gap:8px; margin-top:6px; }
  .pk-meta { font-size:13px; color:#4b5563; margin-top:3px; }
  .pk-meta a { color:#2563eb; font-weight:600; text-decoration:none; }
  .pk-tags { margin-top:6px; }
  .pk-qty { display:inline-block; background:#111827; color:#fff; font-weight:800; font-size:13px; border-radius:6px; padding:2px 8px; margin-right:6px; }
  .pk-actions { display:flex; flex-direction:column; gap:8px; min-width:150px; }
  .pk-actions .wh-btn { min-height:50px; font-size:15px; border-radius:10px; }
  .pk-done { text-align:center; font-weight:700; font-size:14px; padding:8px; border-radius:10px; }
  .pk-done.ok { background:#dcfce7; color:#166534; }
  .pk-done.bad { background:#fee2e2; color:#991b1b; }
  .pk-alt { font-size:12px; color:#92400e; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:6px 8px; text-align:center; }
  .pk-by { font-size:12px; color:#6b7280; text-align:center; }
  .pk-toast { position:fixed; left:50%; bottom:22px; transform:translateX(-50%); background:#111827; color:#fff; padding:12px 18px; border-radius:10px; font-size:14px; z-index:2000; display:none; max-width:90vw; }
  .pk-toast.err { background:#b91c1c; }
  @media (max-width: 640px) {
    .pk-item { grid-template-columns:72px 1fr; }
    .pk-img, .pk-img img { width:72px; height:72px; }
    .pk-actions { grid-column:1 / -1; flex-direction:row; }
    .pk-actions > * { flex:1; }
  }
</style>

@php
  $tz = 'America/Los_Angeles';
  $q = array_filter($f, fn($v) => $v !== '');
  $url = fn($over) => route('wa.warehouse.packer', array_filter(array_merge($q, $over), fn($v) => $v !== '' && $v !== null));
@endphp

<div class="ev-wrap ev-wrap-wide">
  <div class="ev-head">
    <div>
      <h1>Packer View</h1>
      <p class="sub">Every item to pick, grouped by zone. You are picking as <b>{{ \App\Services\WebsiteBridge::actor() }}</b>, so that name is saved on each pick.</p>
    </div>
    <a href="{{ $url([]) }}" class="wh-btn">Refresh</a>
  </div>

  @include('website_admin.warehouse._nav')

  <div class="wh-filters">
    <div class="wh-field">
      <label>Store</label>
      <div class="pk-pills">
        @foreach(['' => 'All stores', 'Hollywood' => 'Hollywood', 'Pico' => 'Pico'] as $v => $l)
          <a href="{{ $url(['store' => $v, 'page' => null]) }}" class="{{ $f['store'] === $v ? 'on' : '' }}">{{ $l }}</a>
        @endforeach
      </div>
    </div>
    <div class="wh-field">
      <label>Picking Status</label>
      <div class="pk-pills">
        @foreach(['' => 'All', 'Not Started' => 'Not started', 'In Progress' => 'In progress', 'Completed' => 'Completed', 'Issues' => 'Issues'] as $v => $l)
          <a href="{{ $url(['status' => $v, 'page' => null]) }}" class="{{ $f['status'] === $v ? 'on' : '' }}">{{ $l }}</a>
        @endforeach
      </div>
    </div>
    <form method="GET" action="{{ route('wa.warehouse.packer') }}" style="display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end;">
      <input type="hidden" name="store" value="{{ $f['store'] }}">
      <input type="hidden" name="status" value="{{ $f['status'] }}">
      <div class="wh-field">
        <label>Zone</label>
        <select name="zone" onchange="this.form.submit()">
          <option value="">All zones</option>
          @foreach($zones as $z)
            <option value="{{ $z }}" @if($f['zone'] === $z) selected @endif>{{ $z }}</option>
          @endforeach
        </select>
      </div>
      <div class="wh-field">
        <label>Sort items by</label>
        <select name="sort" onchange="this.form.submit()">
          @foreach(['' => 'Newest order first', 'location' => 'Location code', 'order' => 'Order number', 'customer' => 'Customer', 'item' => 'Item name', 'status' => 'Status', 'qty' => 'Quantity'] as $v => $l)
            <option value="{{ $v }}" @if($f['sort'] === $v) selected @endif>{{ $l }}</option>
          @endforeach
        </select>
      </div>
    </form>
  </div>

  @if($summary)
    <div class="wh-stats">
      <div class="wh-stat"><div class="l">Total Orders</div><div class="v">{{ $summary['total_orders'] ?? 0 }}</div></div>
      <div class="wh-stat"><div class="l">Not Started</div><div class="v">{{ $summary['not_started'] ?? 0 }}</div><div class="h">Items not picked</div></div>
      <div class="wh-stat amber"><div class="l">In Progress</div><div class="v">{{ $summary['in_progress'] ?? 0 }}</div></div>
      <div class="wh-stat green"><div class="l">Completed</div><div class="v">{{ $summary['completed'] ?? 0 }}</div><div class="h">Items picked</div></div>
      @if(($summary['issues'] ?? 0) > 0)
        <div class="wh-stat red"><div class="l">Can't Find</div><div class="v">{{ $summary['issues'] }}</div></div>
      @endif
    </div>
  @endif

  @if($error)
    <div class="wh-note err">Could not load the pick list: {{ $error }}</div>
  @elseif(!count($grouped))
    <div class="wh-card"><div class="wh-card-pad empty">Nothing to pick for these filters.</div></div>
  @else
    @foreach($grouped as $zone => $items)
      @php
        $st = $zoneStats[$zone] ?? ['total' => count($items), 'picked' => 0, 'not_picked' => count($items), 'cant_find' => 0];
        $zid = 'z' . substr(md5($zone), 0, 8);
      @endphp
      <section class="pk-zone" id="{{ $zid }}" data-total="{{ (int) ($st['total'] ?? 0) }}" data-picked="{{ (int) ($st['picked'] ?? 0) }}" data-pending="{{ (int) ($st['not_picked'] ?? 0) }}">
        <div class="pk-zone-head">
          <div>
            <h2>{{ $zone }}</h2>
            <div class="s"><span class="js-total">{{ (int) ($st['total'] ?? 0) }}</span> items, <span class="js-picked">{{ (int) ($st['picked'] ?? 0) }}</span> picked, <span class="js-pending">{{ (int) ($st['not_picked'] ?? 0) }}</span> to go</div>
          </div>
          <div class="p"><span class="js-pct">{{ ($st['total'] ?? 0) ? round(($st['picked'] ?? 0) / max(1, $st['total']) * 100) : 0 }}</span>%<small>Complete</small></div>
        </div>

        @foreach($items as $it)
          @php
            $e = $erp[(string) ($it['_id'] ?? '')] ?? ['artist' => '', 'album' => ($it['name'] ?? 'Item'), 'category' => null, 'subcategory' => null, 'bin' => ''];
            $dloc = trim((string) (($it['discogs_location'] ?? '') ?: ($it['location']['discogs_location_code'] ?? '')));
            $ps = $it['picking_status'] ?? 'Not Picked';
            $cls = $ps === 'Picked' ? 'st-picked' : ($ps === "Can't Find" ? 'st-cant' : '');
            // Can't-find reminder: sealed vinyl/CD/cassette -> order from AMS/Alliance; used -> dropship via eBay/Discogs.
            $catTxt = strtolower(($e['category'] ?? '') . ' ' . ($e['subcategory'] ?? ''));
            $alt = '';
            if (preg_match('/vinyl|\blp\b|\bcds?\b|compact disc|cassette/', $catTxt)) {
              if (strpos($catTxt, 'seal') !== false || preg_match('/\bnew\b/', strtolower($e['category'] ?? ''))) $alt = 'We can use AMS or Alliance if you do not find it.';
              elseif (strpos($catTxt, 'used') !== false) $alt = 'Reminder: we can dropship this on eBay or Discogs if you do not find it.';
            }
          @endphp
          <div class="pk-item {{ $cls }}" data-order="{{ $it['order_id'] ?? '' }}" data-item="{{ $it['_id'] ?? '' }}" data-status="{{ $ps }}" data-zone="{{ $zid }}" data-alt="{{ $alt }}">
            @if(!empty($it['image']))
              <button type="button" class="pk-img" onclick="pkZoom(this.querySelector('img').src)"><img src="{{ $it['image'] }}" alt="" loading="lazy">@if(($it['cover_type'] ?? '') === 'generic')<span class="g">G</span>@endif</button>
            @else
              <div class="pk-img"></div>
            @endif
            <div>
              @if($e['artist'] !== '')<div class="pk-artist">{{ $e['artist'] }}</div>@endif
              <div class="pk-name">@if(($it['quantity'] ?? 1) > 1)<span class="pk-qty">x{{ $it['quantity'] }}</span>@endif{{ $e['album'] }}</div>
              <div class="pk-cat">
                @if($e['bin'] !== '')<span class="pk-loc">{{ $e['bin'] }}</span>@elseif($dloc !== '')<span class="pk-loc">{{ $dloc }}</span>@else<span class="pk-loc none">No location</span>@endif
                @if($e['category']){{ $e['category'] }}@if($e['subcategory']) › {{ $e['subcategory'] }}@endif @endif
              </div>
              <div class="pk-meta">
                <a href="{{ route('wa.warehouse.show', $it['order_id'] ?? '') }}">{{ ($it['order_number'] ?? '') ?: ($it['external_order_id'] ?? '') }}</a>
                · {{ $it['order_source'] ?? '' }} · {{ $it['customer_name'] ?? '' }}
              </div>
              <div class="pk-tags">
                <span class="wh-tag zone">{{ ($it['zone'] ?? '') ?: $zone }}</span>
                @if($dloc !== '' && strcasecmp($dloc, $e['bin']) !== 0 && $e['bin'] !== '')<span class="wh-tag code">Discogs: {{ $dloc }}</span>@endif
                @if(!empty($it['location']['sub_location']))<span class="wh-tag sub">{{ $it['location']['sub_location'] }}</span>@endif
                @if(!empty($it['location']['shelf_details']))<span class="wh-tag shelf">{{ $it['location']['shelf_details'] }}</span>@endif
                @if(!empty($it['media_condition']))<span class="wh-pill blue">Vinyl: {{ $it['media_condition'] }}</span>@endif
                @if(!empty($it['sleeve_condition']))<span class="wh-pill purple">Cover: {{ $it['sleeve_condition'] }}</span>@endif
                @if(($it['cover_type'] ?? '') === 'generic')<span class="wh-pill yellow">Generic cover</span>@endif
              </div>
              @if(!empty($it['picking_notes']))<div class="pk-meta">Note: {{ $it['picking_notes'] }}</div>@endif
              @php
                $pn = trim((string) ($it['packer_note']['text'] ?? ''));
                $pnBy = $it['packer_note']['by'] ?? '';
                $pnAt = $it['packer_note']['at'] ?? '';
              @endphp
              <div class="js-note-view">
                @if($pn !== '')
                  <div class="pk-note"><div class="js-note-text">{{ $pn }}</div><div class="by">{{ $pnBy }}@if($pnAt) · {{ \Carbon\Carbon::parse($pnAt)->setTimezone($tz)->format('M j, g:i a') }}@endif</div></div>
                @endif
                <button type="button" class="pk-note-btn" onclick="pkNoteOpen(this)">{{ $pn !== '' ? 'Edit note' : '+ Add note' }}</button>
              </div>
              <div class="pk-note-edit js-note-edit">
                <textarea maxlength="1000" placeholder="e.g. Moved to HW / Sold this morning">{{ $pn }}</textarea>
                <div class="row">
                  <button type="button" class="wh-btn wh-btn-green" onclick="pkNoteSave(this)">Save note</button>
                  <button type="button" class="wh-btn" onclick="pkNoteClose(this)">Cancel</button>
                </div>
              </div>
            </div>
            <div class="pk-actions js-actions">
              @if($ps === 'Not Picked')
                <button type="button" class="wh-btn wh-btn-green" onclick="pkPick(this, 'Picked')">Picked</button>
                <button type="button" class="wh-btn wh-btn-red" onclick="pkPick(this, &quot;Can't Find&quot;)">Can't find</button>
              @else
                <div class="pk-done {{ $ps === 'Picked' ? 'ok' : 'bad' }}">{{ $ps === 'Picked' ? 'Picked' : "Can't find" }}</div>
                @if($ps !== 'Picked' && $alt !== '')
                  <div class="pk-alt">{{ $alt }}</div>
                @endif
                @if(!empty($it['picked_by_name']))
                  <div class="pk-by">{{ $it['picked_by_name'] }}@if(!empty($it['picked_at'])), {{ \Carbon\Carbon::parse($it['picked_at'])->setTimezone($tz)->format('M j, g:i a') }}@endif</div>
                @endif
                <button type="button" class="wh-btn" onclick="pkPick(this, 'Not Picked')">Undo</button>
              @endif
            </div>
          </div>
        @endforeach
      </section>
    @endforeach

    @if($page > 1 || $hasMore)
      <div class="wh-pager">
        <div>Page {{ $page }}. 100 items per page.</div>
        <div class="pages">
          @if($page > 1)<a href="{{ $url(['page' => $page - 1 > 1 ? $page - 1 : null]) }}">Previous</a>@endif
          @if($hasMore)<a href="{{ $url(['page' => $page + 1]) }}">Next</a>@endif
        </div>
      </div>
    @endif
  @endif
</div>

<dialog id="pk-zoom" class="wh-imgzoom" onclick="this.close()"><img id="pk-zoom-img" src="" alt=""></dialog>
<div id="pk-toast" class="pk-toast"></div>

<script>
  var pkUrl = {!! json_encode(route('wa.warehouse.pick', ['orderId' => '__O__', 'itemId' => '__I__']), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
  var pkCsrf = @json(csrf_token());
  var pkToastTimer = null;

  function pkZoom(src) {
    document.getElementById('pk-zoom-img').src = src;
    document.getElementById('pk-zoom').showModal();
  }

  function pkToast(msg, isErr) {
    var t = document.getElementById('pk-toast');
    t.textContent = msg;
    t.className = 'pk-toast' + (isErr ? ' err' : '');
    t.style.display = 'block';
    clearTimeout(pkToastTimer);
    pkToastTimer = setTimeout(function () { t.style.display = 'none'; }, isErr ? 5000 : 1800);
  }

  function pkEsc(s) {
    return String(s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; });
  }

  function pkRender(row, status, by) {
    var box = row.querySelector('.js-actions');
    row.dataset.status = status;
    row.classList.remove('st-picked', 'st-cant');
    if (status === 'Not Picked') {
      box.innerHTML = '<button type="button" class="wh-btn wh-btn-green" onclick="pkPick(this, \'Picked\')">Picked</button>'
        + '<button type="button" class="wh-btn wh-btn-red" onclick="pkPick(this, &quot;Can\'t Find&quot;)">Can\'t find</button>';
    } else {
      row.classList.add(status === 'Picked' ? 'st-picked' : 'st-cant');
      box.innerHTML = '<div class="pk-done ' + (status === 'Picked' ? 'ok' : 'bad') + '">' + (status === 'Picked' ? 'Picked' : 'Can\'t find') + '</div>'
        + (status !== 'Picked' && row.dataset.alt ? '<div class="pk-alt">' + pkEsc(row.dataset.alt) + '</div>' : '')
        + (by ? '<div class="pk-by">' + pkEsc(by) + ', just now</div>' : '')
        + '<button type="button" class="wh-btn" onclick="pkPick(this, \'Not Picked\')">Undo</button>';
    }
  }

  function pkCount(row, from, to) {
    var z = document.getElementById(row.dataset.zone);
    if (!z) return;
    var picked = +z.dataset.picked, pending = +z.dataset.pending, total = +z.dataset.total;
    if (from === 'Picked') picked--; if (from === 'Not Picked') pending--;
    if (to === 'Picked') picked++; if (to === 'Not Picked') pending++;
    z.dataset.picked = picked; z.dataset.pending = pending;
    z.querySelector('.js-picked').textContent = picked;
    z.querySelector('.js-pending').textContent = pending;
    z.querySelector('.js-pct').textContent = total ? Math.round(picked / total * 100) : 0;
  }

  var pkNoteUrl = {!! json_encode(route('wa.warehouse.itemNote', ['orderId' => '__O__', 'itemId' => '__I__']), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};

  function pkNoteOpen(btn) {
    var row = btn.closest('.pk-item');
    row.querySelector('.js-note-view').style.display = 'none';
    row.querySelector('.js-note-edit').style.display = 'block';
    row.querySelector('.js-note-edit textarea').focus();
  }

  function pkNoteClose(btn) {
    var row = btn.closest('.pk-item');
    row.querySelector('.js-note-edit').style.display = 'none';
    row.querySelector('.js-note-view').style.display = '';
  }

  function pkNoteSave(btn) {
    var row = btn.closest('.pk-item');
    var text = row.querySelector('.js-note-edit textarea').value.trim();
    btn.disabled = true;
    fetch(pkNoteUrl.replace('__O__', row.dataset.order).replace('__I__', row.dataset.item), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': pkCsrf },
      body: JSON.stringify({ notes: text })
    }).then(function (r) { return r.json().catch(function () { return { success: false, message: 'The ERP returned an error.' }; }); })
      .then(function (j) {
        btn.disabled = false;
        if (!j.success) throw new Error(j.message || 'Could not save the note.');
        var view = row.querySelector('.js-note-view');
        view.innerHTML = (j.text ? '<div class="pk-note"><div class="js-note-text">' + pkEsc(j.text) + '</div><div class="by">' + pkEsc(j.by || '') + ' · just now</div></div>' : '')
          + '<button type="button" class="pk-note-btn" onclick="pkNoteOpen(this)">' + (j.text ? 'Edit note' : '+ Add note') + '</button>';
        pkNoteClose(btn);
        pkToast(j.text ? 'Note saved.' : 'Note removed.');
      })
      .catch(function (e) {
        btn.disabled = false;
        pkToast((e && e.message) || 'Could not save the note.', true);
      });
  }

  // Updates the row right away, then saves. Puts it back if the save fails.
  function pkPick(btn, status) {
    var row = btn.closest('.pk-item');
    var before = row.dataset.status;
    if (row.dataset.busy === '1') return;
    row.dataset.busy = '1';
    pkRender(row, status, status === 'Not Picked' ? '' : @json(\App\Services\WebsiteBridge::actor()));
    pkCount(row, before, status);
    fetch(pkUrl.replace('__O__', row.dataset.order).replace('__I__', row.dataset.item), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': pkCsrf },
      body: JSON.stringify({ status: status })
    }).then(function (r) { return r.json().catch(function () { return { success: false, message: 'The ERP returned an error.' }; }); })
      .then(function (j) {
        row.dataset.busy = '0';
        if (!j.success) throw new Error(j.message || 'Could not save the pick.');
        pkToast(status === 'Not Picked' ? 'Undone.' : (status === 'Picked' ? 'Marked picked.' : ("Marked can't find." + (row.dataset.alt ? ' ' + row.dataset.alt : ''))));
      })
      .catch(function (e) {
        row.dataset.busy = '0';
        pkRender(row, before, '');
        pkCount(row, status, before);
        pkToast((e && e.message) || 'Could not save the pick.', true);
      });
  }
</script>
@endsection
