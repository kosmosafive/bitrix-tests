<?php

declare(strict_types=1);

namespace Kosmos\BitrixTests\Console;

use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

readonly class Helper
{
    protected InputInterface $input;
    protected OutputInterface $output;
    protected FormatterHelper $formatter;
    protected SymfonyStyle $styler;

    public function __construct()
    {
        $this->input = new Input();
        $this->input->setStream(STDIN);

        $this->output = new ConsoleOutput();
        $this->formatter = new FormatterHelper();
        $this->styler = new SymfonyStyle($this->input, $this->output);
    }

    public function getInput(): InputInterface
    {
        return $this->input;
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function createProgressBar(int $totalCount): ProgressBar
    {
        $progressBar = new ProgressBar($this->output, $totalCount);
        $progressBar->setEmptyBarCharacter("\033[31m█\033[0m");
        $progressBar->setProgressCharacter('');
        $progressBar->setBarCharacter("\033[32m█\033[0m");
        $progressBar->setFormat("%current%/%max% %bar% %percent:3s%% \n⚐ %elapsed:6s%/%estimated:-19s% 📈 %memory:6s%");

        return $progressBar;
    }

    public function getFormatter(): FormatterHelper
    {
        return $this->formatter;
    }

    public function getStyler(): SymfonyStyle
    {
        return $this->styler;
    }
}
