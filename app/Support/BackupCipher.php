<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\BackupException;

/**
 * تشفير النسخ الاحتياطية قبل رفعها (T21): libsodium secretstream
 * (XChaCha20-Poly1305) على أجزاء متتابعة، فيُشفَّر الملف الكبير دون تحميله كاملًا
 * في الذاكرة، ويُكشف أي تعديل أو إعادة ترتيب أو اقتطاع للأجزاء.
 *
 * صيغة الملف:
 *   MAGIC (5 بايت) + معرّف المفتاح (8 بايت) + ترويسة secretstream (24 بايت)
 *   ثم أجزاء: طول الجزء المشفّر (4 بايت big-endian) + الجزء. آخر جزء يحمل TAG_FINAL.
 * المعرّف مشتق من المفتاح باتجاه واحد، ليُعرف "مفتاح خاطئ" قبل محاولة فك التشفير.
 *
 * المفتاح من BACKUP_ENCRYPTION_KEY بصيغة base64:… (32 بايت)، ولا يُطبع ولا يُسجَّل.
 */
final class BackupCipher
{
    public const string MAGIC = "AJWB\x01";

    public const int KEY_ID_BYTES = 8;

    public const int CHUNK_BYTES = 65536;

    private function __construct(private readonly string $key) {}

    /**
     * @throws BackupException
     */
    public static function fromConfig(): self
    {
        $configured = (string) config('backup.encryption_key');

        if ($configured === '') {
            throw BackupException::missingKey();
        }

        $key = str_starts_with($configured, 'base64:') ? base64_decode(substr($configured, 7), true) : false;

        if (! is_string($key) || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw BackupException::invalidKey();
        }

        return new self($key);
    }

    /**
     * مفتاح جديد بالصيغة المطلوبة في BACKUP_ENCRYPTION_KEY.
     */
    public static function generateKey(): string
    {
        return 'base64:'.base64_encode(sodium_crypto_secretstream_xchacha20poly1305_keygen());
    }

    public function keyId(): string
    {
        return substr(hash('sha256', 'ajawna-backup-key-id:'.$this->key, true), 0, self::KEY_ID_BYTES);
    }

    /**
     * @param  resource  $output
     */
    public function writer($output): EncryptedStreamWriter
    {
        return new EncryptedStreamWriter($output, $this->key, $this->keyId());
    }

    /**
     * @param  resource  $input
     *
     * @throws BackupException
     */
    public function reader($input): EncryptedStreamReader
    {
        return new EncryptedStreamReader($input, $this->key, $this->keyId());
    }

    /**
     * @param  resource  $input
     * @param  resource  $output
     *
     * @throws BackupException
     */
    public function encryptStream($input, $output): void
    {
        $writer = $this->writer($output);

        while (! feof($input)) {
            $data = fread($input, self::CHUNK_BYTES);

            if ($data === false) {
                throw BackupException::io('قراءة الملف المصدر');
            }

            $writer->write($data);
        }

        $writer->close();
    }

    /**
     * @param  resource  $input
     * @param  resource  $output
     *
     * @throws BackupException
     */
    public function decryptStream($input, $output): void
    {
        $reader = $this->reader($input);

        while (($chunk = $reader->read()) !== null) {
            if ($chunk !== '' && fwrite($output, $chunk) === false) {
                throw BackupException::io('كتابة الملف المسترجع');
            }
        }
    }
}
