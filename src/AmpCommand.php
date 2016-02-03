<?php

namespace Lullabot\AMP;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AmpCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('amp:convert')
            ->setDescription('Convert some HTML to AMP HTML')
            ->addArgument(
                'filename',
                InputArgument::OPTIONAL,
                'Which file do you want to convert to AMP HTML? (Default is stdin)'
            )
            ->addOption(
                '--no-warnings',
                null,
                InputOption::VALUE_NONE,
                'If set, the warnings encountered during conversion will be suppressed'
            )
            ->addOption(
                '--no-lines',
                null,
                InputOption::VALUE_NONE,
                'If set, the line numbers will be not printed along with source code. Option makes sense only when --diff is not used'
            )
            ->addOption(
                '--diff',
                null,
                InputOption::VALUE_NONE,
                'If set, a diff of the input and output HTML will be printed out instead of the AMP html'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filename = $input->getArgument('filename');
        if (!empty($filename)) {
            $file_html = file_get_contents($filename);
        } else {
            $file_html = file_get_contents('php://stdin');
        }

        $amp = new AMP();
        $amp->loadHtml($file_html);
        $amp_html = $amp->convertToAmpHtml();

        if (!$input->getOption('no-lines')) {
            $amp_lines = explode(PHP_EOL, $amp_html);
            $amp_html_new = '';
            $n = strlen((string)count($amp_lines));
            $line = 0;
            foreach ($amp_lines as $amp_line) {
                $line++;
                $amp_html_new .= sprintf("Line %{$n}d: %s" . PHP_EOL, $line, $amp_line);
            }
            // now this is our new output html
            $amp_html = $amp_html_new;
        }

        // Show the diff if the option is set
        if (!$input->getOption('diff')) {
            $output->writeln($amp_html);
        } else {
            // $escape_html is FALSE since we're outputting to the console
            $output->writeln($amp->getInputOutputHtmlDiff($escape_html = FALSE));
        }

        // Show the warnings by default
        if (!$input->getOption('no-warnings')) {
            $output->writeln($amp->warningsHumanText());
        }
    }
}
