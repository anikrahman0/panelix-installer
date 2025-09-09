<?php

namespace Panelix\Installer;

use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{
    public function __construct()
    {
        parent::__construct('Panelix Installer', '1.0.0');

        $this->add(new InstallCommand());
    }
}
