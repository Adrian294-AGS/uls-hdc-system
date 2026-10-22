<x-error-page code="503" :title="__('We\'ll be right back')"
    :message="__('The HDC system is being updated or backed up. This usually takes only a few minutes. Your saved work is safe.')"
    :primary-label="__('Try again')" :primary-url="url()->current()" />
