<?php
/**
 * WebAuthnService - Simplified WebAuthn / Biometric Authentication
 * 
 * Implementasi sederhana WebAuthn untuk autentikasi biometrik.
 * Tidak menggunakan library eksternal — cocok untuk project mahasiswa.
 * Mendukung registrasi dan verifikasi credential passkey/biometric.
 * 
 * @package App
 */

declare(strict_types=1);

namespace App;

require_once __DIR__ . '/../config/app.php';

class WebAuthnService
{
    /** @var int Challenge length in bytes */
    private const CHALLENGE_LENGTH = 32;

    /** @var string Relying Party name (nama aplikasi) */
    private const RP_NAME = 'UASKTE App';

    /**
     * Generate opsi registrasi WebAuthn
     * Generate PublicKeyCredentialCreationOptions for new credential registration
     *
     * @param int    $userId   User ID
     * @param string $userName User name / display name
     * @return array PublicKeyCredentialCreationOptions (siap di-encode ke JSON)
     */
    public static function getRegistrationOptions(int $userId, string $userName): array
    {
        // Generate random challenge
        $challenge = random_bytes(self::CHALLENGE_LENGTH);
        $challengeB64 = self::base64urlEncode($challenge);

        // Simpan challenge di session untuk verifikasi nanti
        $_SESSION['webauthn_challenge'] = $challengeB64;
        $_SESSION['webauthn_action'] = 'register';

        // Ambil credential yang sudah ada (untuk excludeCredentials)
        $existingCredentials = self::getExistingCredentialIds($userId);
        $excludeCredentials = array_map(function ($credId) {
            return [
                'type' => 'public-key',
                'id'   => $credId,
            ];
        }, $existingCredentials);

        // Determine RP ID dari APP_URL
        $rpId = self::getRpId();

        $options = [
            'rp' => [
                'name' => self::RP_NAME,
                'id'   => $rpId,
            ],
            'user' => [
                'id'          => self::base64urlEncode(strval($userId)),
                'name'        => $userName,
                'displayName' => $userName,
            ],
            'challenge' => $challengeB64,
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],   // ES256
                ['type' => 'public-key', 'alg' => -257],  // RS256
            ],
            'timeout' => 60000, // 60 detik
            'attestation' => 'none',
            'authenticatorSelection' => [
                'requireResidentKey'      => true,
                'userVerification'        => 'required',
            ],
            'excludeCredentials' => $excludeCredentials,
        ];

        return $options;
    }

    /**
     * Verifikasi dan simpan credential baru dari registrasi
     * Verify registration response and store credential in database
     *
     * @param int   $userId        User ID
     * @param array $credentialData Data credential dari browser:
     *                              - credential_id: base64url encoded credential ID
     *                              - public_key: base64url encoded public key
     *                              - attestation_object: (optional) raw attestation
     * @return array ['success' => bool, 'message' => string]
     */
    public static function verifyRegistration(int $userId, array $credentialData): array
    {
        try {
            // Validasi challenge dari session
            if (!isset($_SESSION['webauthn_challenge']) || $_SESSION['webauthn_action'] !== 'register') {
                return [
                    'success' => false,
                    'message' => 'Challenge tidak valid atau sudah expired.',
                ];
            }

            // Validasi credential data
            if (empty($credentialData['credential_id'])) {
                return [
                    'success' => false,
                    'message' => 'Credential ID tidak ditemukan.',
                ];
            }

            $pdo = getDBConnection();

            // Cek apakah credential_id sudah terdaftar
            $checkSql = "SELECT id FROM webauthn_credentials WHERE credential_id = :credential_id";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([':credential_id' => $credentialData['credential_id']]);

            if ($checkStmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Credential sudah terdaftar sebelumnya.',
                ];
            }

            // Simpan credential ke database
            $sql = "INSERT INTO webauthn_credentials 
                    (user_id, credential_id, public_key, sign_count, created_at) 
                    VALUES 
                    (:user_id, :credential_id, :public_key, 0, NOW())";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id'       => $userId,
                ':credential_id' => $credentialData['credential_id'],
                ':public_key'    => $credentialData['public_key'] ?? '',
            ]);

            // Hapus challenge dari session
            unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_action']);

            return [
                'success' => true,
                'message' => 'Biometric credential berhasil didaftarkan.',
            ];
        } catch (\PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[WebAuthn] verifyRegistration Error: " . $e->getMessage());
            }
            return [
                'success' => false,
                'message' => 'Gagal menyimpan credential.',
            ];
        }
    }

    /**
     * Generate opsi authentication (login) WebAuthn
     * Generate PublicKeyCredentialRequestOptions for authentication
     *
     * @param int $userId User ID
     * @return array|null Options array atau null jika user tidak punya credential
     */
    public static function getAuthenticationOptions(int $userId): ?array
    {
        // Ambil semua credential yang terdaftar untuk user ini
        $credentials = self::getExistingCredentialIds($userId);

        if (empty($credentials)) {
            return null; // User belum punya credential
        }

        // Generate challenge baru
        $challenge = random_bytes(self::CHALLENGE_LENGTH);
        $challengeB64 = self::base64urlEncode($challenge);

        // Simpan di session
        $_SESSION['webauthn_challenge'] = $challengeB64;
        $_SESSION['webauthn_action'] = 'authenticate';

        // Build allowCredentials list
        $allowCredentials = array_map(function ($credId) {
            return [
                'type'       => 'public-key',
                'id'         => $credId,
            ];
        }, $credentials);

        $rpId = self::getRpId();

        return [
            'challenge'        => $challengeB64,
            'timeout'          => 60000,
            'rpId'             => $rpId,
            'allowCredentials' => $allowCredentials,
            'userVerification' => 'preferred',
        ];
    }

    /**
     * Verifikasi assertion dari authentication
     * Verify authentication assertion (simplified: credential exists + increment sign_count)
     *
     * @param int   $userId        User ID
     * @param array $assertionData Data assertion dari browser:
     *                              - credential_id: base64url encoded credential ID
     *                              - authenticator_data: (optional)
     *                              - signature: (optional)
     *                              - client_data_json: (optional)
     * @return array ['success' => bool, 'message' => string]
     */
    public static function verifyAuthentication(int $userId, array $assertionData): array
    {
        try {
            // Validasi challenge
            if (!isset($_SESSION['webauthn_challenge']) || $_SESSION['webauthn_action'] !== 'authenticate') {
                return [
                    'success' => false,
                    'message' => 'Challenge tidak valid atau sudah expired.',
                ];
            }

            if (empty($assertionData['credential_id'])) {
                return [
                    'success' => false,
                    'message' => 'Credential ID tidak ditemukan dalam assertion.',
                ];
            }

            $pdo = getDBConnection();

            // Verifikasi: cek credential_id milik user ini
            $sql = "SELECT id, sign_count FROM webauthn_credentials 
                    WHERE user_id = :user_id AND credential_id = :credential_id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id'       => $userId,
                ':credential_id' => $assertionData['credential_id'],
            ]);

            $credential = $stmt->fetch();

            if (!$credential) {
                return [
                    'success' => false,
                    'message' => 'Credential tidak ditemukan atau bukan milik user ini.',
                ];
            }

            // Update sign_count (increment counter untuk keamanan)
            $updateSql = "UPDATE webauthn_credentials 
                          SET sign_count = sign_count + 1, last_used_at = NOW() 
                          WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([':id' => $credential['id']]);

            // Hapus challenge dari session
            unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_action']);

            return [
                'success' => true,
                'message' => 'Autentikasi biometrik berhasil.',
            ];
        } catch (\PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[WebAuthn] verifyAuthentication Error: " . $e->getMessage());
            }
            return [
                'success' => false,
                'message' => 'Gagal memverifikasi autentikasi biometrik.',
            ];
        }
    }

    /**
     * Cek apakah user sudah punya credential biometric
     * Check if user has registered biometric credentials
     *
     * @param int $userId User ID
     * @return bool True jika user punya minimal satu credential
     */
    public static function hasCredentials(int $userId): bool
    {
        try {
            $pdo = getDBConnection();

            $sql = "SELECT COUNT(*) as total FROM webauthn_credentials WHERE user_id = :user_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':user_id' => $userId]);

            return ((int) $stmt->fetch()['total']) > 0;
        } catch (\PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[WebAuthn] hasCredentials Error: " . $e->getMessage());
            }
            return false;
        }
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Ambil daftar credential ID yang sudah terdaftar untuk user
     * Get existing credential IDs for a user
     *
     * @param int $userId User ID
     * @return array Array of credential_id strings
     */
    private static function getExistingCredentialIds(int $userId): array
    {
        try {
            $pdo = getDBConnection();

            $sql = "SELECT credential_id FROM webauthn_credentials WHERE user_id = :user_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':user_id' => $userId]);

            return array_column($stmt->fetchAll(), 'credential_id');
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Dapatkan Relying Party ID dari APP_URL
     * Extract hostname from APP_URL for RP ID
     *
     * @return string Hostname (e.g., 'localhost')
     */
    private static function getRpId(): string
    {
        if (defined('APP_URL')) {
            $parsed = parse_url(APP_URL);
            return $parsed['host'] ?? 'localhost';
        }
        return 'localhost';
    }

    /**
     * Base64url encode (RFC 4648)
     * URL-safe base64 encoding tanpa padding
     *
     * @param string $data Data yang akan di-encode
     * @return string Base64url encoded string
     */
    private static function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64url decode (RFC 4648)
     * URL-safe base64 decoding
     *
     * @param string $data Base64url encoded string
     * @return string Decoded data
     */
    private static function base64urlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
