{{-- Paginated table of students with their latest Form 5 status. Expects $students (paginator). --}}
@php
    use App\Http\Controllers\Staff\EntranceTestResultController;
    use App\Support\Form5;

    $listFilters = array_filter(request()->only(EntranceTestResultController::LIST_FILTERS), 'filled');
@endphp

<div class="glass-panel sm:rounded-lg overflow-hidden">
    @if ($students->isEmpty())
        <p class="p-6 text-sm text-gray-600 dark:text-gray-400">{{ $emptyMessage ?? __('No students match these filters.') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/40">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Student') }}</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Student no.') }}</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Program') }}</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('College') }}</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Year') }}</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Submitted') }}</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('SATA') }}</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Intake') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($students as $student)
                        @php
                            $record = $student->latestCumulativeRecord;
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/60">
                            <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                @if ($record)
                                    <a href="{{ route('staff.records.show', $record) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                                        {{ $student->last_name }}, {{ $student->first_name }} {{ $student->middle_name }}
                                    </a>
                                @else
                                    <span class="font-medium">{{ $student->last_name }}, {{ $student->first_name }} {{ $student->middle_name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $student->student_number }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $student->program?->code }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $student->program?->college?->code }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ Form5::YEAR_LEVELS[$student->year_level] ?? '—' }}</td>
                            <td class="px-4 py-3"><x-record-status :status="$record?->status" /></td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $record?->submitted_at?->format('M j, Y') ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($student->has_sata)
                                    <a href="{{ route('staff.sata.index', $student) }}" class="inline-flex rounded-full bg-green-50 dark:bg-green-500/10 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:text-green-400 hover:underline">{{ __('Encoded') }}</a>
                                @else
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Not encoded') }}</span>
                                    <a href="{{ route('staff.sata.create', [$student, ...$listFilters]) }}" class="ms-2 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Encode SATA result') }}</a>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @if ($record && $student->latestIntakeInterview)
                                        <a href="{{ route('staff.records.show', $record) }}#intake" class="hover:opacity-80"><x-intake-status :status="$student->latestIntakeInterview->status" /></a>
                                    @else
                                        <x-intake-status :status="$student->latestIntakeInterview?->status" />
                                    @endif
                                    @if ($student->needs_follow_up)
                                        <x-follow-up-badge />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@if ($students->hasPages())
    <div class="mt-4 glass-panel sm:rounded-lg px-4 py-3">{{ $students->links() }}</div>
@endif
