@props(['name', 'label', 'options' => [], 'value' => null, 'required' => false, 'placeholder' => 'Select…'])

@php
    $id = str_replace('.', '_', $name);
    $selected = (string) old($name, $value);
@endphp

<div {{ $attributes->only(['class', 'x-show', 'x-cloak']) }}>
    <x-input-label :for="$id">
        {{ $label }} @if ($required)<span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>@endif
    </x-input-label>
    <select id="{{ $id }}" name="{{ \App\Support\FormName::html($name) }}"
        class="mt-1 block w-full glass-input focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:focus:border-indigo-400 dark:focus:ring-indigo-400 aria-[invalid=true]:border-red-500 disabled:cursor-default disabled:opacity-80"
        @if ($required) required @endif @error($name) aria-invalid="true" @enderror
        {{ $attributes->except(['class', 'x-show', 'x-cloak']) }}>
        <option value="">{{ $placeholder }}</option>
        @if ($slot->isNotEmpty())
            {{ $slot }}
        @else
            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
            @endforeach
        @endif
    </select>
    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</div>
