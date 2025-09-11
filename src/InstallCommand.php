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
    private bool $dbCreated = false; // Track if database is created

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

        // Step 1: Clone repo without .git
        $cloneProcess = Process::fromShellCommandline(
            "git clone --depth 1 https://github.com/anikrahman0/Panelix {$directory} && rd /s /q {$directory}\\.git"
        );
        $cloneProcess->setTimeout(null);
        $cloneProcess->run(fn($type, $buffer) => $output->write($buffer));

        if (!$cloneProcess->isSuccessful()) {
            $output->writeln('<error>❌ Failed to clone Panelix repository</error>');
            return Command::FAILURE;
        }

        // Step 2: Database setup
        $output->writeln("\n<info>💾 Database setup for Panelix</info>");
        $output->writeln("You can press Enter to accept default values.");

        $helper = $this->getHelper('question');

        // DB name
        $dbQuestion = new Question("Enter database name (default: panelix_db): ", 'panelix_db');
        $databaseName = $helper->ask($input, $output, $dbQuestion);

        // DB username
        $userQuestion = new Question("Enter database username (default: root): ", 'root');
        $dbUsername = $helper->ask($input, $output, $userQuestion);

        // DB password (hidden)
        $passQuestion = new Question("Enter database password (can be empty): ", '');
        $passQuestion->setHidden(true)->setHiddenFallback(false);
        $dbPassword = $helper->ask($input, $output, $passQuestion);

        // Check MySQL
        $checkMysql = new Process(['mysql', '--version']);
        $checkMysql->run();

        if ($checkMysql->isSuccessful()) {
            $output->writeln("<info>🔍 MySQL detected. Attempting to create database '{$databaseName}'...</info>");
            $createDbCmd = "mysql -u {$dbUsername}" . ($dbPassword ? " -p{$dbPassword}" : "") . " -e \"CREATE DATABASE IF NOT EXISTS {$databaseName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\"";
            $createDb = Process::fromShellCommandline($createDbCmd);
            $createDb->run();

            if ($createDb->isSuccessful()) {
                $this->dbCreated = true;
                $output->writeln("<info>✅ Database '{$databaseName}' created or already exists.</info>");
            } else {
                $output->writeln('<comment>⚠️ Could not create database automatically.</comment>');
                $output->writeln("<comment>➡️ Please create manually: CREATE DATABASE {$databaseName} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</comment>");
            }
        } else {
            $output->writeln('<comment>⚠️ MySQL not detected. Please install MySQL and create the database manually.</comment>');
        }

        // Step 3: Update .env with DB credentials
        copy("{$directory}/.env.example", "{$directory}/.env");
        $envPath = "{$directory}/.env";
        $envContent = file_get_contents($envPath);
        $envContent = preg_replace('/DB_DATABASE=.*/', "DB_DATABASE={$databaseName}", $envContent);
        $envContent = preg_replace('/DB_USERNAME=.*/', "DB_USERNAME={$dbUsername}", $envContent);
        $envContent = preg_replace('/DB_PASSWORD=.*/', "DB_PASSWORD={$dbPassword}", $envContent);
        file_put_contents($envPath, $envContent);
        $output->writeln("<info>✅ Database settings saved in .env file</info>");

        // Step 4: Install dependencies
        $output->writeln("\n<info>⚙️ Installing dependencies...</info>");
        $composer = Process::fromShellCommandline("cd {$directory} && composer install --no-scripts");
        $composer->setTimeout(null);
        $composer->run(fn($type, $buffer) => $output->write($buffer));

        // Step 5: Run migrations + seed (if DB created)
        if ($this->dbCreated) {
            $output->writeln("\n<info>📂 Running migrations and seeders...</info>");
            $artisan = Process::fromShellCommandline("cd {$directory} && php artisan migrate --seed");
            $artisan->setTimeout(null);
            $artisan->run(fn($type, $buffer) => $output->write($buffer));
        }

        // Step 6: Start Laravel server
        $host = '127.0.0.1';
        $port = 8000;
        $output->writeln("\n<info>🚀 Starting Laravel development server...</info>");
        $serve = new Process(["php", "artisan", "serve", "--host={$host}", "--port={$port}"], $directory);
        $serve->setTimeout(null);
        $serve->start();

        // Step 7: Final instructions
        $output->writeln("\n<info>✅ Panelix Project is ready!</info>");
        $output->writeln("👉 Serving at: http://{$host}:{$port}");
        $output->writeln("👉 Project directory: {$directory}");

        if (!$this->dbCreated) {
            $output->writeln('');
            $output->writeln('<comment>⚠️ Database was not created automatically.</comment>');
            $output->writeln('<comment>➡️ Please create it manually and then run: php artisan migrate --seed</comment>');
        }

        $output->writeln('');
        $output->writeln('<comment>⚠️ If using a custom domain or port, update CDN_URL in your .env file accordingly.</comment>');
        $output->writeln('<comment>➡️ Example: CDN_URL=http://xyz.test</comment>');

        return Command::SUCCESS;
    }
}
