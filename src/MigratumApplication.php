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

namespace Migratum;

use Migratum\Command\Init;
use Migratum\Config\Config;
use Migratum\Util\Container;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * short description
 *
 * @author Miha Vrhovnik <miha.vrhovnik@gmail.com>
 *
 */
class MigratumApplication extends Application
{
    private const VERSION = '0.5';

    public function __construct()
    {
        parent::__construct('Migratum', self::VERSION);

        $this->addCommands([new Init()]);
    }

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        try {
            $config = $this->getConfig($input, $output);
        } catch (InvalidArgumentException $e) {
            return 1;
        }

        if (null !== $config) {
            $c = new Container($config);

            if (true === $input->hasParameterOption(['--environment', '-e'], true)) {
                $environment = $input->getParameterOption(['--environment', '-e'], false);

                try {
                    $config->getEnvironment($environment);
                } catch (\RuntimeException $e) {
                    $output->writeln(sprintf('<error>Environment \'%s\' does not exist.</error>', $environment));

                    return 1;
                }
            } else {
                if (null === ($environment = $config->getDefault())) {
                    $output->writeln(
                        '<error>Please specify environment to use with -e parameter or set a default one in your config file.</error>'
                    );

                    return 1;
                }
                $environment = $environment->getName();
            }

            $c->build($environment);

            $this->setCommandLoader($c->getCommandLoader());
        }

        $result = parent::doRun($input, $output);

        if ((null === $config) && ('migratum:init' !== $input->getArgument('command'))) {
            $output->writeln('<error>Unable to find the configuration file.</error>');
            $output->writeln('<info>Please run migratum:init to create one.</info>');
        }

        return $result;
    }

    protected function getConfig(InputInterface $input, OutputInterface $output): ?Config
    {
        $configPath = false;
        if (true === $input->hasParameterOption(['--configuration', '-c'], true)) {
            $configPath = $input->getParameterOption(['--configuration', '-c'], false);
            if (!is_file($configPath)) {
                $output->writeln(sprintf('<error>The configuration file "%s" not found.</error>', $configPath));

                throw new InvalidArgumentException();
            }
        } else {
            $dir = __DIR__;
            $found = true;
            while (!is_file($dir . '/migratum.php')) {
                if ($dir === dirname($dir)) {
                    $found = false;
                    break;
                }
                $dir = dirname($dir);
            }
            if ($found) {
                $configPath = $dir . '/migratum.php';
            }
        }

        if (false !== $configPath) {
            $config = new Config();
            $f = require $configPath;
            $f($config);

            return $config;
        }

        return null;
    }

    protected function getDefaultInputDefinition(): InputDefinition
    {
        $definitions = parent::getDefaultInputDefinition();
        $definitions->addOption(
            new InputOption('configuration', 'c', InputOption::VALUE_REQUIRED, 'Configuration file')
        );
        $definitions->addOption(
            new InputOption('environment', 'e', InputOption::VALUE_REQUIRED, 'Environment to use')
        );

        return $definitions;
    }

}
