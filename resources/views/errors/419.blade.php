<x-error-page code="419" :title="__('Your session has expired')"
    :message="__('For your security, you were signed out after a period of inactivity, or the page was open for too long. Please log in again and repeat your last step.')"
    :primary-label="__('Log in again')" :primary-url="route('login')" />
