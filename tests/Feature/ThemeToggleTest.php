<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ThemeToggleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function roles(): array
    {
        return [
            'student' => ['student'],
            'counselor' => ['counselor'],
            'director' => ['director'],
        ];
    }

    #[DataProvider('roles')]
    public function test_theme_toggle_renders_in_the_nav_for_every_role(string $role): void
    {
        $user = User::factory()->{$role}()->create();

        $html = $this->actingAs($user)->get(route($user->dashboardRoute()))->assertOk()->getContent();

        // Desktop nav + mobile menu
        $this->assertSame(2, substr_count($html, 'onclick="toggleTheme()"'));
        $this->assertThemeSetup($html);
    }

    public function test_theme_toggle_and_background_are_on_guest_pages(): void
    {
        foreach (['/', route('login'), route('register')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'onclick="toggleTheme()"'));
            $this->assertThemeSetup($html);
        }
    }

    public function test_login_page_renders_the_theme_toggle(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('aria-label="Toggle light/dark mode"', false)
            ->assertSee('class="icon-moon"', false)
            ->assertSee('class="icon-sun"', false);
    }

    private function assertThemeSetup(string $html): void
    {
        // The saved theme is applied in <head>, before anything renders.
        $this->assertStringStartsWith('<script>', ltrim(explode('<head>', $html, 2)[1]));
        $this->assertLessThan(strpos($html, '</head>'), strpos($html, "localStorage.getItem('theme')"));
        $this->assertStringContainsString(asset('images/bg-day.png'), $html);
        $this->assertStringContainsString(asset('images/bg-night.png'), $html);

        // Background layers come first in <body>, exactly once.
        $this->assertStringStartsWith('<div class="beach-bg beach-day"', ltrim(explode('>', explode('<body', $html, 2)[1], 2)[1]));
        $this->assertSame(1, substr_count($html, 'class="beach-bg beach-day"'));
        $this->assertSame(1, substr_count($html, 'class="beach-bg beach-night"'));
        $this->assertSame(1, substr_count($html, 'class="beach-tint"'));
        $this->assertStringNotContainsString('bg-gray-100 dark:bg-gray-900', $html);
    }
}
