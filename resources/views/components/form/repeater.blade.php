{{--
    Add/remove rows for a repeatable section (siblings, schools, organizations).
    `columns`: [key => ['label' => ..., 'type' => 'text'|'number'|'select', 'options' => [...]]]
    `rows`: saved rows (arrays). After a failed submit the old input is used instead.
--}}
@props(['name', 'label', 'columns', 'rows' => [], 'max' => 10, 'addLabel' => 'Add row', 'blank' => []])

@php
    $initialRows = array_values(session()->hasOldInput() ? (array) old($name, []) : $rows);
    $blankRow = array_merge(array_fill_keys(array_keys($columns), ''), $blank);
    $rowErrors = collect($errors->getMessages())
        ->filter(fn ($messages, $key) => str_starts_with($key, $name.'.'))
        ->map(fn ($messages) => $messages[0]);
@endphp

<fieldset x-data="{
        rows: @js($initialRows),
        errors: @js($rowErrors),
        blank: @js($blankRow),
        max: {{ (int) $max }},
        add() { if (this.rows.length < this.max) this.rows.push({ ...this.blank }) },
        remove(index) { this.rows.splice(index, 1); this.errors = {} },
        error(index, key) { return this.errors['{{ $name }}.' + index + '.' + key] ?? null },
    }" {{ $attributes }}>
    <legend class="block font-medium text-sm text-gray-700 dark:text-gray-300">{{ $label }}</legend>

    <p x-show="rows.length === 0" class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No rows yet.') }}</p>

    <div class="mt-2 space-y-3">
        <template x-for="(row, index) in rows" :key="index">
            <div class="rounded-md border border-gray-200 dark:border-gray-700 p-3">
                <div class="grid gap-3 sm:grid-cols-{{ min(count($columns), 3) }}">
                    @foreach ($columns as $key => $column)
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400" :for="'{{ $name }}_' + index + '_{{ $key }}'">{{ $column['label'] }}</label>
                            @if (($column['type'] ?? 'text') === 'select')
                                <select x-model="row.{{ $key }}" :id="'{{ $name }}_' + index + '_{{ $key }}'" :name="'{{ $name }}[' + index + '][{{ $key }}]'"
                                    class="mt-1 block w-full rounded-md glass-input text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400">
                                    <option value="">{{ __('Select…') }}</option>
                                    @foreach ($column['options'] as $optionValue => $optionLabel)
                                        <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="{{ $column['type'] ?? 'text' }}" x-model="row.{{ $key }}" :id="'{{ $name }}_' + index + '_{{ $key }}'" :name="'{{ $name }}[' + index + '][{{ $key }}]'"
                                    class="mt-1 block w-full rounded-md glass-input text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400"
                                    @if (($column['type'] ?? 'text') === 'number') min="0" max="120" @else maxlength="255" @endif
                                    @foreach ($column['attributes'] ?? [] as $attribute => $attributeValue) {{ $attribute }}="{{ $attributeValue }}" @endforeach
                                    :aria-invalid="error(index, '{{ $key }}') ? 'true' : null">
                            @endif
                            <p x-show="error(index, '{{ $key }}')" x-text="error(index, '{{ $key }}')" class="mt-1 text-xs text-red-600 dark:text-red-400"></p>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 text-right">
                    <button type="button" @click="remove(index)" class="text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300">{{ __('Remove') }}</button>
                </div>
            </div>
        </template>
    </div>

    <button type="button" @click="add()" x-show="rows.length < max"
        class="mt-3 inline-flex items-center rounded-md border border-dashed border-gray-300 dark:border-gray-600 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:border-indigo-400 dark:hover:border-indigo-500 hover:text-indigo-700 dark:hover:text-indigo-300">
        + {{ $addLabel }}
    </button>
    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</fieldset>
