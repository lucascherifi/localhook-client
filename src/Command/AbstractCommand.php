<?php

namespace Kasifi\Localhook\Command;

use Exception;
use Kasifi\Localhook\ConfigurationStorage;
use Kasifi\Localhook\Exceptions\NoConfigurationException;
use Localhook\Core\SocketIoClientConnector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractCommand extends Command
{
    /** @var SymfonyStyle */
    protected $io;

    /** @var InputInterface */
    protected $input;

    /** @var OutputInterface */
    protected $output;

    /** @var ConfigurationStorage */
    protected $configurationStorage;

    /** @var SocketIoClientConnector */
    protected $socketIoClientConnector;

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->configurationStorage = new ConfigurationStorage();
        $this->io = new SymfonyStyle($input, $output);
        $this->input = new $input;
        $this->output = new $output;
        $this->loadConfiguration();
    }

    protected function loadConfiguration()
    {
        try {
            $this->configurationStorage->loadFromFile()->get();
        } catch (NoConfigurationException $e) {

            $this->io->comment($e->getMessage());

            $serverUrl = $this->io->ask('Server URL', 'http://localhost:1337');

            $this->configurationStorage->merge(['server_url' => $serverUrl])->save();
        }
    }

    protected function ensureServerConnection()
    {
        if (!$this->socketIoClientConnector) {
            $this->output->writeln('Connecting to ' . $this->configurationStorage->get()['server_url'] . ' ...');
            $this->socketIoClientConnector = new SocketIoClientConnector($this->configurationStorage->get()['server_url']);
            $this->socketIoClientConnector->ensureConnection();
        }
    }

    protected function askPrivateKey()
    {
        return $privateKey = $this->io->ask('Private key', '1----------------------------------');
    }

    protected function retrieveWebHookConfiguration($endpoint)
    {
        if (!$endpoint) {
            if (
                isset($this->configurationStorage->get()['webhooks'])
                && count($webHooks = $this->configurationStorage->get()['webhooks'])
            ) {
                $question = new ChoiceQuestion('Select a configured webhook', array_keys($webHooks));
                $endpoint = $this->io->askQuestion($question);
            } else {
                $this->addWebHookConfiguration();
            }
        } elseif (!isset($endpoint, $this->configurationStorage->get()['webhooks'][$endpoint])) {
            $this->addWebHookConfiguration();
        }

        return ['privateKey' => $this->configurationStorage->get()['webhooks'][$endpoint], 'endpoint' => $endpoint];
    }

    private function addWebHookConfiguration()
    {
        $privateKey = $this->askPrivateKey();
        $configuration = $this->socketIoClientConnector->retrieveConfigurationFromPrivateKey($privateKey);
        $endpoint = $configuration['endpoint'];
        if (!$endpoint) {
            throw new Exception('This private key doesn\'t match any endpoint');
        }
        $this->io->comment('Associated endpoint: ' . $endpoint);

        $this->configurationStorage->merge(['webhooks' => [$endpoint => $privateKey]])->save();
    }
}