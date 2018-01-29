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

class Rollback extends Command
{

    protected static $defaultName = 'migratum:rollback';

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
            ->setDescription('Rollback migrations')
            ->addOption('dry-run', '', InputOption::VALUE_NONE, 'Show which migrations are about to be reverted')
            ->addOption(
                'target',
                't',
                InputOption::VALUE_REQUIRED,
                'The version number to revert to.'
            )
            ->addOption(
                'back',
                'b',
                InputOption::VALUE_REQUIRED,
                'How many versions back you want to go.'
            );

        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $target = $input->getOption('target');
        $back = $input->getOption('back');
        $dryRun = $input->getOption('dry-run') ?? false;
        $verbose = OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity();

        if ((null === $target) && (null === $back)) {
            $output->writeln('<error>Please specify the version you want to rollback to.</error>');

            return 0;
        }
        if ((null !== $target) && (null !== $back)) {
            $output->writeln('<error>You can specify either \'target\' or \'back\' option.</error>');

            return 0;
        }

        if (null !== $back) {
            $target = (string) (-1 * $back);
        }

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
                        'Reverting <info>%s @ %s</info> %s ',
                        $migration->namespace,
                        $migration->version,
                        $migration->description
                    )
                );
            } else {
                $output->write(sprintf('Reverting <info>%s</info> %s ', $migration->version, $migration->description));
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
                    '<info>Reverting migrations on database \'%s\'</info> <comment>in dry run mode</comment>!',
                    $this->manager->getDatabaseName()
                )
            );
        } else {
            $output->writeln(
                sprintf('<info>Reverting migrations on database \'%s\'...</info>', $this->manager->getDatabaseName())
            );
        }

        try {
            $this->manager->rollback($target, $dryRun);
        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln('<error>' . trim($e->getMessage()) . '</error>');
            $output->writeln('<info>Migration failed</info>');
        }

        $output->writeln('');

        if ($count > 0) {
            $output->writeln(
                sprintf('<info>Reverted %d migrations.</info>', $count)
            );
        } else {
            $output->writeln('<info>No migrations were reverted.</info>');
        }

        return 0;

    }

}
