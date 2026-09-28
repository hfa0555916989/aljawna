<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BackupException;
use App\Support\BackupCipher;
use App\Support\BackupRetentionPolicy;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Throwable;

/**
 * نسخة قاعدة البيانات اليومية المشفّرة خارج Laravel Cloud (T21، docs/RUNBOOK.md).
 *
 * لا تعتمد على pg_dump (غير مؤكَّد توفّره في بيئة Laravel Cloud، ويلزم أن يطابق إصدار
 * الخادم): تقرأ كل جدول من التطبيق نفسه داخل معاملة REPEATABLE READ للقراءة فقط
 * (لقطة متسقة)، وتكتب سطور JSON مضغوطة gzip ومشفّرة قبل أن تغادر الخادم.
 *
 * البنية (الجداول والقيود والمشغّلات والفهارس) لا تُنسخ؛ تأتي من الترحيلات. لذلك تحفظ
 * النسخة أسماء الترحيلات التي كانت منفّذة، والاسترجاع يبني البنية بها بالضبط ثم يحمّل
 * البيانات ثم ينفّذ ما استجد بعدها من ترحيلات، كل ذلك في معاملة واحدة: أي فشل يعيد
 * القاعدة كما كانت.
 *
 * صيغة المحتوى (سطر JSON لكل عنصر):
 *   {"format": …, "version": 1, "created_at": …, "migrations": […], "tables": […]}
 *   {"table": "users", "columns": […]} ثم صف لكل سطر بترتيب الأعمدة […]
 *   {"end": true, "rows": {"users": 10, …}}
 * الجداول مرتبة بحسب المفاتيح الأجنبية (الأب قبل الابن) لتُحمَّل بالترتيب نفسه.
 *
 * @phpstan-type Summary array{created_at: string, migrations: list<string>, tables: list<string>, rows: array<string, int>}
 */
class DatabaseBackup
{
    public const string FORMAT = 'ajawna-database-backup';

    public const int VERSION = 1;

    public const string EXTENSION = '.ajdb';

    private const string NAME_FORMAT = 'Y-m-d\THis\Z';

    private const int READ_CHUNK = 1000;

    private const int INSERT_BATCH = 500;

    private const int JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

    /**
     * ينشئ نسخة جديدة ويرفعها، ويعيد مسارها في وجهة النسخ.
     *
     * @throws BackupException
     */
    public function create(): string
    {
        $cipher = BackupCipher::fromConfig();
        $temp = $this->temporaryStream();

        try {
            $writer = $cipher->writer($temp);
            $deflate = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]) ?: throw BackupException::io('بدء الضغط');

            $this->dump(function (array $line) use ($writer, $deflate): void {
                $writer->write((string) deflate_add($deflate, json_encode($line, self::JSON_FLAGS)."\n", ZLIB_NO_FLUSH));
            });

            $writer->write((string) deflate_add($deflate, '', ZLIB_FINISH));
            $writer->close();
            rewind($temp);

            $path = $this->prefix().'/'.now('UTC')->format(self::NAME_FORMAT).self::EXTENSION;

            if (! $this->disk()->writeStream($path, $temp)) {
                throw BackupException::io('رفع النسخة');
            }

