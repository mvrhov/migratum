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

namespace Migratum\Migration;

use Migratum\Driver\DriverInterface;
use Symfony\Component\Finder\Finder;
use Twig\Environment;


class Manager
{
    public const MigrationNS = 'migratum';

    /** @var array */
    private $paths = [];
    /** @var array */
    private $pathsMigratum = [];
    /** @var DriverInterface */
    private $driver;
    /** @var Environment */
    private $twig;
    /** @var string */
    private $changelogTableName;
    /** @var bool */
    private $multiDriverMigrations;
    /** @var callable */
    private $migrationQueryCallback;
    /** @var array */
    private $migrationParameters = [];

    public function __construct(
        DriverInterface $driver,
        Environment $twig,
        string $changelogTableName,
        bool $multiDriverMigrations,
        array $migrationParameters
    ) {
        $this->driver = $driver;
        $this->twig = $twig;
        $this->changelogTableName = $changelogTableName;
        $this->multiDriverMigrations = $multiDriverMigrations;
        $this->migrationParameters = $migrationParameters;
    }

    /**
     *
     * Add migrations path
     */
    public function addPath(string $path, string $namespace = ''): self
    {
        $path = rtrim($path, '/\\') . '/';
        if (self::MigrationNS !== $namespace) {
            $this->paths[$namespace][] = $path;
        } else {
            $this->pathsMigratum[self::MigrationNS][] = $path;
        }

        return $this;
    }

    /**
     * @param string $version
     * @param bool   $dryRun
     * @param bool   $all Run all pending migrations
     *
     * @return Migration[]
     */
    public function migrate(string $version, bool $dryRun, bool $all): array
    {
        $migrations = $this->getMigrationStatus();

        if ($version < 0) {
            reset($migrations);
            end($migrations);
            $version = key($migrations);
        }
        $toRun = [];
        $bin = [];
        $pending = [];

        foreach ($migrations as $m) {
            if (null !== $m->appliedAt) {
                $pending += $bin;
                $bin = [];

                if ($m->fileChecksum != $m->checksum && $m->version > 100000) {
                    throw new \RuntimeException(
                        sprintf(
                            'The applied migration %s has a different checksum that migration on disk.' . "\n" .
                            'Old checksum %s new checksum %s',
                            $m->version,
                            $m->checksum,
                            $m->fileChecksum
                        )
                    );
                }
                continue;
            }

            $dbVersion = 'v' . $m->version;
            if ($dbVersion <= $version) {
                $bin[$dbVersion] = $m;
            }
        }

        $toRun += $bin;

        $this->initChangelogTable($dryRun);
        if ($all) {
            $toRun += $pending;
            ksort($toRun, SORT_NATURAL);
        }

        if (0 === \count($toRun)) {
            return $pending;
        }
        $this->runMigrations($toRun, $dryRun, true, $this->migrationParameters);

        return $pending;
    }

    /**
     * Merges the migrations from db with the ones in filesystem
     *
     * @return Migration[]
     */
    public function getMigrationStatus(): array
    {
        $mDb = $this->findDatabaseMigrations();
        $mAvail = $this->findAvailableMigrations();

        $migrations = [];

        foreach ($mAvail as $version => $m) {
            if (isset($mDb[$version])) {
                $m->appliedAt = $mDb[$version]->appliedAt;
                $m->checksum = $mDb[$version]->checksum;
                //should we merge the arrays instead?!
                $m->meta = $mDb[$version]->meta;
            }

            $migrations[$version] = $m;
        }

        //db might contain some migrations that are not on disk copy them over
        $dbOnly = array_diff_key($mDb, $mAvail);
        $migrations += $dbOnly;

        ksort($migrations, SORT_NATURAL);

        return $migrations;
    }

    /**
     * Find the migrations that were applied in the database
     * @return Migration[]
     */
    protected function findDatabaseMigrations(): array
    {
        if (!$this->driver->tableExists($this->changelogTableName)) {
            return [];
        }

        $dbMigrations = $this->driver->query(sprintf('SELECT * FROM %s;', $this->changelogTableName));

        if (0 === \count($dbMigrations)) {
            return [];
        }

        $migrations = [];
        foreach ($dbMigrations as $migration) {
            $m = new Migration();
            $m->version = $migration['version'];
            $m->appliedAt = $this->driver->convertToPhpDateTime($migration['applied_at']);
            $m->checksum = $migration['checksum'];
            $m->description = $migration['description'];
            $m->namespace = $migration['namespace'];
            $m->meta = json_decode($migration['metadata']);

            $migrations['v' . $m->version] = $m;
        }

        ksort($migrations, SORT_NATURAL);

        return $migrations;
    }

