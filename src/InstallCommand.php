<?php

namespace Panelix\Installer;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('new')
            ->setDescription('Create a new Panelix Laravel 12 project with database setup')
            ->addArgument('name', InputArgument::OPTIONAL, 'The directory name for the new project', 'panelix-app');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = $input->getArgument('name');

        $output->writeln("<info>🚀 Installing Panelix Dashboard into {$directory}...</info>");

        // Step 1: Clone the repo without .git
        $process = Process::fromShellCommandline(
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

        // Step 2: Create database
        $output->writeln("<info>📦 Creating database: panelix_db...</info>");
        $createDb = Process::fromShellCommandline('mysql -u root -e "CREATE DATABASE IF NOT EXISTS panelix_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"');
        $createDb->run();

        if (!$createDb->isSuccessful()) {
            $output->writeln('<error>❌ Failed to create database. Please check MySQL credentials.</error>');
            return Command::FAILURE;
        }

        // Step 3: Update .env file with DB credentials
        copy("{$directory}/.env.example", "{$directory}/.env");
        $envPath = "{$directory}/.env";
        $envContent = file_get_contents($envPath);
        $envContent = preg_replace('/DB_DATABASE=.*/', 'DB_DATABASE=panelix_db', $envContent);
        $envContent = preg_replace('/DB_USERNAME=.*/', 'DB_USERNAME=root', $envContent);
        $envContent = preg_replace('/DB_PASSWORD=.*/', 'DB_PASSWORD=', $envContent);
        file_put_contents($envPath, $envContent);

        // Step 4: Run composer install without scripts
        $output->writeln("<info>⚙️ Installing dependencies...</info>");
        $composer = Process::fromShellCommandline("cd {$directory} && composer install --no-scripts");
        $composer->setTimeout(null);
        $composer->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        // Step 5: Run migrations + seed
        $artisan = Process::fromShellCommandline("cd {$directory} && php artisan migrate --seed");
        $artisan->setTimeout(null);
        $artisan->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        // Step 6: Start Laravel server
        $output->writeln("<info>🚀 Starting Laravel development server...</info>");
        $serve = new Process(["php", "artisan", "serve"], $directory);
        $serve->setTimeout(null);
        $serve->start();

        $output->writeln("<info>✅ Panelix Project is ready!</info>");
        $output->writeln("👉 Serving at: http://127.0.0.1:8000");
        $output->writeln("👉 Project directory: {$directory}");

        return Command::SUCCESS;
    }
}