            return $path;
        } finally {
            fclose($temp);
        }
    }

    /**
     * النسخ الموجودة من الأقدم إلى الأحدث.
     *
     * @return array<string, CarbonImmutable> المسار => وقت الإنشاء
     */
    public function list(): array
    {
        $backups = [];

        foreach ($this->disk()->files($this->prefix()) as $path) {
            $createdAt = $this->createdAtFromPath($path);

            if ($createdAt !== null) {
                $backups[$path] = $createdAt;
            }
        }

        asort($backups);

        return $backups;
    }

    public function latest(): ?string
    {
        $backups = $this->list();

        return $backups === [] ? null : (string) array_key_last($backups);
    }

    /**
     * يحذف ما خرج عن سياسة الاحتفاظ، ويعيد المسارات المحذوفة.
     *
     * @return list<string>
     */
    public function prune(): array
    {
        $backups = $this->list();
        $keep = array_flip(BackupRetentionPolicy::fromConfig()->keep($backups, now('UTC')));
        $deleted = [];

        foreach (array_keys($backups) as $path) {
            if (! isset($keep[$path])) {
                $this->disk()->delete((string) $path);
                $deleted[] = (string) $path;
            }
        }

        return $deleted;
    }

    /**
     * يفك تشفير النسخة ويتحقق من اكتمالها وسلامة محتواها دون أن يكتب شيئًا في القاعدة.
     *
     * @return Summary
     *
     * @throws BackupException
     */
    public function verify(string $path): array
    {
        $local = $this->download($path);

        try {
            return $this->inspect($local);
        } finally {
            fclose($local);
        }
    }

    /**
     * يسترجع النسخة إلى الاتصال الافتراضي. القاعدة الهدف يجب أن تكون فارغة، أو يُمرَّر
     * $wipe فتُحذف كل جداولها أولًا. كل الخطوات في معاملة واحدة.
     *
     * @return Summary
     *
     * @throws BackupException
     */
    public function restore(string $path, bool $wipe): array
    {
        $local = $this->download($path);

        try {
            $summary = $this->inspect($local);
            $files = $this->migrationFiles($summary['migrations']);
            rewind($local);

            DB::transaction(function () use ($wipe, $files, $local): void {
                if ($this->tableNames() !== []) {
                    if (! $wipe) {
                        throw BackupException::targetNotEmpty();
                    }

                    Schema::dropAllTables();
                }

                $migrator = $this->migrator();
                $migrator->getRepository()->createRepository();
                $migrator->run($files);

                $this->load($local);
                $this->resetSequences();

                $migrator->run([database_path('migrations')]);
            });

            return $summary;
        } finally {
            fclose($local);
        }
    }

    /**
     * @param  callable(array<mixed>): void  $emit
     */
    private function dump(callable $emit): void
    {
        $connection = $this->connection();

        $run = function () use ($connection, $emit): void {
            $tables = $this->orderedTables();

            $emit([
                'format' => self::FORMAT,
                'version' => self::VERSION,
                'created_at' => now('UTC')->toIso8601String(),
                'migrations' => $connection->table('migrations')->orderBy('id')->pluck('migration')->map(fn (mixed $name): string => (string) $name)->all(),
                'tables' => $tables,
            ]);

            $counts = [];

            foreach ($tables as $table) {
                $columns = $this->columns($table);
                $emit(['table' => $table, 'columns' => $columns]);
                $counts[$table] = 0;

                foreach ($this->rows($table, $columns) as $row) {
                    $emit(array_map(fn (string $column): mixed => $row->{$column}, $columns));
                    $counts[$table]++;
                }
            }

            $emit(['end' => true, 'rows' => $counts]);
        };

        // لقطة متسقة لكل الجداول. داخل معاملة قائمة (الاختبارات) تُقرأ بمستوى عزلها.
        if ($connection->transactionLevel() > 0) {
            $run();

            return;
        }

        $connection->transaction(function () use ($connection, $run): void {
            $connection->statement('set transaction isolation level repeatable read, read only');
            $run();
        });
    }

    /**
     * @param  list<string>  $columns
     * @return iterable<object>
     */
    private function rows(string $table, array $columns): iterable
    {
        $query = $this->connection()->table($table);

        if (in_array('id', $columns, true)) {
            return $query->lazyById(self::READ_CHUNK);
        }

        return $query->orderBy($columns[0])->cursor();
    }

    /**
     * @param  resource  $local
     * @return Summary
     *
     * @throws BackupException
     */
    private function inspect($local): array
    {
        $meta = null;
        $table = null;
        $columnCount = 0;
        $counts = [];
        $end = null;

        foreach ($this->lines($local) as $line) {
            if ($end !== null) {
                throw BackupException::invalidContent('بيانات بعد علامة النهاية');
            }

            if ($meta === null) {
                if (($line['format'] ?? null) !== self::FORMAT || ($line['version'] ?? null) !== self::VERSION) {
                    throw BackupException::invalidContent('صيغة غير معروفة');
                }

                $meta = $line;

                continue;
            }

            if (array_is_list($line)) {
                if ($table === null || count($line) !== $columnCount) {
                    throw BackupException::invalidContent('صف خارج جدول أو بعدد أعمدة خاطئ');
                }

                $counts[$table]++;

                continue;
            }

            if (isset($line['table'], $line['columns']) && is_array($line['columns'])) {
                $table = (string) $line['table'];
                $columnCount = count($line['columns']);
                $counts[$table] = 0;

                continue;
            }

            if (($line['end'] ?? false) === true) {
                $end = $line;

                continue;
            }

            throw BackupException::invalidContent('سطر غير معروف');
        }

        if ($meta === null || $end === null) {
            throw BackupException::truncated();
        }

        if (($end['rows'] ?? null) !== $counts || array_keys($counts) !== $meta['tables']) {
            throw BackupException::invalidContent('عدد الصفوف لا يطابق علامة النهاية');
        }

        return [
            'created_at' => (string) $meta['created_at'],
            'migrations' => array_values(array_map(fn (mixed $name): string => (string) $name, (array) $meta['migrations'])),
            'tables' => array_map(fn (mixed $name): string => (string) $name, $meta['tables']),
            'rows' => $counts,
        ];
    }

    /**
     * @param  resource  $local
     */
    private function load($local): void
    {
        $table = '';
        $columns = [];
        $batch = [];

        foreach ($this->lines($local) as $line) {
            if (array_is_list($line)) {
                $batch[] = array_combine($columns, $line);

                if (count($batch) >= self::INSERT_BATCH) {
                    $this->insert($table, $batch);
                    $batch = [];
                }

                continue;
            }

            if (isset($line['table'])) {
                $this->insert($table, $batch);
                $batch = [];
                $table = (string) $line['table'];
                $columns = array_map(fn (mixed $column): string => (string) $column, (array) $line['columns']);
            }
        }

        $this->insert($table, $batch);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insert(string $table, array $rows): void
    {
        if ($table !== '' && $rows !== []) {
            $this->connection()->table($table)->insert($rows);
        }
    }

    /**
     * يضبط كل تسلسل (serial/identity) على أكبر قيمة محمّلة، فلا تتصادم المعرّفات الجديدة.
     */
    private function resetSequences(): void
    {
        $connection = $this->connection();
        $grammar = $connection->getQueryGrammar();

        $columns = $connection->select(
            "select table_name, column_name from information_schema.columns
             where table_schema = current_schema() and (column_default like 'nextval(%' or is_identity = 'YES')",
        );

        foreach ($columns as $column) {
            $table = (string) $column->table_name;
            $wrapped = $grammar->wrap((string) $column->column_name);

            $connection->selectOne(
                "select setval(pg_get_serial_sequence(?, ?), coalesce(max({$wrapped}), 1), max({$wrapped}) is not null) from ".$grammar->wrapTable($table),
                [$table, (string) $column->column_name],
            );
        }
    }

    /**
     * جداول البيانات مرتبة بحيث يسبق كل جدول ما يعتمد عليه بمفتاح أجنبي.
     *
     * @return list<string>
     *
     * @throws BackupException
     */
    private function orderedTables(): array
    {
        $tables = array_values(array_diff($this->tableNames(), (array) config('backup.database.excluded_tables')));
        $parents = array_fill_keys($tables, []);

        $foreignKeys = $this->connection()->select(
            "select distinct tc.table_name as child, ccu.table_name as parent
             from information_schema.table_constraints tc
             join information_schema.constraint_column_usage ccu
               on ccu.constraint_name = tc.constraint_name and ccu.table_schema = tc.table_schema
             where tc.constraint_type = 'FOREIGN KEY' and tc.table_schema = current_schema()",
        );

        foreach ($foreignKeys as $foreignKey) {
            $child = (string) $foreignKey->child;
            $parent = (string) $foreignKey->parent;

            if ($child !== $parent && isset($parents[$child], $parents[$parent])) {
                $parents[$child][$parent] = true;
            }
        }

        $ordered = [];

        while ($parents !== []) {
            $ready = array_keys(array_filter($parents, fn (array $pending): bool => $pending === []));

            if ($ready === []) {
                throw BackupException::foreignKeyCycle();
            }

            sort($ready);

            foreach ($ready as $table) {
                $ordered[] = (string) $table;
                unset($parents[$table]);

                foreach ($parents as $child => $pending) {
                    unset($parents[$child][$table]);
                }
            }
        }

        return $ordered;
    }

    /**
     * @return list<string>
     */
    private function tableNames(): array
    {
        $rows = $this->connection()->select(
            "select table_name from information_schema.tables
             where table_schema = current_schema() and table_type = 'BASE TABLE' order by table_name",
        );

        $names = [];

        foreach ($rows as $row) {
            $names[] = (string) $row->table_name;
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function columns(string $table): array
    {
        $rows = $this->connection()->select(
            'select column_name from information_schema.columns
             where table_schema = current_schema() and table_name = ? order by ordinal_position',
            [$table],
        );

        $columns = [];

        foreach ($rows as $row) {
            $columns[] = (string) $row->column_name;
        }

        return $columns;
    }

    /**
     * ملفات الترحيلات التي كانت منفّذة عند النسخ. كلها يجب أن تكون في الشيفرة الحالية.
     *
     * @param  list<string>  $migrations
     * @return list<string>
     *
     * @throws BackupException
     */
    private function migrationFiles(array $migrations): array
    {
        $files = array_map(fn (string $name): string => database_path('migrations/'.$name.'.php'), $migrations);
        $missing = array_values(array_filter($migrations, fn (string $name): bool => ! is_file(database_path('migrations/'.$name.'.php'))));

        if ($missing !== []) {
            throw BackupException::migrationsMissing($missing);
        }

        return $files;
    }

    /**
     * سطور المحتوى بعد فك التشفير وفك الضغط، سطرًا سطرًا دون تحميل النسخة كاملة.
     *
     * @param  resource  $local
     * @return Generator<int, array<mixed>>
     *
     * @throws BackupException
     */
    private function lines($local): Generator
    {
        rewind($local);

        $reader = BackupCipher::fromConfig()->reader($local);
        $inflate = inflate_init(ZLIB_ENCODING_GZIP) ?: throw BackupException::io('بدء فك الضغط');
        $buffer = '';

        while (($chunk = $reader->read()) !== null) {
            $inflated = @inflate_add($inflate, $chunk, ZLIB_SYNC_FLUSH);

            if ($inflated === false) {
                throw BackupException::invalidContent('ضغط تالف');
            }

            $buffer .= $inflated;

            while (($newline = strpos($buffer, "\n")) !== false) {
                yield $this->decodeLine(substr($buffer, 0, $newline));
                $buffer = substr($buffer, $newline + 1);
            }
        }

        if ($buffer !== '' || inflate_get_status($inflate) !== ZLIB_STREAM_END) {
            throw BackupException::truncated();
        }
    }

    /**
     * @return array<mixed>
     *
     * @throws BackupException
     */
    private function decodeLine(string $line): array
    {
        try {
            $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw BackupException::invalidContent('سطر JSON غير صالح');
        }

        if (! is_array($decoded)) {
            throw BackupException::invalidContent('سطر JSON غير صالح');
        }

        return $decoded;
    }

    /**
     * ينزّل النسخة المشفّرة إلى ملف مؤقت محلي (تبقى مشفّرة) لقراءتها أكثر من مرة.
     *
     * @return resource
     *
     * @throws BackupException
     */
    private function download(string $path)
    {
        if (! $this->disk()->exists($path)) {
            throw BackupException::notFound($path);
        }

        $source = $this->disk()->readStream($path) ?? throw BackupException::io('تنزيل النسخة');
        $local = $this->temporaryStream();

        try {
            if (stream_copy_to_stream($source, $local) === false) {
                throw BackupException::io('تنزيل النسخة');
            }
        } catch (Throwable $exception) {
            fclose($local);

            throw $exception;
        } finally {
            fclose($source);
        }

        rewind($local);

        return $local;
    }

    /**
     * @return resource
     */
    private function temporaryStream()
    {
        return tmpfile() ?: throw BackupException::io('إنشاء ملف مؤقت');
    }

    private function createdAtFromPath(string $path): ?CarbonImmutable
    {
        $name = basename($path);

        if (! str_ends_with($name, self::EXTENSION)) {
            return null;
        }

        $createdAt = CarbonImmutable::createFromFormat(self::NAME_FORMAT, substr($name, 0, -strlen(self::EXTENSION)), 'UTC');

        return $createdAt instanceof CarbonImmutable ? $createdAt : null;
    }

    private function migrator(): Migrator
    {
        /** @var Migrator */
        return app('migrator');
    }

    private function connection(): Connection
    {
        /** @var Connection */
        return DB::connection();
    }

    private function prefix(): string
    {
        return (string) config('backup.database.prefix');
    }

    private function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter */
        return Storage::disk((string) config('backup.disk'));
    }
}
