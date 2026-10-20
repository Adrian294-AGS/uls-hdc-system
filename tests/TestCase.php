<?php

namespace Tests;

use Closure;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Response for the breached-password API (Have I Been Pwned). Null = "not breached".
     */
    protected ?Closure $pwnedPasswords = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests never call the internet.
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'api.pwnedpasswords.com')) {
                return $this->pwnedPasswords ? ($this->pwnedPasswords)($request) : Http::response('', 200);
            }

            return null;
        });
    }
}
