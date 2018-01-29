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


interface DriverInterface
{
    public const PLATFORM_POSTGRESQL = 'postgresql';

    /**
     * Returns database type one of DriverInterface::PLATFORM_
     */
    public static function getDatabasePlatform(): string;

    /**
     * Establish database connection
     */
    public function connect();

    /**
     * Terminate database connection
     */
    public function disconnect(): void;

    /**
     * Start transaction
     */
    public function beginTransaction(): void;

    /**
     * Commit transaction
     */
    public function commitTransaction(): void;

    /**
     * Rollback transaction
     */
    public function rollbackTransaction(): void;

    /**
     * @param string $query
     *
     * @return array associative array with all rows
     */
    public function query(string $query): array;

    /**
     * Checks if given table exists
     */
    public function tableExists(string $tableName): bool;

    /**
     * Returns the name of database that we will connect to
     */
    public function getDatabaseName(): string;

    /**
     * Converts database value to php value
     *
     * @param null|string $dbValue
     *
     * @return \DateTimeImmutable|null
     */
    public function convertToPhpDateTime(?string $dbValue): ?\DateTimeImmutable;

    /**
     * Converts php date time to database date time
     *
     * @param \DateTimeInterface $phpValue
     *
     * @return string
     */
    public function convertToDbDateTime(\DateTimeInterface $phpValue): string;

    /**
     * Escape the field content
     */
    public function escapeString(string $value): string;

    /**
     * Escape the table/field name
     */
    public function escapeIdentifier(string $value): string;
}