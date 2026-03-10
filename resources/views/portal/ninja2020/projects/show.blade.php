@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.view_project'))

@push('head')
<style>
    [x-cloak] { display: none !important; }
</style>
@endpush

@section('body')
            <div class="bg-white shadow sm:rounded-lg mb-4" translate>
                <div class="px-4 py-5 sm:p-6">
                    <div class="sm:flex sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                {{ $project->name }}
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex flex-col mt-4">
                <!-- Project Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-sm text-gray-600 mb-2">{{ ctrans('texts.due_date') }}</div>
                        <div class="text-2xl font-bold text-gray-900">
                            {{ $due_date }}
                        </div>
                    </div>
                    
                    <!-- Budgeted Hours -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-sm text-gray-600 mb-2">{{ ctrans('texts.budgeted_hours') }}</div>
                        <div class="text-2xl font-bold text-gray-900">
                            {{ number_format($project->budgeted_hours ?? 0, 2) }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ ctrans('texts.hours') }}
                        </div>
                    </div>

                    <!-- Total Hours Logged -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-sm text-gray-600 mb-2">{{ ctrans('texts.total_hours') }}</div>
                        <div class="text-2xl font-bold text-gray-900">
                            {{ number_format($hours_logged, 2) }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            @if($project->budgeted_hours > 0)
                                {{ number_format(($hours_logged / $project->budgeted_hours) * 100, 1) }}% {{ ctrans('texts.of_budget') }}
                            @else
                                {{ ctrans('texts.hours') }}
                            @endif
                        </div>
                    </div>

                    <!-- Hours Invoiced / Uninvoiced -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-sm text-gray-600 mb-2">{{ ctrans('texts.invoiced_hours') }}</div>
                        <div class="text-2xl font-bold text-green-600">
                            {{ number_format($hours_invoiced, 2) }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ number_format($hours_uninvoiced, 2) }} {{ ctrans('texts.uninvoiced') }}
                        </div>
                    </div>

                    <!-- Task Rate & Active Tasks -->
                    <!-- <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-sm text-gray-600 mb-2">{{ ctrans('texts.task_rate') }}</div>
                        <div class="text-2xl font-bold text-gray-900">
                            {{ \App\Utils\Number::formatMoney($project->task_rate ?? 0, auth()->guard('contact')->user()->client) }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ $project->tasks_count }} {{ ctrans('texts.active_tasks') }}
                        </div>
                    </div> -->
                </div>

                <!-- Project Notes (if any) -->
                @if($project->public_notes)
                    <div class="bg-white rounded-lg shadow p-6 mb-6" x-data="{ notesExpanded: false }">
                        <h3 class="text-lg font-semibold mb-2">{{ ctrans('texts.notes') }}</h3>
                        <div class="text-gray-700">
                            @if(strlen($project->public_notes) > 200)
                                <span x-show="!notesExpanded">
                                    {!! nl2br(e(\Illuminate\Support\Str::limit($project->public_notes, 200, ''))) !!}
                                    <button @click="notesExpanded = true" class="text-primary hover:text-primary-dark font-semibold ml-1">
                                        {{ ctrans('texts.more') }}...
                                    </button>
                                </span>
                                <span x-show="notesExpanded" x-cloak>
                                    {!! nl2br(e($project->public_notes)) !!}
                                    <button @click="notesExpanded = false" class="text-primary hover:text-primary-dark font-semibold ml-1">
                                        {{ ctrans('texts.less') }}
                                    </button>
                                </span>
                            @else
                                {!! nl2br(e($project->public_notes)) !!}
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Tasks Table -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-xl font-semibold">{{ ctrans('texts.tasks') }}</h2>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ ctrans('texts.start_date') }}
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ ctrans('texts.description') }}
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ ctrans('texts.status') }}
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ ctrans('texts.duration') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" x-data="{ expandedTasks: {}, expandedLogs: {} }">
                                @forelse($project->tasks as $task)
                                    @php
                                        $hasTimeLogs = $show_item_description && collect($task->processLogsExpandedNotation())->filter(fn($log) => strlen($log['description'] ?? '') > 1)->count() > 0;
                                        $taskId = 'task_' . $task->id;
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center space-x-2">
                                                @if($hasTimeLogs)
                                                    <button @click="expandedTasks['{{ $taskId }}'] = !expandedTasks['{{ $taskId }}']" 
                                                            class="text-gray-500 hover:text-primary focus:outline-none"
                                                            title="{{ ctrans('texts.view_time_logs') }}">
                                                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-90': expandedTasks['{{ $taskId }}'] }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                        </svg>
                                                    </button>
                                                @endif
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $task->calculatedStartDate() }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4" x-data="{ expanded: false }">
                                            <div class="text-sm font-medium text-gray-900">
                                                @if(strlen($task->description) > 100)
                                                    <span x-show="!expanded">
                                                        {{ \Illuminate\Support\Str::limit($task->description, 100, '') }}
                                                        <button @click="expanded = true" class="text-primary hover:text-primary-dark font-semibold ml-1">
                                                            {{ ctrans('texts.more') }}...
                                                        </button>
                                                    </span>
                                                    <span x-show="expanded" x-cloak>
                                                        {{ $task->description }}
                                                        <button @click="expanded = false" class="text-primary hover:text-primary-dark font-semibold ml-1">
                                                            {{ ctrans('texts.less') }}
                                                        </button>
                                                    </span>
                                                @else
                                                    {{ $task->description }}
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center space-x-2">
                                                {!! $task->stringStatus() !!}
                                                @if($task->invoice_id)
                                                    <a href="{{ route('client.invoice.show', $task->invoice->hashed_id) }}" 
                                                       class="text-primary hover:text-primary-dark"
                                                       title="{{ ctrans('texts.view_invoice') }}">
                                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/>
                                                        </svg>
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $task->calcDurationForHumans() }}
                                        </td>
                                    </tr>
                                    
                                    <!-- Time Log Details (if enabled) -->
                                    @if($hasTimeLogs)
                                        @foreach($task->processLogsExpandedNotation() as $logIndex => $log)
                                            @if(strlen($log['description']) > 1)
                                                @php
                                                    $logId = $taskId . '_log_' . $logIndex;
                                                @endphp
                                                <tr class="bg-gray-50" x-show="expandedTasks['{{ $taskId }}']" x-cloak x-transition>
                                                    <td colspan="4" class="px-12 py-3">
                                                        <div class="flex items-start space-x-4 text-sm">
                                                            
                                                            <div class="text-gray-500 min-w-[80px]">
                                                                {{ $log['duration'] }}
                                                            </div>
                                                            <div class="text-gray-700 flex-1">
                                                                @if(strlen($log['description']) > 150)
                                                                    <span x-show="!expandedLogs['{{ $logId }}']">
                                                                        {!! nl2br(e(\Illuminate\Support\Str::limit($log['description'], 150, ''))) !!}
                                                                        <button @click="expandedLogs['{{ $logId }}'] = true" class="text-primary hover:text-primary-dark font-semibold ml-1">
                                                                            {{ ctrans('texts.more') }}...
                                                                        </button>
                                                                    </span>
                                                                    <span x-show="expandedLogs['{{ $logId }}']" x-cloak>
                                                                        {!! nl2br(e($log['description'])) !!}
                                                                        <button @click="expandedLogs['{{ $logId }}'] = false" class="text-primary hover:text-primary-dark font-semibold ml-1">
                                                                            {{ ctrans('texts.less') }}
                                                                        </button>
                                                                    </span>
                                                                @else
                                                                    {!! nl2br(e($log['description'])) !!}
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                            {{ ctrans('texts.no_results') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
@endsection
