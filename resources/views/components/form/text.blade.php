@props(['name', 'label', 'value' => null, 'type' => 'text', 'required' => false, 'hint' => null, 'kind' => null])

@php
    $id = str_replace('.', '_', $name);
    $htmlName = \App\Support\FormName::html($name);
@endphp

<div {{ $attributes->only(['class', 'x-show', 'x-cloak']) }}>
    <x-input-label :for="$id">
        {{ $label }} @if ($required)<span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span>@endif
    </x-input-label>
    <x-text-input :id="$id" :name="$htmlName" :type="$type" :kind="$kind" class="mt-1 block w-full" :required="$required" :invalid="$errors->has($name)"
        :value="old($name, $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value)"
        {{ $attributes->except(['class', 'x-show', 'x-cloak']) }} />
    @if ($hint)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    @endif
    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</div>
