<?php

namespace Panelix\Installer;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    // Remove static $defaultName
    // protected static $defaultName = 'new';

    protected function configure(): void
    {
        $this
            ->setName('new') // <-- Explicitly set the command name
            ->setDescription('Create a new Panelix Laravel 12 project')
            ->addArgument('name', InputArgument::OPTIONAL, 'The directory name for the new project', 'panelix-app');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = $input->getArgument('name');

        $output->writeln("<info>🚀 Installing Panelix Dashboard into {$directory}...</info>");

        $process = $process = Process::fromShellCommandline(
            "git clone --depth 1 https://github.com/anikrahman0/Panelix {$directory} && rd /s /q {$directory}\\.git"
        );

        $process->setTimeout(null);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $output->writeln('<error>Failed to clone Panelix repository</error>');
            return Command::FAILURE;
        }

        $output->writeln("<info>✅ Panelix Project is ready in ./$directory</info>");
        $output->writeln('');
        $output->writeln('<comment>Next steps:</comment>');
        $output->writeln("cd {$directory}");
        $output->writeln('composer install --no-scripts');
        $output->writeln('cp .env.example .env');
        $output->writeln('php artisan key:generate');
        $output->writeln('php artisan migrate --seed');
        $output->writeln('php artisan serve');

        return Command::SUCCESS;
    }
}