    /**
     * Find all migrations that are available
     *
     * @return Migration[]
     */
    protected function findAvailableMigrations(): array
    {
        $migrations = [];
        foreach ($this->getNSFinder() as $ns => $finder) {
            /** @var Finder $finder */
            foreach ($finder as $file) {
                $matches = [];
                preg_match('/^(\d+)_(.+)/', basename(basename($file->getFilename(), '.twig'), '.sql'), $matches);

                //if there is no matches, then skip this file as it's not a migration
                if (0 === \count($matches)) {
                    continue;
                }
                $m = new Migration();
                $m->version = $matches[1]; //do not cast to int as we might be running on 32bit php
                $m->description = str_replace(['_', '-'], ' ', $matches[2]);
                $m->fileChecksum = rtrim(base64_encode(hash_file('sha256', $file->getPathname(), true)), '=');
                $m->file = $file->getPathname();
                $m->namespace = $ns;

                $migrations['v' . $m->version] = $m;
            }
        }

        ksort($migrations, SORT_NATURAL);

        return $migrations;
    }

    protected function getNSFinder(): \Generator
    {
        /**
         * @var string   $ns
         * @var string[] $paths
         */
        foreach ($this->paths as $ns => $paths) {
            $finder = new Finder();

            $finder->name('*.sql.twig')
                ->name('*.sql')
                ->files()
            ;

            foreach ($paths as $path) {
                $finder->in($this->multiDriverMigrations ? $path . $this->driver->getDatabasePlatform() : $path);
            }

            yield $ns => $finder;
        }
    }

    /**
     * @throws \RuntimeException
     */
    private function initChangelogTable(bool $dryRun): void
    {
        if ($this->driver->tableExists($this->changelogTableName)) {
            return;
        }

        //store old configured settings
        $oldPaths = $this->paths;
        $oldMultiDriverMigrations = $this->multiDriverMigrations;

        //set new settings for our own migrations
        $this->paths = $this->pathsMigratum;
        $this->multiDriverMigrations = true;

        if (0 === \count($this->paths[self::MigrationNS])) {
            throw new \RuntimeException('No migratum migration path defined.');
        }

        $type = $this->driver->getDatabasePlatform();
        reset($this->paths);
        $changelogDir = current($this->paths[self::MigrationNS]) . $type;
        if (!is_dir($changelogDir)) {
            throw new \RuntimeException(sprintf('There is no changelog definitions dir "%s"', $changelogDir));
        }

        $migrations = $this->findAvailableMigrations();

        $this->runMigrations($migrations, $dryRun, true, ['table' => $this->changelogTableName]);

        //restore old settings
        $this->paths = $oldPaths;
        $this->multiDriverMigrations = $oldMultiDriverMigrations;
    }

    /**
     * @param Migration[] $migrations
     * @param bool        $dryRun
     * @param bool        $migrateUp
     * @param array       $context
     */
    private function runMigrations(array $migrations, bool $dryRun, bool $migrateUp, array $context): void
    {
        $processed = $this->preprocessMigrations($migrations, $migrateUp, $context);

        if (!$dryRun) {
            $this->driver->beginTransaction();
        }
        foreach ($processed as $version => $queries) {
            foreach ($queries as $query) {
                if (null !== ($callback = $this->migrationQueryCallback)) {
                    $callback($migrations[$version], $query, true);
                }
                if (!$dryRun) {
                    $this->driver->query($query);
                }
            }

            $sql = $this->generateInsert($migrations[$version]);
            if (null !== ($callback = $this->migrationQueryCallback)) {
                $callback($migrations[$version], $sql, true);
            }
            if (!$dryRun) {
                $this->driver->query($sql);
            }
        }
        if (!$dryRun) {
            $this->driver->commitTransaction();
        }
    }

