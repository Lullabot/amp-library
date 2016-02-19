<?php

namespace Lullabot\AMP;

use Lullabot\AMP\Validate\Scope;
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
                'If set, the original HTML and warnings/messages encountered during conversion will not be printed out'
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
                'If set, a diff of the input and output HTML will be printed out instead of the AMP html. ' .
                'Note that the original HTML will be formatted before being diffed with output HTML for best results. ' .
                'This is because the output HTML is also formatted automatically.'
            )
            ->addOption(
                '--js',
                null,
                InputOption::VALUE_NONE,
                'If set, a list of custom amp components and the url to include the js is printed out'
            )
            ->addOption(
                '--full-document',
                null,
                InputOption::VALUE_NONE,
                'If set, assumes this is a whole document html document and not an html fragment underneath the body (which is the default)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filename = $input->getArgument('filename');
        if (!empty($filename)) {
            $file_html = file_get_contents($filename);
        } else {
            $filename = 'php://stdin';
            $file_html = file_get_contents($filename);
        }

        $amp = new AMP();
        $options = ['filename' => $filename]; // So warnings can be printed out with filename appending to line number
        if ($input->getOption('full-document')) {
            $options += ['scope' => Scope::HTML_SCOPE];
        }
        $amp->loadHtml($file_html, $options);
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
            $output->writeln("\nORIGINAL HTML");
            $output->writeln("~~~~~~~~~~~~~~~");
            $output->writeln($this->getStringWithLineNumbers($amp->getInputHtml()));
            $output->writeln($amp->warningsHumanText());
        }

        // Show the components with js urls
        if ($input->getOption('js')) {
            $output->writeln("\nCOMPONENT NAMES WITH JS PATH");
            $output->writeln("~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~");
            $output->writeln($this->componentList($amp->getComponentJs()));
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

    protected function componentList($components)
    {
        $str = '';
        if (empty($components)) {
            return 'No custom amp components found';
        }

        foreach ($components as $name => $uri) {
            $str .= "'$name', include path '$uri''";
        }

        return $str;
    }
}
