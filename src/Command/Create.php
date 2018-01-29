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

use Migratum\Config\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Create extends Command
{

    protected static $defaultName = 'migratum:create';

    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Create empty migration')
            ->addArgument('name', InputArgument::REQUIRED, 'Migration name.')
            ->addOption(
                'namespace',
                '-s',
                InputOption::VALUE_REQUIRED,
                'The namespace where migration will be created.'
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');

        if (empty($name)) {
            $output->writeln('<error>Please provide non empty migration name.</error>');

            return 1;
        }

        //replace invalid characters with _
        $name = preg_replace('/[\*\\:<>\?\/"\s]/', '_', $name);
        //replace multiple occupancies of _ with one
        $name = preg_replace('/_+/', '_', $name);

        $fn = (new \DateTime('now', new \DateTimeZone('UTC')))->format('YmdHis') . '_' . $name . '.sql.twig';

        $paths = [];
        foreach ($this->config->getPaths() as $path => $namespace) {
            $paths[$namespace][] = $path;
        }

        $namespace = $input->getOption('namespace') ?? '';
        if (false !== ($break = strrpos($namespace, '.'))) {
            $index = (int)substr($namespace, $break + 1);
            $namespace = substr($namespace, 0, $break);
        } else {
            $index = -1;
        }

        if (!isset($paths[$namespace])) {
            $output->writeln(sprintf('<error>Namespace \'%s\' is not configured.</error>', $namespace));

            return 1;
        }

        if (\count($paths[$namespace]) > 1) {
            if ($index < 0) {
                $output->writeln(
                    sprintf('<error>There is more than one path configured under namespace \'%s\'.</error>', $namespace)
                );
                $output->writeln(
                    '<info>Please use the namespace from list bellow to select proper path under namespace.</info>'
                );
                $table = new Table($output);
                $table->setHeaders(['Namespace', 'Path']);

                $index = 0;
                foreach ($paths[$namespace] as $path) {
                    $table->addRow([$namespace . '.' . $index, $path]);
                    $index++;
                }

                $table->render();

                return 1;
            }
            if (!isset($paths[$namespace][$index])) {
                $output->writeln(
                    sprintf(
                        '<error>Path with index \'%d\' in namespace \'%s\' is not configured.</error>',
                        $index,
                        $namespace
                    )
                );

                return 1;
            }
        }

        $path = $paths[$namespace][max($index, 0)];
        if ($this->config->hasMultiDriverMigrations()) {
            $class = $this->config->getDatabaseDriverClass();
            $path .= $class::getDatabasePlatform() . '/';
        }
        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            $output->writeln(
                sprintf(
                    '<error>Unable to create directory \'%s\' for migrations.</error>',
                    $paths[$namespace][$index]
                )
            );

            return 1;
        }

        if (!copy(dirname(__DIR__) . '/Resources/template.sql.twig', $path . $fn)) {
            $output->writeln(
                sprintf(
                    '<info>There was an error while trying to create migration \'%s\' in \'%s\'.</info>',
                    $fn,
                    $path
                )
            );

            return 1;
        }

        $output->writeln(
            sprintf(
                '<info>Created migration \'%s\' in \'%s\'.</info>',
                $fn,
                $path
            )
        );

        return 0;
    }

}
