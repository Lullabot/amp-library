<?php

namespace Lullabot\AMP;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use QueryPath;

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
                '--no-orig-and-warn',
                null,
                InputOption::VALUE_NONE,
                'If set, the original HTML and warnings encountered during conversion will not be printed out'
            )
            ->addOption(
                '--no-lines',
                null,
                InputOption::VALUE_NONE,
                'If set, the line numbers will be not printed the AMPized HTML. Option makes sense only when --no-orig-and-warn is not used'
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
            // now this is our new output html
            $amp_html = $this->getStringWithLineNumbers($amp_html);
        }

        // Show the diff if the option is set
        if (!$input->getOption('diff')) {
            $output->writeln($amp_html);
        } else {
            // $escape_html is FALSE since we're outputting to the console
            $output->writeln($amp->getInputOutputHtmlDiff($escape_html = FALSE));
        }

        // Show the warnings by default
        if (!$input->getOption('no-orig-and-warn')) {
            $output->writeln("\nORIGINAL HTML WITH WARNINGS");
            $output->writeln("===========================");
            $output->writeln($this->getStringWithLineNumbers($amp->getInputHtml()));
            $output->writeln('Warnings');
            $output->writeln($amp->warningsHumanText());
        }
    }

    protected function getStringWithLineNumbers($string_input)
    {
        $lines = explode(PHP_EOL, $string_input);
        $string_output = '';
        $n = strlen((string)count($lines));
        $lineno = 0;
        foreach ($lines as $line) {
            $lineno++;
            $string_output .= sprintf("Line %{$n}d: %s" . PHP_EOL, $lineno, $line);
        }

        return $string_output;
    }
}
