<?php

namespace Studip\Cli\Commands\Migrate;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Migrate extends Command
{
    protected static $defaultName = 'migrate';

    protected function configure(): void
    {
        $this->setDescription('Run the database migrations.');
        $this->setHelp('This command runs all pending database migrations.');

        $this->addOption('branch', 'b', InputOption::VALUE_OPTIONAL, 'the branch of the migrations', '0');

        $this->addOption('domain', 'd', InputOption::VALUE_OPTIONAL, 'the domain of the migrations', 'studip');

        $defaultPath = $GLOBALS['STUDIP_BASE_PATH'] . '/db/migrations';
        $this->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'the path to the directory containing the migrations', $defaultPath);

        $this->addOption('target', 't', InputOption::VALUE_OPTIONAL, 'the target version', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $branch = $input->getOption('branch');
        $domain = $input->getOption('domain');
        $path = $input->getOption('path');
        $target = $input->getOption('target');
        $verbose = $input->getOption('verbose');

        $version = new \DBSchemaVersion($domain, $branch);
        $migrator = new \Migrator($path, $version, $verbose);
        $migrator->migrateTo($target);

        return Command::SUCCESS;
    }
}