    /**
     * @param Migration[] $migrations
     * @param bool        $migrateUp migration direction
     * @param array       $context   context to pass to the templated migration
     *
     * @return string[][]
     */
    protected function preprocessMigrations(array $migrations, bool $migrateUp, array $context): array
    {
        $processed = [];

        $startBlock = 'QueryBlockStart';
        $startLen = \strlen($startBlock);
        $endBlock = 'QueryBlockEnd';
        $endLen = \strlen($endBlock);

        foreach ($migrations as $version => $m) {
            $tplContext = $context[$version] ?? [];
            $tplContext = array_merge($tplContext, ['__migrations' => $context]);
            $twigName = ('' !== $m->namespace) ? '@' . $m->namespace . '/' : '';
            $twigName .= $this->multiDriverMigrations ? $this->driver->getDatabasePlatform() . '/' : '';
            $twigName .= basename($m->file);

            $tpl = $this->twig->loadTemplate($twigName);
            //copy description from the block
            if ($tpl->hasBlock('description', $tplContext)) {
                $description = trim($tpl->renderBlock('description', $tplContext));
                if (!empty($description)) {
                    $m->description = $description;
                }
            }

            $block = $tpl->renderBlock($migrateUp ? 'up' : 'down', $tplContext);
            $sqlBlocks = preg_split('/--\s?@Migratum\\\\/m', $block, -1, PREG_SPLIT_NO_EMPTY);
            if (false === $sqlBlocks) {
                throw new \RuntimeException('There was an error while trying to splitt the SQL');
            }
            $processed[$version] = [];
            foreach ($sqlBlocks as $block) {
                $inStartBlock = (0 === strpos($block, $startBlock));
                if (0 === strpos($block, $endBlock)) {
                    $block = trim(substr($block, $endLen));
                    if ('' === $block) {
                        continue;
                    }
                }
                if ($inStartBlock) {
                    $processed[$version][] = trim(substr($block, $startLen));
                } else {
                    $processed[$version] = array_merge(
                        $processed[$version],
                        array_map('trim', \SqlFormatter::splitQuery($block))
                    );
                }
            }
        }

        return $processed;
    }

    private function generateInsert(Migration $migration)
    {
        $values = [
            'version' => $this->driver->escapeString($migration->version),
            'applied_at' => $this->driver->escapeString($this->driver->convertToDbDateTime(new \DateTime())),
            'checksum' => $this->driver->escapeString($migration->fileChecksum),
            'description' => $this->driver->escapeString($migration->description),
            'namespace' => $this->driver->escapeString($migration->namespace),
            'metadata' => $this->driver->escapeString(json_encode($migration->meta)),
        ];

        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s);',
            $this->changelogTableName,
            implode(', ', array_keys($values)),
            implode(', ', array_values($values))
        );
    }

    /**
     * @param string $version
     * @param bool   $dryRun
     *
     * @return Migration[]
     */
    public function rollback(string $version, bool $dryRun): array
    {
        $migrations = $this->getMigrationStatus();
        krsort($migrations, SORT_NATURAL);

        if ($version < 0) {
            $goBackCount = -1 * $version;
            $version = 'v0';
        } else {
            $version = 'v' . $version;
        }
        $toRun = [];

        foreach ($migrations as $m) {
            if (null === $m->appliedAt) {
                continue;
            }

            $dbVersion = 'v' . $m->version;
            if ($dbVersion >= $version && $dbVersion > 'v10000') {
                $toRun[$dbVersion] = $m;
            }
        }

        if (0 === \count($toRun)) {
            return [];
        }

        if (isset($goBackCount)) {
            $toRun = \array_slice($toRun, 0, $goBackCount, true);
        }

        $this->runMigrations($toRun, $dryRun, false, $this->migrationParameters);

        return [];
    }

    /**
     * This gets called before each query from a migration is executed
     * The callback signature is (Migration $migration, string $query, bool $migratingUp)
     *
     * @param callable $callback
     *
     * @return Manager
     */
    public function setMigrationQueryCallback(callable $callback): self
    {
        $this->migrationQueryCallback = $callback;

        return $this;
    }

    public function getDatabaseName(): string
    {
        return $this->driver->getDatabaseName();
    }
}
