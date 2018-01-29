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

namespace Migratum\Util;

use League\Container\Argument\RawArgument;
use Migratum\Command\Create;
use Migratum\Command\Migrate;
use Migratum\Command\Pending;
use Migratum\Command\Rollback;
use Migratum\Command\Status;
use Migratum\Config\Config;
use Migratum\Config\Environment;
use Migratum\Migration\Manager;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Twig\Loader\FilesystemLoader;


/**
 * short description
 *
 * @author Miha Vrhovnik <miha.vrhovnik@gmail.com>
 *
 */
class Container
{
    /** @var Config */
    private $config;
    /** @var Environment */
    private $environment;
    /** @var \League\Container\Container */
    private $container;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->container = new \League\Container\Container();
    }

    public function build(string $environmentName)
    {
        $this->container->add('config', $this->config);
        $this->environment = $this->config->getEnvironment($environmentName);

        $this->wireTwig();
        $this->wireDbDriver();
        $this->wireManager();
        $this->wireCommands();
    }

    protected function wireTwig(): void
    {
        $loader = $this->container->add('twig.loader', FilesystemLoader::class);

        foreach ($this->environment->getPaths() as $path => $namespace) {
            $loader->addMethodCall(
                'addPath',
                [
                    new RawArgument($path),
                    new RawArgument($namespace === '' ? FilesystemLoader::MAIN_NAMESPACE : $namespace),
                ]
            );
        }

        //add migratum's own namespace path
        $loader->addMethodCall(
            'addPath',
            [
                new RawArgument(dirname(__DIR__) . '/Resources/migrations'),
                new RawArgument(Manager::MigrationNS),
            ]
        );

        $this->container->add('twig.environment', \Twig\Environment::class)
            ->addArgument('twig.loader')
            ->addArgument(new RawArgument(['debug' => $this->environment->isDebug()]))
        ;
    }

    protected function wireDbDriver(): void
    {
        $database = $this->container->add('database', $this->environment->getDatabaseDriverClass());
        $options = $this->environment->getDriverOptions();

        $database->addArguments(
            [
                new RawArgument($options->getHostname()),
                new RawArgument($options->getDatabase()),
                new RawArgument($options->getUsername()),
                new RawArgument($options->getPassword()),
                new RawArgument($options->getPort()),
                new RawArgument($options->getSchema()),
                new RawArgument($options->getOptions()),
            ]
        );
    }

    protected function wireManager(): void
    {
        $manager = $this->container->add('manager', Manager::class);
        $manager->addArgument('database');
        $manager->addArgument('twig.environment');
        $manager->addArgument(
            new RawArgument(
                empty($this->environment->getChangelogTableName()) ? 'db_changelog' : $this->environment->getChangelogTableName()
            )
        );
        $manager->addArgument(new RawArgument($this->environment->hasMultiDriverMigrations()));
        $manager->addArgument(new RawArgument($this->environment->getMigrationParameters()));

        $path = dirname(__DIR__) . '/Resources/migrations';

        //add migratum namespace into twig namespace
        $manager->addMethodCall(
            'addPath',
            [
                new RawArgument($path),
                new RawArgument(Manager::MigrationNS),
            ]
        );

        foreach ($this->environment->getPaths() as $path => $namespace) {
            $manager->addMethodCall(
                'addPath',
                [
                    new RawArgument($path),
                    new RawArgument($namespace),
                ]
            );
        }
    }

    protected function wireCommands(): void
    {
        $commandMap = [];

        $commandMap[Create::getDefaultName()] = Create::class;
        $this->container->add(Create::class)
            ->addArgument('config')
        ;

        $commandMap[Migrate::getDefaultName()] = Migrate::class;
        $this->container->add(Migrate::class)
            ->addArgument('manager')
        ;

        $commandMap[Rollback::getDefaultName()] = Rollback::class;
        $this->container->add(Rollback::class)
            ->addArgument('manager')
        ;

        $commandMap[Status::getDefaultName()] = Status::class;
        $this->container->add(Status::class)
            ->addArgument('manager')
        ;

        $commandMap[Pending::getDefaultName()] = Pending::class;
        $this->container->add(Pending::class)
            ->addArgument('manager')
        ;


        $this->container->add('command.loader', ContainerCommandLoader::class)
            ->addArgument(new RawArgument($this->container))
            ->addArgument(new RawArgument($commandMap))
        ;
    }

    public function getCommandLoader(): CommandLoaderInterface
    {
        return $this->container->get('command.loader');
    }
}
