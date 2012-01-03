<?php

/*
 * This file is part of the rfc2epub package
 *
 * (c) Justin Rainbow <justin.rainbow@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Rfc2ePub\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Finder\Finder;
use Rfc2ePub\Command;

/**
 * The console application that handles the commands
 *
 * @author Justin Rainbow <justin.rainbow@gmail.com>
 */
class Application extends BaseApplication
{
    protected $composer;

    public function __construct()
    {
        parent::__construct('RFC 2 ePub', '0.1.0-dev');
    }
    /**
     * {@inheritDoc}
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->registerCommands();

        return parent::doRun($input, $output);
    }

    public function locateResource($name)
    {
        $resources = __DIR__.'/../Resources';

        $file = $resources . '/' . $name;

        return $file;
    }

    /**
     * Initializes all the composer commands
     */
    protected function registerCommands()
    {
        $this->add(new Command\CreateCommand());

        if ('phar:' === substr(__FILE__, 0, 5)) {
            $this->add(new Command\SelfUpdateCommand());
        }
    }
}
