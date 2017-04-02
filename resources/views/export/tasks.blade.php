<tr>
    <td>{{ trans('texts.client') }}</td>
    @if ($multiUser)
        <td>{{ trans('texts.user') }}</td>
    @endif
    <td>{{ trans('texts.start_date') }}</td>
    <td>{{ trans('texts.duration') }}</td>
    <td>{{ trans('texts.description') }}</td>
</tr>

@foreach ($tasks as $task)
    @if (!$task->client || !$task->client->is_deleted)
        <tr>
            <td>{{ $task->present()->client }}</td>
            @if ($multiUser)
                <td>{{ $task->present()->user }}</td>
            @endif
            <td>{{ $task->getStartTime() }}</td>
            <td>{{ $task->getDuration() }}</td>
            <td>{{ $task->description }}</td>
        </tr>
    @endif
@endforeach
