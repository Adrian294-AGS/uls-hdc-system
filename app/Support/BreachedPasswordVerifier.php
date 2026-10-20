<?php

namespace App\Support;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Stringable;
use Illuminate\Validation\NotPwnedVerifier;
use Throwable;

/**
 * Password::uncompromised() check against the Have I Been Pwned range API. If the service cannot be
 * reached, the password is allowed (as in Laravel) and a one-line warning is logged instead of an
 * error with a stack trace, because being offline is expected and is not a fault in the app.
 */
final class BreachedPasswordVerifier extends NotPwnedVerifier
{
    public function __construct(HttpFactory $factory, int $timeout = 5)
    {
        parent::__construct($factory, $timeout);
    }

    /**
     * @return Collection<int, string>
     */
    protected function search($hashPrefix)
    {
        $body = '';

        try {
            $response = $this->factory->withHeaders(['Add-Padding' => 'true'])
                ->timeout($this->timeout)
                ->get('https://api.pwnedpasswords.com/range/'.$hashPrefix);

            if ($response->successful()) {
                $body = $response->body();
            } else {
                Log::warning('Breached-password check unavailable (HTTP '.$response->status().'); password allowed.');
            }
        } catch (Throwable $exception) {
            Log::warning('Breached-password check unavailable ('.class_basename($exception).'); password allowed.');
        }

        return (new Stringable($body))->trim()->explode("\n")->filter(fn ($line) => str_contains($line, ':'));
    }
}
