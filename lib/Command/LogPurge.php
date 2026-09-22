<?php

namespace FriendsOfRedaxo\ConsentKit\Command;

use FriendsOfRedaxo\ConsentKit\Log;
use rex_console_command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class LogPurge extends rex_console_command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Deletes consent log entries older than the retention period')
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Override the configured retention period (days)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = $input->getOption('days');
        $deleted = Log::purge(null === $days ? null : (int) $days);
        $this->getStyle($input, $output)->success($deleted . ' log entries deleted.');
        return 0;
    }
}
