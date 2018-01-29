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

namespace Migratum\Config;

use Migratum\Driver\DriverInterface;


/**
 * short description
 *
 * @author Miha Vrhovnik <miha.vrhovnik@gmail.com>
 *
 */
class Environment
{
    /** @var string */
    private $name;

    /** @var string */
    private $changelogTableName;

    /** @var array */
    private $paths = [];

    /** @var bool */
    private $debug;

    /** @var bool */
    private $multiDriverMigrations;

    /** @var string */
    private $databaseDriverClass;

    /** @var DriverOptions */
    private $driverOptions;

    /** @var array */
    private $migrationParameters = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function setDatabaseDriver(string $className): self
    {
        $r = new \ReflectionClass($className);
        if (!$r->implementsInterface(DriverInterface::class)) {
            throw new \InvalidArgumentException(
                sprintf('"%s" doesn\'t implement the "%s"', $className, DriverInterface::class)
            );
        }

        $this->databaseDriverClass = $className;

        return $this;
    }

    public function getDatabaseDriverClass(): string
    {
        return $this->databaseDriverClass;
    }

    public function getDriverOptions(): DriverOptions
    {
        return $this->driverOptions;
    }

    public function setDriverOptions(DriverOptions $driverOptions): self
    {
        $this->driverOptions = $driverOptions;

        return $this;
    }

    /**
     * Add migration paths
     */
    public function addPath(string $path, string $namespace = ''): self
    {
        $path = rtrim($path, '/\\') . '/';
        $this->paths[$path] = $namespace;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    public function getChangelogTableName(): string
    {
        return $this->changelogTableName ?? 'db_changelog';
    }

    public function setChangelogTableName(string $tableName): self
    {
        $this->changelogTableName = $tableName;

        return $this;
    }

    public function isDebug(): bool
    {
        return $this->debug ?? false;
    }

    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * Set this to true if you have migrations for multiple different databases e.g.
     * mysql, postgresql. This means that migratum will automatically add database type into the search path
     */
    public function setMultiDriverMigrations(bool $multiDriverMigrations): self
    {
        $this->multiDriverMigrations = $multiDriverMigrations;

        return $this;
    }

    public function hasMultiDriverMigrations(): bool
    {
        return $this->multiDriverMigrations ?? false;
    }

    /**
     * Returns the data that is passed into the migration
     */
    public function getMigrationParameters(): array
    {
        return $this->migrationParameters;
    }

    /**
     * Set the additional data for the migrations
     */
    public function setMigrationParameters(array $migrationParameters): self
    {
        $this->migrationParameters = $migrationParameters;

        return $this;
    }

    /**
     * Returns environment name
     */
    public function getName(): string
    {
        return $this->name;
    }

}
