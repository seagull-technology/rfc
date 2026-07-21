<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Gsb\SignFlowOpenService;
use App\Services\Gsb\SignFlowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SanadLoginController extends Controller
{
    private const SESSION_TTL_SECONDS = 600;

    public function redirectToProvider(Request $request, SignFlowService $signFlow): RedirectResponse
    {
        if (! $signFlow->isAuthorizationConfigured()) {
            return redirect()->route('login')->withErrors([
                'sanad' => __('app.auth.sanad_not_configured'),
            ]);
        }

        $state = Str::random(64);
        $verifier = Str::random(96);
        $challenge = $this->base64UrlEncode(hash('sha256', $verifier, true));
        $redirectUri = $signFlow->redirectUri(route('sanad.callback'));

        $request->session()->put([
            'sanad_oauth_state' => $state,
            'sanad_pkce_verifier' => $verifier,
            'sanad_oauth_started_at' => now()->timestamp,
        ]);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $signFlow->clientId(),
            'redirect_uri' => $redirectUri,
            'scope' => $signFlow->scope(),
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        $separator = str_contains($signFlow->authorizationUrl(), '?') ? '&' : '?';

        return redirect()->away($signFlow->authorizationUrl().$separator.$query);
    }

    public function callback(
        Request $request,
        SignFlowService $signFlow,
        SignFlowOpenService $signFlowOpen,
    ): RedirectResponse {
        $expectedState = (string) $request->session()->pull('sanad_oauth_state', '');
        $verifier = (string) $request->session()->pull('sanad_pkce_verifier', '');
        $startedAt = (int) $request->session()->pull('sanad_oauth_started_at', 0);
        $receivedState = (string) $request->query('state', '');

        if ($request->filled('error')) {
            return $this->loginError('sanad_cancelled');
        }

        if ($expectedState === ''
            || $receivedState === ''
            || ! hash_equals($expectedState, $receivedState)
            || $verifier === ''
            || $startedAt < now()->subSeconds(self::SESSION_TTL_SECONDS)->timestamp) {
            return $this->loginError('sanad_invalid_session');
        }

        $code = trim((string) $request->query('code', ''));

        if ($code === '') {
            return $this->loginError('sanad_missing_code');
        }

        $tokenResult = $signFlow->exchangeAuthorizationCode(
            $code,
            $verifier,
            $signFlow->redirectUri(route('sanad.callback')),
        );
        $accessToken = $this->accessToken($tokenResult);

        if (! ($tokenResult['ok'] ?? false) || $accessToken === null) {
            Log::warning('SANAD authorization code exchange failed.', [
                'status' => $tokenResult['status'] ?? null,
                'error' => $tokenResult['error'] ?? null,
            ]);

            return $this->loginError('sanad_verification_failed');
        }

        try {
            $identityResult = $signFlowOpen->introspect($accessToken);
            $nationalId = $signFlowOpen->nationalId($identityResult);

            if (! ($identityResult['ok'] ?? false) || $nationalId === null) {
                Log::warning('SANAD identity introspection failed.', [
                    'status' => $identityResult['status'] ?? null,
                    'error' => $identityResult['error'] ?? null,
                ]);

                return $this->loginError('sanad_verification_failed');
            }

            $user = User::query()->where('national_id', $nationalId)->first();

            if (! $user) {
                return $this->loginError('sanad_account_not_found');
            }

            if (! $user->canSignIn()) {
                return $this->loginError(
                    $user->requiresAdminApprovalBeforeLogin()
                        ? 'approval_required_before_login'
                        : 'invalid_credentials',
                );
            }

            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put('current_entity_id', $user->primaryEntity()?->getKey());

            $user->forceFill(['last_login_at' => now()])->save();

            return redirect()->route('dashboard');
        } finally {
            $logoutResult = $signFlowOpen->logout($accessToken);

            if (! ($logoutResult['ok'] ?? false)) {
                Log::notice('SANAD remote session logout failed after authentication.', [
                    'status' => $logoutResult['status'] ?? null,
                    'error' => $logoutResult['error'] ?? null,
                ]);
            }
        }
    }

    /** @param array<string, mixed> $result */
    private function accessToken(array $result): ?string
    {
        foreach (['data.access_token', 'data.data.access_token', 'data.token.access_token'] as $path) {
            $token = trim((string) data_get($result, $path));

            if ($token !== '') {
                return $token;
            }
        }

        return null;
    }

    private function loginError(string $translationKey): RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'sanad' => __('app.auth.'.$translationKey),
        ]);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
