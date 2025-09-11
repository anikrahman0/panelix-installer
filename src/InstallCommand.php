<?php

namespace Panelix\Installer;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    private bool $dbCreated = false; // track DB creation
    private string $databaseName = 'panelix_db'; // default DB name

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

        // Step 0: Ask user for database name
        $helper = $this->getHelper('question');
        $question = new Question("Enter database name (default: panelix_db): ", 'panelix_db');
        $this->databaseName = $helper->ask($input, $output, $question);

        $output->writeln("<info>🚀 Installing Panelix Dashboard into {$directory}...</info>");
        $output->writeln("<info>📦 Using database: {$this->databaseName}</info>");

        // Step 1: Clone the repo without .git
        $process = Process::fromShellCommandline(
            "git clone --depth 1 https://github.com/anikrahman0/Panelix {$directory} && rd /s /q {$directory}\\.git"
        );
        $process->setTimeout(null);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $output->writeln('<error>❌ Failed to clone Panelix repository</error>');
            return Command::FAILURE;
        }

        // Step 2: Try to create database (skip on failure)
        $output->writeln("<info>📦 Attempting to create database: {$this->databaseName}...</info>");
        $checkMysql = new Process(['mysql', '--version']);
        $checkMysql->run();

        if ($checkMysql->isSuccessful()) {
            $createDb = Process::fromShellCommandline(
                'mysql -u root -e "CREATE DATABASE IF NOT EXISTS ' . $this->databaseName . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"'
            );
            $createDb->run();

            if ($createDb->isSuccessful()) {
                $this->dbCreated = true;
                $output->writeln("<info>✅ Database \"{$this->databaseName}\" created or already exists.</info>");
            } else {
                $output->writeln('<comment>⚠️ Could not create database automatically. You may need to create it manually later.</comment>');
            }
        } else {
            $output->writeln('<comment>⚠️ MySQL not detected. Please install MySQL and create "' . $this->databaseName . '" manually.</comment>');
        }

        // Step 3: Update .env file with DB credentials
        copy("{$directory}/.env.example", "{$directory}/.env");
        $envPath = "{$directory}/.env";
        $envContent = file_get_contents($envPath);
        $envContent = preg_replace('/DB_DATABASE=.*/', 'DB_DATABASE=' . $this->databaseName, $envContent);
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

        // Step 5: Run migrations + seed (only if DB was created successfully)
        if ($this->dbCreated) {
            $output->writeln("<info>📂 Running migrations and seeders...</info>");
            $artisan = Process::fromShellCommandline("cd {$directory} && php artisan migrate --seed");
            $artisan->setTimeout(null);
            $artisan->run(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });
        }

        // Step 6: Start Laravel server
        $host = '127.0.0.1';
        $port = 8000;
        $output->writeln("<info>🚀 Starting Laravel development server...</info>");
        $serve = new Process(["php", "artisan", "serve", "--host={$host}", "--port={$port}"], $directory);
        $serve->setTimeout(null);
        $serve->start();

        // Final instructions
        $output->writeln("<info>✅ Panelix Project is ready!</info>");
        $output->writeln("👉 Serving at: http://{$host}:{$port}");
        $output->writeln("👉 Project directory: {$directory}");
        $output->writeln("👉 Database: {$this->databaseName}");

        // Step 7: Database instructions if not created
        if (!$this->dbCreated) {
            $output->writeln('');
            $output->writeln('<comment>⚠️ Database was not created automatically.</comment>');
            $output->writeln("<comment>➡️ Please create it manually: CREATE DATABASE {$this->databaseName};</comment>");
            $output->writeln('<comment>   (You can also use phpMyAdmin or any MySQL management tool)</comment>');
            $output->writeln('<comment>➡️ Then run: php artisan migrate --seed</comment>');
        }

        // Step 8: CDN_URL instructions if custom domain or port
        $output->writeln('');
        $output->writeln('<comment>⚠️ If using a custom domain or port, update the CDN_URL in your .env to match your domain, e.g.:</comment>');
        $output->writeln('<comment>   CDN_URL=http://xyz.test</comment>');

        return Command::SUCCESS;
    }
}
