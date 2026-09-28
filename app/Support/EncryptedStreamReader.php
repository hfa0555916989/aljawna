<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\BackupException;

/**
 * يفك تشفير نسخة بصيغة App\Support\BackupCipher جزءًا بعد جزء، ويرفض الملف إن
 * كان بمفتاح آخر، أو عُدِّل أي جزء منه، أو انتهى قبل علامة النهاية، أو زاد بعدها.
 */
final class EncryptedStreamReader
{
    private string $state;

    private string $associatedData;

    private bool $finished = false;

    /**
     * @param  resource  $input
     *
     * @throws BackupException
     */
    public function __construct(private $input, string $key, string $keyId)
    {
        $prefix = $this->readExactly(strlen(BackupCipher::MAGIC) + BackupCipher::KEY_ID_BYTES);

        if ($prefix === null || ! str_starts_with($prefix, BackupCipher::MAGIC)) {
            throw BackupException::notABackup();
        }

        if (! hash_equals($keyId, substr($prefix, strlen(BackupCipher::MAGIC)))) {
            throw BackupException::wrongKey();
        }

        $header = $this->readExactly(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);

        if ($header === null) {
            throw BackupException::truncated();
        }

        $this->associatedData = $prefix;
        $this->state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
    }

    /**
     * الجزء التالي بعد فك تشفيره، أو null بعد الجزء الأخير.
     *
     * @throws BackupException
     */
    public function read(): ?string
    {
        if ($this->finished) {
            return null;
        }

        $lengthBytes = $this->readExactly(4);

        if ($lengthBytes === null) {
            throw BackupException::truncated();
        }

        /** @var array{1: int} $unpacked */
        $unpacked = unpack('N', $lengthBytes);
        $length = $unpacked[1];

        if ($length < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES || $length > BackupCipher::CHUNK_BYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) {
            throw BackupException::corrupted();
        }

        $cipher = $this->readExactly($length) ?? throw BackupException::truncated();
        $result = sodium_crypto_secretstream_xchacha20poly1305_pull($this->state, $cipher, $this->associatedData);

        if ($result === false) {
            throw BackupException::corrupted();
        }

        [$plain, $tag] = $result;

        if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
            $this->finished = true;

            if ($this->readExactly(1) !== null) {
                throw BackupException::corrupted();
            }
        }

        return $plain;
    }

    /**
     * يقرأ العدد المطلوب بالضبط، أو null إن انتهى الملف قبل أي بايت. نهاية الملف في
     * منتصف القراءة تعني نسخة ناقصة.
     *
     * @throws BackupException
     */
    private function readExactly(int $length): ?string
    {
        $data = '';

        while (($remaining = $length - strlen($data)) > 0 && ! feof($this->input)) {
            $chunk = fread($this->input, $remaining);

            if ($chunk === false) {
                throw BackupException::io('قراءة النسخة');
            }

            $data .= $chunk;
        }

        if ($data === '') {
            return null;
        }

        if (strlen($data) < $length) {
            throw BackupException::truncated();
        }

        return $data;
    }
}
