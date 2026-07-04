<?php
/**
 * GoogleOAuth - Google SSO Service
 * 
 * Menggunakan league/oauth2-google untuk implementasi login dengan Google.
 * Mendukung CSRF protection dengan state token di session.
 * 
 * @package App
 */

declare(strict_types=1);

namespace App;

require_once __DIR__ . '/../config/app.php';

use League\OAuth2\Client\Provider\Google;

class GoogleOAuth
{
    /** @var Google Provider instance */
    private static ?Google $provider = null;

    /**
     * Dapatkan Google OAuth provider instance (singleton)
     * Get or create the Google OAuth2 provider
     *
     * @return Google
     */
    private static function getProvider(): Google
    {
        if (self::$provider === null) {
            self::$provider = new Google([
                'clientId'     => GOOGLE_CLIENT_ID,
                'clientSecret' => GOOGLE_CLIENT_SECRET,
                'redirectUri'  => GOOGLE_REDIRECT_URI,
            ]);
        }

        return self::$provider;
    }

    /**
     * Generate URL login Google dengan state token untuk CSRF protection
     * Generate Google OAuth authorization URL with CSRF state token
     *
     * @return string Authorization URL
     */
    public static function getAuthUrl(): string
    {
        $provider = self::getProvider();

        $authUrl = $provider->getAuthorizationUrl([
            'scope' => [
                'openid',
                'email',
                'profile',
            ],
            'prompt' => 'select_account',
        ]);

        // Simpan state token di session untuk validasi CSRF
        $_SESSION['oauth2_state'] = $provider->getState();

        return $authUrl;
    }

    /**
     * Handle callback dari Google setelah user login
     * Exchange authorization code for token and get user profile
     *
     * @param string $code  Authorization code dari Google
     * @param string $state State token untuk validasi CSRF
     * @return array|null User profile data atau null jika gagal
     *                    Keys: email, name, avatar, google_id, first_name, last_name
     */
    public static function handleCallback(string $code, string $state): ?array
    {
        try {
            // Validasi state token (CSRF protection)
            if (!self::validateState($state)) {
                if (defined('APP_DEBUG') && APP_DEBUG) {
                    error_log("[GoogleOAuth] Invalid state token - possible CSRF attack");
                }
                return null;
            }

            $provider = self::getProvider();

            // Tukarkan authorization code dengan access token
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);

            // Ambil profil user dari Google
            /** @var \League\OAuth2\Client\Provider\GoogleUser $googleUser */
            $googleUser = $provider->getResourceOwner($token);

            // Hapus state token dari session setelah digunakan
            unset($_SESSION['oauth2_state']);

            return [
                'google_id'  => $googleUser->getId(),
                'email'      => $googleUser->getEmail(),
                'name'       => $googleUser->getName(),
                'first_name' => $googleUser->getFirstName(),
                'last_name'  => $googleUser->getLastName(),
                'avatar'     => $googleUser->getAvatar(),
            ];
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[GoogleOAuth] Identity Provider Error: " . $e->getMessage());
            }
            return null;
        } catch (\Exception $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[GoogleOAuth] Callback Error: " . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Validasi state token untuk mencegah CSRF attack
     * Validate CSRF state token from session
     *
     * @param string $state State token dari callback URL
     * @return bool True jika state valid
     */
    public static function validateState(string $state): bool
    {
        if (empty($state)) {
            return false;
        }

        if (!isset($_SESSION['oauth2_state']) || empty($_SESSION['oauth2_state'])) {
            return false;
        }

        return hash_equals($_SESSION['oauth2_state'], $state);
    }
}
