<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('Import from Excel or CSV') }}</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ Auth::user()->isDirector() ? __('You can import for all colleges.') : __('You can import only for students in your assigned colleges.') }}
        </p>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <section class="glass-panel sm:rounded-lg p-4 sm:p-6">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('1. Download a template') }}</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('The Excel template has an Instructions sheet and dropdowns for codes and descriptors. Row 2 is an example and is never imported.') }}</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ($types as $key => $type)
                        <div class="rounded-md border border-gray-200 dark:border-gray-700 p-4">
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ __($type->label()) }}</p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ implode(', ', array_column($type->columns(), 'label')) }}</p>
                            <div class="mt-3 flex flex-wrap gap-2 text-sm">
                                <a href="{{ route('staff.imports.template', [$key, 'xlsx']) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Excel template') }}</a>
                                <span class="text-gray-400" aria-hidden="true">·</span>
                                <a href="{{ route('staff.imports.template', [$key, 'csv']) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('CSV template') }}</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <form method="POST" action="{{ route('staff.imports.preview') }}" enctype="multipart/form-data" class="glass-panel sm:rounded-lg p-4 sm:p-6 space-y-4">
                @csrf
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('2. Upload the filled-in file') }}</h3>
                <x-form.radios name="type" label="What are you importing?" :options="$types->map->label()->all()" :value="old('type', 'roster')" required />
                <div>
                    <x-input-label for="file">{{ __('File') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></x-input-label>
                    <input id="file" name="file" type="file" required accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
                        @error('file') aria-invalid="true" @enderror
                        class="mt-1 block w-full text-sm text-gray-700 dark:text-gray-300 file:me-3 file:rounded-md file:border-0 file:bg-gray-800 file:px-3 file:py-2 file:text-xs file:font-semibold file:uppercase file:tracking-widest file:text-white hover:file:bg-gray-700 dark:file:bg-gray-100 dark:file:text-gray-900">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('.xlsx or .csv, up to 5 MB and :max rows.', ['max' => \App\Support\Imports\ImportType::MAX_ROWS]) }}</p>
                    <x-input-error :messages="$errors->get('file')" class="mt-2" />
                </div>
                <div class="flex justify-end border-t border-gray-100 dark:border-gray-700 pt-4">
                    <x-primary-button>{{ __('Upload and preview') }}</x-primary-button>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Nothing is saved yet: you will see every row and every problem first.') }}</p>
            </form>
        </div>
    </div>
</x-app-layout>
