{{-- One aggregate table. Cells are display strings (already suppressed); counts are never links. --}}
@php($compact = $compact ?? false)

<div @class(['mt-4' => $compact])>
    @if ($compact)
        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $table->title }}</h4>
    @else
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __($table->title) }}</h3>
    @endif
    <div class="mt-3 overflow-x-auto rounded-md border border-gray-200 dark:border-gray-700">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/40">
                <tr>
                    @foreach ($table->headers as $index => $header)
                        <th scope="col" @class([
                            'px-3 py-2 font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap',
                            'text-left' => $index === 0,
                            'text-right' => $index > 0,
                        ])>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($table->rows as $row)
                    <tr>
                        @foreach ($row as $index => $cell)
                            @if ($index === 0)
                                <th scope="row" class="px-3 py-1.5 text-left font-normal text-gray-800 dark:text-gray-200">{{ $cell }}</th>
                            @else
                                <td @class([
                                    'px-3 py-1.5 text-right tabular-nums',
                                    'text-gray-400 dark:text-gray-500' => $cell === '0',
                                    'text-gray-900 dark:text-gray-100' => $cell !== '0',
                                ])>{{ $cell }}</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($table->note)
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __($table->note) }}</p>
    @endif
</div>
