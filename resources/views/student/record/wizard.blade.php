@php
    use App\Support\Form5;

    $steps = Form5::STEPS;
    $stepKeys = array_keys($steps);
    $stepNumber = array_search($step, $stepKeys, true) + 1;
    $previous = Form5::previousStep($step);
    $exists = $record->exists;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('HDC Form 5: Student Cumulative Record') }}
            </h2>
            <x-record-status :status="$record->status" />
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 grid gap-6 md:grid-cols-[14rem_1fr]">
            {{-- Step list (desktop) --}}
            <nav aria-label="Form sections" class="hidden md:block glass-panel rounded-lg p-2 self-start">
                <ol class="space-y-1">
                    @foreach ($steps as $key => $title)
                        @php
                            $isCurrent = $key === $step;
                            $isDone = $record->hasCompletedStep($key) || ($key === 'review' && $record->submitted_at);
                        @endphp
                        <li>
                            @if ($exists)
                                <a href="{{ route('student.record.edit', [$record, $key]) }}"
                                    @class([
                                        'flex items-center gap-2 rounded-md px-3 py-2 text-sm',
                                        'bg-indigo-50 dark:bg-indigo-500/10 font-semibold text-indigo-700 dark:text-indigo-300' => $isCurrent,
                                        'text-gray-700 dark:text-gray-300 hover:bg-white/60 dark:hover:bg-white/10' => ! $isCurrent,
                                    ])
                                    @if ($isCurrent) aria-current="step" @endif>
                                    <x-step-marker :number="$loop->iteration" :done="$isDone" :current="$isCurrent" />
                                    {{ $title }}
                                </a>
                            @else
                                <span @class([
                                    'flex items-center gap-2 rounded-md px-3 py-2 text-sm',
                                    'bg-indigo-50 dark:bg-indigo-500/10 font-semibold text-indigo-700 dark:text-indigo-300' => $isCurrent,
                                    'text-gray-500 dark:text-gray-400' => ! $isCurrent,
                                ])>
                                    <x-step-marker :number="$loop->iteration" :done="false" :current="$isCurrent" />
                                    {{ $title }}
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>

            <div class="min-w-0 space-y-4">
                {{-- Progress --}}
                <div class="glass-panel rounded-lg px-4 py-3">
                    <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                        <span>{{ __('Step :n of :total', ['n' => $stepNumber, 'total' => count($steps)]) }}</span>
                        <span>{{ $record->progressPercent() }}% {{ __('complete') }}</span>
                    </div>
                    <x-progress-bar :percent="$record->progressPercent()" class="mt-2" />
                </div>


                @if ($record->status === \App\Enums\RecordStatus::Submitted)
                    <div class="rounded-md bg-blue-50 dark:bg-blue-500/10 px-4 py-3 text-sm text-blue-800 dark:text-blue-300">
                        {{ __('This record is already submitted. You can still update it until the HDC office reviews it.') }}
                    </div>
                @endif

                <div class="glass-panel sm:rounded-lg p-4 sm:p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $steps[$step] }}</h3>

                    @if ($step === 'entry')
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ Form5::FORM_INTRO }}</p>
                    @endif

                    @if ($step === 'review')
                        @include('student.record.steps.review')
                    @else
                        <form method="POST" enctype="multipart/form-data" class="mt-6"
                            action="{{ $exists ? route('student.record.update', [$record, $step]) : route('student.record.store') }}">
                            @csrf
                            @if ($exists)
                                @method('PUT')
                            @endif

                            <div class="space-y-6">
                                @include('student.record.steps.'.$step)
                            </div>

                            <div class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 dark:border-gray-700 pt-6">
                                @if ($previous && $exists)
                                    <a href="{{ route('student.record.edit', [$record, $previous]) }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">
                                        &larr; {{ __('Back') }}
                                    </a>
                                @else
                                    <a href="{{ route('student.dashboard') }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">
                                        {{ __('Back to dashboard') }}
                                    </a>
                                @endif

                                <x-primary-button>{{ __('Save and continue') }} &rarr;</x-primary-button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
