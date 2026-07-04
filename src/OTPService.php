<?php
/**
 * OTPService - OTP via WhatsApp (Fonnte API)
 * 
 * Menggenerate OTP 6 digit, menyimpan hash-nya di database,
 * dan mengirim via WhatsApp menggunakan Fonnte API.
 * Mendukung expiry 5 menit dan maksimal 5 percobaan verifikasi.
 * 
 * @package App
 */

declare(strict_types=1);

namespace App;

require_once __DIR__ . '/../config/app.php';

class OTPService
{
    /** @var int OTP expiry time in minutes */
    private const OTP_EXPIRY_MINUTES = 5;

    /** @var int Maximum verification attempts */
    private const MAX_ATTEMPTS = 5;

    /** @var int OTP digit length */
    private const OTP_LENGTH = 6;

    /**
     * Generate OTP baru untuk user
     * Generate new 6-digit OTP, hash and store in database
     *
     * @param int $userId User ID yang meminta OTP
     * @return string|false OTP plain text (untuk dikirim via WA) atau false jika gagal
     */
    public static function generate(int $userId): string|false
    {
        try {
            $pdo = getDBConnection();

            // Invalidate semua OTP sebelumnya untuk user ini
            $invalidateSql = "UPDATE otp_codes SET verified = 1 WHERE user_id = :user_id AND verified = 0";
            $invalidateStmt = $pdo->prepare($invalidateSql);
            $invalidateStmt->execute([':user_id' => $userId]);

            // Generate 6-digit OTP
            $otp = str_pad((string) random_int(0, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);

            // Hash OTP sebelum disimpan (sama seperti password)
            $otpHash = password_hash($otp, PASSWORD_DEFAULT);

            // Hitung waktu expiry
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::OTP_EXPIRY_MINUTES . ' minutes'));

            // Simpan ke database
            $sql = "INSERT INTO otp_codes (user_id, otp_hash, expires_at, attempts, verified, created_at) 
                    VALUES (:user_id, :otp_hash, :expires_at, 0, 0, NOW())";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id'    => $userId,
                ':otp_hash'   => $otpHash,
                ':expires_at' => $expiresAt,
            ]);

            return $otp;
        } catch (\PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[OTPService] generate Error: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Verifikasi OTP yang diinput user
     * Verify OTP: check expiry, attempts limit, and hash match
     *
     * @param int    $userId   User ID
     * @param string $inputOtp OTP yang diinput user
     * @return array ['success' => bool, 'message' => string]
     */
    public static function verify(int $userId, string $inputOtp): array
    {
        try {
            $pdo = getDBConnection();

            // Ambil OTP terbaru yang belum digunakan untuk user ini
            $sql = "SELECT id, otp_hash, expires_at, attempts 
                    FROM otp_codes 
                    WHERE user_id = :user_id AND verified = 0 
                    ORDER BY created_at DESC 
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            $otpRecord = $stmt->fetch();

            // Cek apakah ada OTP record
            if (!$otpRecord) {
                return [
                    'success' => false,
                    'message' => 'Kode OTP tidak ditemukan. Silakan minta OTP baru.',
                ];
            }

            // Cek apakah sudah expired
            if (strtotime($otpRecord['expires_at']) < time()) {
                // Tandai sebagai used karena sudah expired
                self::markAsUsed($pdo, (int) $otpRecord['id']);
                return [
                    'success' => false,
                    'message' => 'Kode OTP sudah kadaluarsa. Silakan minta OTP baru.',
                ];
            }

            // Cek apakah sudah melebihi batas percobaan
            if ((int) $otpRecord['attempts'] >= self::MAX_ATTEMPTS) {
                self::markAsUsed($pdo, (int) $otpRecord['id']);
                return [
                    'success' => false,
                    'message' => 'Terlalu banyak percobaan. Silakan minta OTP baru.',
                ];
            }

            // Increment attempt counter
            $updateAttemptSql = "UPDATE otp_codes SET attempts = attempts + 1 WHERE id = :id";
            $updateStmt = $pdo->prepare($updateAttemptSql);
            $updateStmt->execute([':id' => $otpRecord['id']]);

            // Verifikasi OTP dengan password_verify
            if (password_verify($inputOtp, $otpRecord['otp_hash'])) {
                // OTP valid - tandai sebagai used
                self::markAsUsed($pdo, (int) $otpRecord['id']);
                return [
                    'success' => true,
                    'message' => 'Verifikasi OTP berhasil.',
                ];
            }

            // OTP tidak cocok
            $remainingAttempts = self::MAX_ATTEMPTS - ((int) $otpRecord['attempts'] + 1);
            return [
                'success' => false,
                'message' => "Kode OTP salah. Sisa percobaan: {$remainingAttempts}.",
            ];
        } catch (\PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[OTPService] verify Error: " . $e->getMessage());
            }
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat verifikasi OTP.',
            ];
        }
    }

    /**
     * Kirim OTP via WhatsApp menggunakan Fonnte API
     * Send OTP message via WhatsApp using Fonnte API (cURL)
     *
     * @param string $phone Nomor telepon tujuan (format 08xxx atau 628xxx)
     * @param string $otp   Kode OTP yang akan dikirim
     * @return array ['success' => bool, 'message' => string, 'response' => mixed]
     */
    public static function sendViaWhatsApp(string $phone, string $otp): array
    {
        try {
            // Format nomor telepon: 08xxx -> 628xxx
            $phone = self::formatPhoneNumber($phone);

            // Compose pesan OTP
            $message = "🔐 *Kode Verifikasi OTP*\n\n"
                     . "Kode OTP Anda: *{$otp}*\n\n"
                     . "Kode ini berlaku selama " . self::OTP_EXPIRY_MINUTES . " menit.\n"
                     . "Jangan bagikan kode ini kepada siapapun.\n\n"
                     . "— " . (defined('APP_NAME') ? APP_NAME : 'UASKTE App');

            // Kirim via Fonnte API
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => 'https://api.fonnte.com/send',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => [
                    'target'  => $phone,
                    'message' => $message,
                ],
                CURLOPT_HTTPHEADER     => [
                    'Authorization: ' . FONNTE_TOKEN,
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            // Handle cURL error
            if ($curlError) {
                if (defined('APP_DEBUG') && APP_DEBUG) {
                    error_log("[OTPService] cURL Error: " . $curlError);
                }
                return [
                    'success'  => false,
                    'message'  => 'Gagal mengirim OTP. Silakan coba lagi.',
                    'response' => null,
                ];
            }

            $responseData = json_decode($response, true);

            // Cek response dari Fonnte
            if ($httpCode === 200 && isset($responseData['status']) && $responseData['status'] === true) {
                return [
                    'success'  => true,
                    'message'  => 'OTP berhasil dikirim via WhatsApp.',
                    'response' => $responseData,
                ];
            }

            return [
                'success'  => false,
                'message'  => 'Gagal mengirim OTP: ' . ($responseData['reason'] ?? 'Unknown error'),
                'response' => $responseData,
            ];
        } catch (\Exception $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[OTPService] sendViaWhatsApp Error: " . $e->getMessage());
            }
            return [
                'success'  => false,
                'message'  => 'Terjadi kesalahan saat mengirim OTP.',
                'response' => null,
            ];
        }
    }

    /**
     * Hapus semua OTP yang sudah expired
     * Clean up expired OTP entries from database
     *
     * @return int Jumlah record yang dihapus
     */
    public static function cleanExpired(): int
    {
        try {
            $pdo = getDBConnection();

            $sql = "DELETE FROM otp_codes WHERE expires_at < NOW() OR verified = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();

            return $stmt->rowCount();
        } catch (\PDOException $e) {
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("[OTPService] cleanExpired Error: " . $e->getMessage());
            }
            return 0;
        }
    }

    /**
     * Format nomor telepon Indonesia
     * Convert 08xxx format to 628xxx international format
     *
     * @param string $phone Nomor telepon
     * @return string Nomor telepon dalam format internasional
     */
    private static function formatPhoneNumber(string $phone): string
    {
        // Hapus spasi, dash, dan karakter non-digit
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Convert 08xxx ke 628xxx
        if (str_starts_with($phone, '08')) {
            $phone = '62' . substr($phone, 1);
        }

        // Convert +62xxx ke 62xxx (jika ada + yang lolos)
        if (str_starts_with($phone, '+62')) {
            $phone = substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Tandai OTP record sebagai sudah digunakan
     * Mark OTP record as used
     *
     * @param \PDO $pdo PDO instance
     * @param int  $id  OTP record ID
     * @return void
     */
    private static function markAsUsed(\PDO $pdo, int $id): void
    {
        $sql = "UPDATE otp_codes SET verified = 1 WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
    }
}
