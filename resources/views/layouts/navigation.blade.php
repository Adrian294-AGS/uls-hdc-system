@php
    $user = Auth::user();
    $isStaff = $user->isCounselor() || $user->isDirector();
    $unreadCount = $isStaff ? $user->unreadNotifications()->count() : 0;
@endphp

<nav x-data="{ open: false }" class="glass-bar sticky top-0 z-40">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route($user->dashboardRoute()) }}" class="flex items-center gap-2">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
                        <span class="font-semibold text-gray-800 dark:text-gray-100">ULS HDC</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-5 xl:-my-px xl:ms-6 xl:flex">
                    <x-nav-link :href="route($user->dashboardRoute())" :active="request()->routeIs($user->dashboardRoute())">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    @if ($user->isStudent())
                        <x-nav-link :href="route('student.record.index')" :active="request()->routeIs('student.record.*')">
                            {{ __('My Record') }}
                        </x-nav-link>
                    @endif
                    @if ($isStaff)
                        <x-nav-link :href="route('staff.records.index')" :active="request()->routeIs('staff.records.*')">
                            {{ __('Records') }}
                        </x-nav-link>
                        <x-nav-link :href="route('staff.tracker')" :active="request()->routeIs('staff.tracker')">
                            {{ __('Tracker') }}
                        </x-nav-link>
                        <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                            {{ __('Reports') }}
                        </x-nav-link>
                        <x-nav-link :href="route('staff.imports.index')" :active="request()->routeIs('staff.imports.*')">
                            {{ __('Import') }}
                        </x-nav-link>
                    @endif
                    @if ($user->isDirector())
                        <x-nav-link :href="route('director.counselors.index')" :active="request()->routeIs('director.counselors.*')">
                            {{ __('Counselors') }}
                        </x-nav-link>
                        <x-nav-link :href="route('director.colleges.index')" :active="request()->routeIs('director.colleges.*', 'director.programs.*')" title="{{ __('Colleges & Programs') }}">
                            {{ __('Colleges') }}
                        </x-nav-link>
                        <x-nav-link :href="route('director.backups.index')" :active="request()->routeIs('director.backups.*')">
                            {{ __('Backups') }}
                        </x-nav-link>
                    @endif
                    <x-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">
                        {{ __('Profile') }}
                    </x-nav-link>
                </div>
            </div>

            <!-- User + Theme + Logout -->
            <div class="hidden xl:flex xl:items-center xl:gap-3 xl:ms-6">
                @if ($isStaff)
                    <x-notification-bell :count="$unreadCount" />
                @endif

                <div class="hidden 2xl:block text-right leading-tight">
                    <div class="text-sm font-medium text-gray-800 dark:text-gray-100 whitespace-nowrap">{{ $user->name }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $user->role->label() }}</div>
                </div>

                <x-theme-toggle />

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-secondary-button type="submit" class="whitespace-nowrap">{{ __('Log Out') }}</x-secondary-button>
                </form>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center gap-2 xl:hidden">
                @if ($isStaff)
                    <x-notification-bell :count="$unreadCount" />
                @endif
                <button @click="open = ! open" :aria-expanded="open" aria-label="{{ __('Menu') }}" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-white/60 focus:outline-none focus:bg-white/60 focus:text-gray-500 transition duration-150 ease-in-out dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-gray-300 dark:focus:bg-white/10">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden xl:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route($user->dashboardRoute())" :active="request()->routeIs($user->dashboardRoute())">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            @if ($user->isStudent())
                <x-responsive-nav-link :href="route('student.record.index')" :active="request()->routeIs('student.record.*')">
                    {{ __('My Record') }}
                </x-responsive-nav-link>
            @endif
            @if ($isStaff)
                <x-responsive-nav-link :href="route('staff.records.index')" :active="request()->routeIs('staff.records.*')">
                    {{ __('Records') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('staff.tracker')" :active="request()->routeIs('staff.tracker')">
                    {{ __('Tracker') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                    {{ __('Reports') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('staff.imports.index')" :active="request()->routeIs('staff.imports.*')">
                    {{ __('Import') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('staff.notifications.index')" :active="request()->routeIs('staff.notifications.*')">
                    {{ __('Notifications') }} @if ($unreadCount) ({{ $unreadCount }}) @endif
                </x-responsive-nav-link>
            @endif
            @if ($user->isDirector())
                <x-responsive-nav-link :href="route('director.counselors.index')" :active="request()->routeIs('director.counselors.*')">
                    {{ __('Counselors') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('director.colleges.index')" :active="request()->routeIs('director.colleges.*', 'director.programs.*')">
                    {{ __('Colleges & Programs') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('director.backups.index')" :active="request()->routeIs('director.backups.*')">
                    {{ __('Backups') }}
                </x-responsive-nav-link>
            @endif
            <x-responsive-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.edit')">
                {{ __('Profile') }}
            </x-responsive-nav-link>
        </div>

        <div class="pt-4 pb-3 border-t border-gray-200/70 dark:border-white/10">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-100">{{ $user->name }}</div>
                <div class="font-medium text-sm text-gray-500 dark:text-gray-400">{{ $user->email }} · {{ $user->role->label() }}</div>
            </div>

            <div class="mt-3 flex items-center gap-3 px-4">
                <x-theme-toggle />
                <form method="POST" action="{{ route('logout') }}" class="flex-1">
                    @csrf
                    <x-secondary-button type="submit" class="w-full justify-center">{{ __('Log Out') }}</x-secondary-button>
                </form>
            </div>
        </div>
    </div>
</nav>
