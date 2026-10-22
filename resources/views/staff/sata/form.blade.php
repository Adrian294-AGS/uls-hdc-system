@php
    use App\Models\EntranceTestResult;

    $editing = $result->exists;
    $subtests = EntranceTestResult::subtests();
    $descriptors = config('sata.descriptors');
    $fieldValue = fn (string $field) => old($field, $result->{$field} instanceof \DateTimeInterface ? $result->{$field}->format('Y-m-d') : $result->{$field});

    $initial = ['quotient' => (string) $fieldValue('quotient'), 'quotient_descriptor' => (string) $fieldValue('quotient_descriptor')];
    foreach (array_keys($subtests) as $key) {
        $initial["{$key}_score"] = (string) $fieldValue("{$key}_score");
        $initial["{$key}_descriptor"] = (string) $fieldValue("{$key}_descriptor");
    }

    $inputBase = 'block w-full rounded-md glass-input text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400';
    $inputNormal = $inputBase.' border-gray-300 dark:border-gray-600';
    $inputError = $inputBase.' border-red-500 dark:border-red-500';
    // Red border (both themes) when the field has an error.
    $fieldClasses = fn (string $field, string $extra = '') => trim(($errors->has($field) ? $inputError : $inputNormal).' '.$extra);
    $subtestLimits = config('sata.limits.subtest');
    $quotientLimits = config('sata.limits.quotient');
@endphp

