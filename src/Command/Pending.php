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

namespace Migratum\Command;

use Migratum\Migration\Manager;
use Migratum\Migration\Migration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Pending extends Command
{

    protected static $defaultName = 'migratum:pending';

    /** @var Manager */
    private $manager;

    public function __construct(Manager $manager)
    {
        $this->manager = $manager;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Run all pending migrations')
            ->addOption('dry-run', '', InputOption::VALUE_NONE, 'Show which migrations are about to be applied')
            ->addOption('force', '', InputOption::VALUE_NONE, 'Confirm the run of pending migrations')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $force = $input->getOption('force') ?? false;
        $dryRun = $input->getOption('dry-run') ?? false;

        if (!$force) {
            $output->writeln(
                '<info>Please review pending migrations first, and if they don\'t conflict with already applied ones rerun this command with --force option.</info>'
            );

            return 0;
        }

        $verbose = OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity();

        $lastMigrationVersion = null;
        $count = 0;
        $callback = function (Migration $migration, string $query, bool $migratingUp) use (
            $output,
            $verbose,
            &$lastMigrationVersion,
            &$count
        ) {
            if ($lastMigrationVersion === $migration->version) {
                if ($verbose) {
                    $output->writeln('');
                    $output->writeln(trim($query));
                } else {
                    $output->write('<comment>.</comment>');
                }

                return;
            }
            $count++;
            if (null !== $lastMigrationVersion) {
                $output->writeln('');
            }

            $lastMigrationVersion = $migration->version;
            if ('' !== $migration->namespace) {
                $output->write(
                    sprintf(
                        'Applying <info>%s @ %s</info> %s ',
                        $migration->namespace,
                        $migration->version,
                        $migration->description
                    )
                );
            } else {
                $output->write(sprintf('Applying <info>%s</info> %s ', $migration->version, $migration->description));
            }

            if ($verbose) {
                $output->writeln('');
                $output->writeln(trim($query));
            }
        };

        $this->manager->setMigrationQueryCallback($callback);
        if ($dryRun) {
            $output->writeln(
                sprintf(
                    '<info>Applying migrations on database \'%s\'</info> <comment>in dry run mode</comment>!',
                    $this->manager->getDatabaseName()
                )
            );
        } else {
            $output->writeln(
                sprintf('<info>Applying migrations on database \'%s\'...</info>', $this->manager->getDatabaseName())
            );
        }

        try {
            $this->manager->migrate('-1', $dryRun, true);
        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln('<error>' . trim($e->getMessage()) . '</error>');
            $output->writeln('<info>Migration failed</info>');
        }

        $output->writeln('');

        if ($count > 0) {
            $output->writeln(
                sprintf('<info>Applied %d migrations.</info>', $count)
            );
        } else {
            $output->writeln('<info>No migrations were applied, because your database is already up to date.</info>');
        }

        return 0;
    }

}
