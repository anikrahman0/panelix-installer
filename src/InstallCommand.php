<?php

namespace Panelix\Installer;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    protected static $defaultName = 'new';

    // Configure method should have : void return type
    protected function configure(): void
    {
        $this
            ->setDescription('Create a new Panelix Laravel 12 project')
            ->addArgument('name', InputArgument::OPTIONAL, 'The directory name for the new project', 'panelix-app');
    }

    // Execute method must return int
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = $input->getArgument('name');

        $output->writeln("<info>🚀 Installing Panelix Dashboard into {$directory}...</info>");

        // Clone from GitHub repo
        $process = Process::fromShellCommandline("git clone https://github.com/anikrahman/panelix {$directory}");
        $process->setTimeout(null);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $output->writeln('<error>Failed to clone Panelix repository</error>');
            return Command::FAILURE; // return int
        }

        $output->writeln("<info>✅ Panelix Project is ready in ./$directory</info>");
        $output->writeln('');
        $output->writeln('<comment>Next steps:</comment>');
        $output->writeln("cd {$directory}");
        $output->writeln('composer install');
        $output->writeln('cp .env.example .env');
        $output->writeln('php artisan key:generate');
        $output->writeln('php artisan migrate --seed');
        $output->writeln('php artisan serve');

        return Command::SUCCESS; // return int
    }
}
