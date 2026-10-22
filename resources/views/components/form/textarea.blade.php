@props(['name', 'label', 'value' => null, 'required' => false, 'rows' => 3])

@php($id = str_replace('.', '_', $name))

<div {{ $attributes->only(['class', 'x-show', 'x-cloak']) }}>
    <x-input-label :for="$id">
        {{ $label }} @if ($required)<span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>@endif
    </x-input-label>
    <textarea id="{{ $id }}" name="{{ \App\Support\FormName::html($name) }}" rows="{{ $rows }}"
        class="mt-1 block w-full glass-input focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:focus:border-indigo-400 dark:focus:ring-indigo-400 aria-[invalid=true]:border-red-500"
        @if ($required) required @endif @error($name) aria-invalid="true" @enderror
        {{ $attributes->except(['class', 'x-show', 'x-cloak']) }}>{{ old($name, $value) }}</textarea>
    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</div>
