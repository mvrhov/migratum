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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

class Status extends Command
{

    protected static $defaultName = 'migratum:status';

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
            ->setDescription('Show migration status');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $migrations = $this->manager->getMigrationStatus();

        if (0 === \count($migrations)) {
            $output->writeln(
                sprintf('<info>You don\'t have any migrations on %s yet.</info>', $this->manager->getDatabaseName())
            );

            return 0;
        }

        $output->writeln(sprintf('<info>Status of migrations on \'%s\'.</info>', $this->manager->getDatabaseName()));

        $table = new Table($output);
        $table->setHeaders(['Version', 'Applied at', 'Description']);

        $width = (new Terminal())->getWidth();
        //we optimize this for standard 80 character terminal width
        $descLen = max($width - 43, 37); /* 18 + 22 + 3*/
        $checksumMismatch = [];

        foreach ($migrations as $m) {
            $isPending = (null === $m->appliedAt);
            $versionError = !$isPending && ($m->fileChecksum !== $m->checksum) && ($m->version > 100000);
            if ($versionError) {
                $checksumMismatch[] = $m;
            }

            $table->addRow(
                [
                    $versionError ? '<error>' . $m->version . '</error>' : $m->version,
                    $isPending ? '...pending...' : $m->appliedAt->format('Y-m-d H:i:s'),
                    \strlen($m->description) > $descLen ? substr($m->description, 0, $descLen - 3) . '...' :
                        $m->description,
                ]
            );
        }

        $table->render();

        if (\count($checksumMismatch) > 0) {
            $output->writeln(
                '<info>The migrations cannot be run because at least one migration on disk is different from the migration already applied.</info>'
            );
            $output->writeln('<info>The problematic migrations are.</info>');

            $table = new Table($output);
            $table->setHeaders(['Version', 'Old checksum', 'New checksum']);

            foreach ($checksumMismatch as $m) {
                $table->addRow([$m->version, $m->checksum, $m->fileChecksum]);
            }

            $table->render();
        }

        return 0;
    }

}
