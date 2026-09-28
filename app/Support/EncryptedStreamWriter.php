<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\BackupException;

/**
 * يكتب نسخة مشفّرة جزءًا بعد جزء بصيغة App\Support\BackupCipher. لا تكتمل النسخة
 * إلا باستدعاء close() الذي يكتب الجزء الأخير بعلامة النهاية.
 */
final class EncryptedStreamWriter
{
    private string $state;

    private string $associatedData;

    private string $buffer = '';

    private bool $closed = false;

    /**
     * @param  resource  $output
     */
    public function __construct(private $output, string $key, string $keyId)
    {
        [$this->state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        $this->associatedData = BackupCipher::MAGIC.$keyId;

        $this->put($this->associatedData.$header);
    }

    /**
     * @throws BackupException
     */
    public function write(string $data): void
    {
        if ($this->closed) {
            throw BackupException::io('الكتابة بعد إغلاق النسخة');
        }

        $this->buffer .= $data;

        while (strlen($this->buffer) >= BackupCipher::CHUNK_BYTES) {
            $this->push(substr($this->buffer, 0, BackupCipher::CHUNK_BYTES), SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);
            $this->buffer = substr($this->buffer, BackupCipher::CHUNK_BYTES);
        }
    }

    /**
     * @throws BackupException
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->push($this->buffer, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
        $this->buffer = '';
        $this->closed = true;
    }

    private function push(string $plain, int $tag): void
    {
        $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($this->state, $plain, $this->associatedData, $tag);

        $this->put(pack('N', strlen($cipher)).$cipher);
    }

    /**
     * @throws BackupException
     */
    private function put(string $bytes): void
    {
        $offset = 0;
        $length = strlen($bytes);

        while ($offset < $length) {
            $written = fwrite($this->output, substr($bytes, $offset));

            if ($written === false || $written === 0) {
                throw BackupException::io('كتابة النسخة المشفّرة');
            }

            $offset += $written;
        }
    }
}
