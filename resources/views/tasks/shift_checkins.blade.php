@extends('layouts.app')
@section('title', 'Shift task check-ins')

@section('content')
<section class="content-header">
    <h1>Shift task check-ins <small>last {{ $days }} days</small></h1>
</section>

<section class="content">
    <p>
        @foreach([1 => 'Today', 7 => '7 days', 30 => '30 days'] as $d => $label)
            <a href="{{ route('tasks.shift-checkins', ['days' => $d]) }}" class="btn btn-sm {{ $days == $d ? 'btn-primary' : 'btn-default' }}">{{ $label }}</a>
        @endforeach
    </p>
    <div class="box box-solid">
        <div class="box-body table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr><th>Shift date</th><th>Employee</th><th>Task</th><th>Understood (open)</th><th>End of shift</th></tr>
                </thead>
                <tbody>
                @forelse($lines as $l)
                    <tr>
                        <td>{{ $l['date']->format('D M j') }}</td>
                        <td>{{ $l['user'] }}</td>
                        <td><a href="{{ route('tasks.edit', $l['task_id']) }}">{{ $l['task'] }}</a></td>
                        <td>{{ $l['understood_at'] ? $l['understood_at']->format('g:i A') : '—' }}</td>
                        <td>
                            @if($l['end'] === 'done')
                                <span class="label label-success">Did it</span> {{ $l['end_at']->format('g:i A') }}
                            @elseif($l['end'] === 'not_done')
                                <span class="label label-danger">Didn't do it</span> {{ $l['end_at']->format('g:i A') }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">No check-ins yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@stop
