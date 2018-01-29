<?php declare(strict_types=1);
/**
 * Released under the MIT License.
 *
 * Copyright (c) 2018 Miha Vrhovnik <miha.vrhovnik@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace Migratum\Driver;


class Postgresql implements DriverInterface
{
    /** @var string */
    protected $connectionString;
    /** @var string */
    protected $schema;
    /** @var resource|null */
    protected $connection;
    /** @var string */
    protected $database;

    public function __construct(
        string $host,
        string $dbName,
        string $user,
        string $password,
        int $port = 5432,
        string $schema = '',
        array $options = []
    ) {
        $this->connectionString = "host={$host} port={$port} dbname={$dbName} user={$user} password={$password}";
        if (count($options)) {
            $this->connectionString .= ' options=\'' . implode(' ', $options) . '\'';
        }
        $this->schema = '' === $schema ? 'public' : $schema;
        $this->database = $dbName;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): void
    {
        $this->query('START TRANSACTION');
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $query): array
    {
        if ((null === $this->connection) && (false === pg_ping($this->connection))) {
            $this->connect();
        }

        if (!pg_send_query($this->connection, $query)) {
            throw new DriverException(pg_last_error($this->connection));
        }

        $resource = pg_get_result($this->connection);

        if ($resource === false) {
            throw new DriverException(pg_last_error($this->connection));
        }
        $state = pg_result_error_field($resource, PGSQL_DIAG_SQLSTATE);
        if ($state !== null) {
            throw new DriverException(pg_result_error($resource), $state);
        }

        $result = pg_fetch_all($resource);
        pg_free_result($resource);

        return \is_array($result) ? $result : [];
    }

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        $connection = pg_connect($this->connectionString, PGSQL_CONNECT_FORCE_NEW);

        if (false === $connection) {
            throw new DriverException('Unable to establish connection.');
        }

        $resource = pg_query($connection, sprintf('SET search_path TO %s', $this->schema));
        if (false === $resource) {
            $error = pg_last_error($connection);
            throw new DriverException(
                sprintf('Unable to set search path to %s. The error was %s', $this->schema, $error)
            );
        }

        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function commitTransaction(): void
    {
        $this->query('COMMIT');
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDbDateTime(\DateTimeInterface $phpValue): string
    {
        return $phpValue->format('Y-m-d H:i:s');
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPhpDateTime(?string $dbValue): ?\DateTimeImmutable
    {
        if (null === $dbValue) {
            return null;
        }

        return new \DateTimeImmutable($dbValue);
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect(): void
    {
        if (null !== $this->connection) {
            pg_close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function escapeIdentifier(string $value): string
    {
        if ((null === $this->connection) && (false === pg_ping($this->connection))) {
            $this->connect();
        }

        return pg_escape_identifier($this->connection, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function escapeString(string $value): string
    {
        if ((null === $this->connection) && (false === pg_ping($this->connection))) {
            $this->connect();
        }

        return pg_escape_literal($this->connection, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabaseName(): string
    {
        return $this->database;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDatabasePlatform(): string
    {
        return self::PLATFORM_POSTGRESQL;
    }

    /**
     * {@inheritdoc}
     */
    public function rollbackTransaction(): void
    {
        $this->query('ROLLBACK');
    }

    /**
     * {@inheritdoc}
     */
    public function tableExists(string $tableName): bool
    {
        if ((null === $this->connection) && (false === pg_ping($this->connection))) {
            $this->connect();
        }

        $result = $this->query(
            sprintf(
                'SELECT COUNT(*) AS tables FROM pg_tables WHERE tablename = %s',
                pg_escape_literal($this->connection, $tableName)
            )
        );

        return isset($result[0]['tables']) && ($result[0]['tables'] > 0);
    }
}