<x-app-layout>
    <x-slot name="header">
        <a href="{{ $hasHistory ? route('staff.sata.index', $student) : route('staff.records.index', $listFilters) }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">
            &larr; {{ $hasHistory ? __('SATA history') : __('Back to records') }}
        </a>
        <h2 class="mt-1 font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $editing ? __('Edit SATA result') : __('Encode SATA result') }}: {{ $student->name }}
        </h2>
        @include('staff.sata.partials.student-header')
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            @if ($errors->any())
                <div class="rounded-md bg-red-50 dark:bg-red-500/10 px-4 py-3 text-sm text-red-800 dark:text-red-300" role="alert">
                    {{ __('Please fix the highlighted fields.') }}
                </div>
            @endif

            <form method="POST" action="{{ $editing ? route('staff.sata.update', $result) : route('staff.sata.store', $student) }}"
                @if ($editing) data-confirm="{{ __('Save the changes to this SATA result?') }}" data-confirm-button="{{ __('Save changes') }}" @endif
                class="glass-panel sm:rounded-lg p-4 sm:p-6"
                x-data="sataForm(@js($initial), @js(config('sata.ranges')))">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Scholastic Abilities Test for Adults (SATA)') }}</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Type each standard score; the descriptor is suggested automatically and can still be changed.') }}
                </p>

                {{-- Paper-form layout: subtests as columns, score and descriptor as rows --}}
                <div class="mt-4 overflow-x-auto rounded-md border border-gray-200 dark:border-gray-700">
                    <table class="min-w-[56rem] w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th scope="col" class="w-36 px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-300"><span class="sr-only">{{ __('Row') }}</span></th>
                                @foreach ($subtests as $subtest)
                                    <th scope="col" class="px-2 py-2 text-center font-semibold text-gray-700 dark:text-gray-300" title="{{ $subtest['name'] }}">{{ $subtest['code'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr>
                                <th scope="row" class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ __('Standard Score') }}</th>
                                @foreach ($subtests as $key => $subtest)
                                    <td class="px-2 py-2 align-top">
                                        <label for="{{ $key }}_score" class="sr-only">{{ __(':code standard score', ['code' => $subtest['code']]) }}</label>
                                        <input id="{{ $key }}_score" name="{{ $key }}_score" type="number" inputmode="numeric"
                                            min="{{ $subtestLimits['min'] }}" max="{{ $subtestLimits['max'] }}" required
                                            x-model="values.{{ $key }}_score" x-on:input="suggest('{{ $key }}', 'subtest')"
                                            class="{{ $fieldClasses($key.'_score', 'text-center tabular-nums') }}"
                                            @error($key.'_score') aria-invalid="true" @enderror>
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <th scope="row" class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">{{ __('Descriptor') }}</th>
                                @foreach ($subtests as $key => $subtest)
                                    <td class="px-2 py-2 align-top">
                                        <label for="{{ $key }}_descriptor" class="sr-only">{{ __(':code descriptor', ['code' => $subtest['code']]) }}</label>
                                        <select id="{{ $key }}_descriptor" name="{{ $key }}_descriptor" required x-model="values.{{ $key }}_descriptor"
                                            class="{{ $fieldClasses($key.'_descriptor', 'px-1 text-xs') }}"
                                            @error($key.'_descriptor') aria-invalid="true" @enderror>
                                            <option value="">—</option>
                                            @foreach ($descriptors as $descriptor)
                                                <option value="{{ $descriptor }}">{{ $descriptor }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                </div>

                @php
                    $subtestErrors = collect(array_keys($subtests))
                        ->flatMap(fn ($key) => [...$errors->get($key.'_score'), ...$errors->get($key.'_descriptor')]);
                @endphp
                @if ($subtestErrors->isNotEmpty())
                    <ul class="mt-2 space-y-1 text-sm text-red-600 dark:text-red-400">
                        @foreach ($subtestErrors as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                @endif

                @include('staff.sata.partials.legend')

                <div class="mt-6 grid gap-6 sm:grid-cols-3">
                    <div>
                        <x-input-label for="quotient">{{ __('Quotient') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></x-input-label>
                        <input id="quotient" name="quotient" type="number" inputmode="numeric" min="{{ $quotientLimits['min'] }}" max="{{ $quotientLimits['max'] }}" required
                            x-model="values.quotient" x-on:input="suggest(null, 'quotient')"
                            class="{{ $fieldClasses('quotient', 'mt-1 tabular-nums') }}">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __(':min to :max', ['min' => $quotientLimits['min'], 'max' => $quotientLimits['max']]) }}</p>
                        <x-input-error :messages="$errors->get('quotient')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="quotient_descriptor">{{ __('Quotient descriptor') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></x-input-label>
                        <select id="quotient_descriptor" name="quotient_descriptor" required x-model="values.quotient_descriptor" class="{{ $fieldClasses('quotient_descriptor', 'mt-1') }}">
                            <option value="">—</option>
                            @foreach ($descriptors as $descriptor)
                                <option value="{{ $descriptor }}">{{ $descriptor }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('quotient_descriptor')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="exam_date">{{ __('Date of examination') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></x-input-label>
                        <input id="exam_date" name="exam_date" type="date" min="2000-01-01" max="{{ \App\Support\Validation::today() }}" required
                            value="{{ $fieldValue('exam_date') }}" class="{{ $fieldClasses('exam_date', 'mt-1') }}">
                        <x-input-error :messages="$errors->get('exam_date')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6">
                    <x-form.textarea name="remarks" label="Remarks (optional)" :value="$result->remarks" rows="2" />
                </div>

                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">{{ __('Subtest standard scores: :min to :max.', ['min' => $subtestLimits['min'], 'max' => $subtestLimits['max']]) }}</p>

                @foreach ($listFilters as $filterName => $filterValue)
                    <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
                @endforeach

                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 dark:border-gray-700 pt-6">
                    <a href="{{ $hasHistory ? route('staff.sata.index', $student) : route('staff.records.index', $listFilters) }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">{{ __('Cancel') }}</a>
                    <div class="flex flex-wrap items-center gap-3">
                        @unless ($editing)
                            <x-secondary-button type="submit" name="next" value="1">{{ __('Save and encode next student') }}</x-secondary-button>
                        @endunless
                        <x-primary-button>{{ $editing ? __('Save changes') : __('Save result') }}</x-primary-button>
                    </div>
                </div>

                @csrf
                @if ($editing)
                    @method('PUT')
                @endif
            </form>
        </div>
    </div>

    <script>
        function sataForm(values, ranges) {
            return {
                values,
                ranges,
                // Suggest a descriptor from the configured ranges as soon as a score is typed.
                suggest(key, kind) {
                    const scoreField = kind === 'quotient' ? 'quotient' : key + '_score';
                    const descriptorField = kind === 'quotient' ? 'quotient_descriptor' : key + '_descriptor';
                    const score = parseInt(this.values[scoreField], 10);
                    if (Number.isNaN(score)) {
                        return;
                    }
                    const match = this.ranges[kind].find((range) =>
                        (range.min === null || score >= range.min) && (range.max === null || score <= range.max));
                    if (match) {
                        this.values[descriptorField] = match.descriptor;
                    }
                },
            };
        }
    </script>
</x-app-layout>
