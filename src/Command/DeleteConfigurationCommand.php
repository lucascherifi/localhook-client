<?php

namespace Localhook\Localhook\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteConfigurationCommand extends AbstractCommand

{
    protected function configure()
    {
        $this
            ->setName('delete-configuration')
            ->addArgument('endpoint', InputArgument::OPTIONAL, 'The name of the endpoint')
            ->addOption('all', null, InputOption::VALUE_NONE, 'If the configuration file should be deleted')
            ->setDescription('Delete a configuration or the whole configuration file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $all = $input->getOption('all');
        if ($all) {
            $this->configurationStorage->deleteFile();
        } else {
            $endpoint = $input->getArgument('endpoint');

            if (!$endpoint) {
                throw new \Exception('You should either enter a endpoint or the --all option');
            }

            if (!isset($this->configurationStorage->get()['webhooks'][$endpoint])) {
                throw new \Exception('The ' . $endpoint . ' configuration was not found');
            }
            $configuration = $this->configurationStorage->get();
            unset($configuration['webhooks'][$endpoint]);
            $this->configurationStorage->replaceConfiguration($configuration)->save();
        }
    }
}
