@php use App\Support\Form5; @endphp

<p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Family background & socio-economic data') }}</p>

<div class="grid gap-6 lg:grid-cols-2">
    @foreach (Form5::PARENT_RELATIONS as $relation => $relationLabel)
        @php
            $parent = $record->parent($relation);
        @endphp
        <fieldset class="space-y-4 rounded-md border border-gray-200 dark:border-gray-700 p-4">
            <legend class="px-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $relationLabel }}</legend>
            <x-form.text :name="'parents.'.$relation.'.name'" label="Name" kind="name" maxlength="255" :value="$parent?->name" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.text :name="'parents.'.$relation.'.age'" label="Age" type="number" min="10" max="120" :value="$parent?->age" />
                <x-form.text :name="'parents.'.$relation.'.religion'" label="Religion" :value="$parent?->religion" />
            </div>
            <x-form.text :name="'parents.'.$relation.'.occupation'" label="Occupation" :value="$parent?->occupation" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.text :name="'parents.'.$relation.'.monthly_income'" label="Monthly income" :value="$parent?->monthly_income" />
                <x-form.text :name="'parents.'.$relation.'.educational_attainment'" label="Educational attainment" :value="$parent?->educational_attainment" />
            </div>
        </fieldset>
    @endforeach
</div>

<x-form.checkboxes name="parents_status" label="Parents' status" :options="Form5::PARENTS_STATUSES" :value="$record->parents_status" :columns="3"
    hint="Check all that apply." />

<x-form.textarea name="family_view" label="How do you see your family at present?" :value="$record->family_view" rows="4" />

<x-form.textarea name="family_concern" label="Any specific concern you are going through right now with regards to your family?" :value="$record->family_concern" rows="4" />

<x-form.repeater name="siblings" label="Siblings (including yourself)" add-label="Add sibling" :max="Form5::MAX_SIBLINGS"
    :rows="$record->siblings->map->only(['name', 'sex', 'age', 'year_course_school', 'occupation'])->all()"
    :columns="[
        'name' => ['label' => 'Name', 'attributes' => \App\Support\Validation::htmlAttributes('name') + ['maxlength' => 255]],
        'sex' => ['label' => 'Sex', 'type' => 'select', 'options' => Form5::SEXES],
        'age' => ['label' => 'Age', 'type' => 'number'],
        'year_course_school' => ['label' => 'Yr./Course/School'],
        'occupation' => ['label' => 'Occupation/Office'],
    ]" />

<x-form.text name="home_language" label="Dialect/language spoken at home" :value="$record->home_language" />
