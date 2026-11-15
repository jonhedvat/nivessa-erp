@extends('layouts.app')
@section('title', 'Start Shift')

@section('content')
<section class="content-header">
    <h1>You're clocked in <small>here's what's on your plate today</small></h1>
</section>

<section class="content">
    @if(empty($dueTasks))
        <div class="alert alert-success">
            <strong>Nothing due today.</strong> You're all caught up — have a good shift!
        </div>
        <a href="{{ $continueUrl }}" class="btn btn-primary btn-lg"><i class="fa fa-arrow-right"></i> Continue to POS</a>
    @elseif(!$requireAck)
        @include('tasks.partials.due_today_bubble', ['dueTodayTasks' => $dueTasks])
        <a href="{{ $continueUrl }}" class="btn btn-primary btn-lg"><i class="fa fa-arrow-right"></i> Continue to POS</a>
    @else
        <form method="POST" action="{{ route('tasks.start-shift.ack') }}" id="start_shift_ack_form">
            {{ csrf_field() }}
            <input type="hidden" name="sub_type" value="{{ request('sub_type') }}">
            <input type="hidden" name="store" value="{{ $store }}">
            <div style="background:#FFF3E0;border:1px solid #F0C27B;border-left:6px solid #E8912B;border-radius:12px;padding:16px 20px;margin:15px 0;font-family:'Inter Tight',system-ui,sans-serif;">
                <div style="font-size:18px;font-weight:800;color:#5A4410;">Your tasks for today</div>
                <div style="font-size:14px;color:#7a6a3a;margin-bottom:12px;">Read each one and tick <strong>I understand</strong>. At close you'll check off whether you did it.</div>
                @foreach($dueTasks as $t)
                <label style="display:flex;align-items:flex-start;gap:12px;background:#fff;border:1px solid #F0DDBE;border-radius:8px;padding:12px 14px;margin-bottom:8px;cursor:pointer;font-weight:normal;">
                    <input type="checkbox" name="ack[]" value="{{ $t['id'] }}" class="start-shift-ack" required style="width:22px;height:22px;margin-top:2px;flex:0 0 auto;">
                    <span style="flex:1 1 auto;">
                        <span class="label label-{{ ['high'=>'danger','medium'=>'warning','low'=>'default'][$t['priority']] ?? 'default' }}">{{ ucfirst($t['priority']) }}</span>
                        <strong style="font-size:15px;color:#333;margin-left:6px;">{{ $t['title'] }}</strong>
                        @if(!empty($t['description']))
                            <span style="display:block;color:#666;font-size:13px;margin-top:4px;white-space:pre-line;">{{ $t['description'] }}</span>
                        @endif
                        <span style="display:block;color:#B26A00;font-size:12px;font-weight:700;margin-top:4px;">I understand</span>
                    </span>
                </label>
                @endforeach
            </div>
            <button type="submit" class="btn btn-primary btn-lg" id="start_shift_continue" disabled><i class="fa fa-arrow-right"></i> Continue to POS</button>
            <span id="start_shift_left" style="margin-left:10px;color:#7a6a3a;"></span>
        </form>
        <script>
        (function () {
            var boxes = document.querySelectorAll('.start-shift-ack');
            var btn = document.getElementById('start_shift_continue');
            var left = document.getElementById('start_shift_left');
            function sync() {
                var n = 0;
                boxes.forEach(function (b) { if (!b.checked) { n++; } });
                btn.disabled = n > 0;
                left.textContent = n > 0 ? (n + ' left to tick') : '';
            }
            boxes.forEach(function (b) { b.addEventListener('change', sync); });
            sync();
        })();
        </script>
    @endif
</section>
@stop
