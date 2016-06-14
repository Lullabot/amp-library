<?php
/*
 * Copyright 2016 Google
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
                '--no-orig-and-warn',
                null,
                InputOption::VALUE_NONE,
                'If set, the original HTML and warnings/messages encountered during conversion will not be printed out'
            )
            ->addOption(
                '--no-lines',
                null,
                InputOption::VALUE_NONE,
                'If set, the line numbers will be not printed alongside the AMPized HTML.'
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
            )
            ->addOption(
                '--options',
                null,
                InputOption::VALUE_REQUIRED,
                'If set, loads options from the file indicated'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $amp = new AMP();
        $options_filename = $input->getOption('options');
        $options = [];
        if (!empty($options_filename)) {
            $options = $amp->getOptions($options_filename);
        }

        // consoleOutput($filename = 'php://stdin', $options, $full_document = false, $js = false, $no_lines = false, $diff = false, $no_orig_and_warn = false, $verbose = false)
        /** @var string $console_output */
        $console_output = $amp->consoleOutput(
            $input->getArgument('filename'),
            $options,
            $input->getOption('full-document'),
            $input->getOption('js'),
            $input->getOption('no-lines'),
            $input->getOption('diff'),
            $input->getOption('no-orig-and-warn'),
            $input->getOption('verbose')
        );

        $output->write($console_output);
    }
}
