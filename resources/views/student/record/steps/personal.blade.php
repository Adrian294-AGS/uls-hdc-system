@php
    use App\Support\Form5;
    use App\Support\FormName;

    $selectedProgram = (string) old('program_id', $record->program_id);
@endphp

<div x-data="{ civilStatus: @js(FormName::current('civil_status', $record->civil_status)) }" class="space-y-6">
    <div class="grid gap-6 sm:grid-cols-3">
        <x-form.text name="last_name" label="Last name" kind="name" :value="$record->last_name" required autocomplete="family-name" />
        <x-form.text name="first_name" label="First name" kind="name" :value="$record->first_name" required autocomplete="given-name" />
        <x-form.text name="middle_name" label="Middle name" kind="name" :value="$record->middle_name" autocomplete="additional-name" />
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <x-form.select name="program_id" label="Course preference" placeholder="Select your course" required>
            @foreach ($colleges as $college)
                <optgroup label="{{ $college->code }} – {{ $college->name }}">
                    @foreach ($college->programs as $program)
                        <option value="{{ $program->id }}" @selected($selectedProgram === (string) $program->id)>{{ $program->code }} – {{ $program->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </x-form.select>
        <x-form.select name="year_level" label="Year level" :options="Form5::YEAR_LEVELS" :value="$record->year_level" required />
    </div>

    <x-form.text name="major" label="Major" :value="$record->major" maxlength="255" />

    <x-form.text name="home_address" label="Home address" :value="$record->home_address" required maxlength="500"
        hint="House no., street, barangay, municipality, province" autocomplete="street-address" />

    <div class="grid gap-6 sm:grid-cols-2">
        <x-form.text name="birth_date" label="Birth date" type="date" :value="$record->birth_date" required min="1900-01-02" :max="\App\Support\Validation::today()" />
        <x-form.text name="place_of_birth" label="Place of birth" :value="$record->place_of_birth" required maxlength="255" />
    </div>
    @if ($record->age() !== null)
        <p class="-mt-4 text-sm text-gray-600 dark:text-gray-400">{{ __('Age: :age', ['age' => $record->age()]) }}</p>
    @endif

    <div class="grid gap-6 sm:grid-cols-2">
        <x-form.radios name="sex" label="Sex" :options="Form5::SEXES" :value="$record->sex" required />
        <x-form.radios name="civil_status" label="Civil status" :options="Form5::CIVIL_STATUSES" :value="$record->civil_status" required model="civilStatus" />
    </div>

    <div x-show="civilStatus === 'married'" x-cloak class="grid gap-6 sm:grid-cols-2 rounded-md bg-gray-50 dark:bg-gray-900/40 p-4">
        <x-form.text name="spouse_name" label="Spouse name" kind="name" maxlength="255" :value="$record->spouse_name" required />
        <x-form.text name="number_of_children" label="No. of children" type="number" min="0" max="30" :value="$record->number_of_children" />
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <x-form.text name="contact_number" label="Mobile no." kind="phone" :value="$record->contact_number" required autocomplete="tel" hint="09XXXXXXXXX or +639XXXXXXXXX" />
        <x-form.text name="email" label="E-mail address" kind="email" :value="$record->email" required autocomplete="email" />
    </div>

    <div class="grid gap-6 sm:grid-cols-3">
        <x-form.text name="nationality" label="Nationality" :value="$record->nationality" required maxlength="100" />
        <x-form.text name="religion" label="Religion" :value="$record->religion" maxlength="100" />
        <x-form.text name="ethnicity" label="Ethnicity" :value="$record->ethnicity" maxlength="100" />
    </div>

    <x-form.text name="boarding_address" label="Boarding house address" :value="$record->boarding_address" maxlength="500" />

    <fieldset class="space-y-4 rounded-md border border-gray-200 dark:border-gray-700 p-4">
        <legend class="px-1 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ __('In case of emergency') }}</legend>
        <div class="grid gap-6 sm:grid-cols-2">
            <x-form.text name="emergency_contact_name" label="Emergency contact name" kind="name" maxlength="255" :value="$record->emergency_contact_name" required />
            <x-form.text name="emergency_contact_number" label="Emergency contact mobile no." kind="phone" :value="$record->emergency_contact_number" required />
        </div>
        <x-form.text name="emergency_contact_address" label="Emergency contact address" :value="$record->emergency_contact_address" required maxlength="500" />
    </fieldset>
</div>
