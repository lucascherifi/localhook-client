<?php

namespace Kasifi\Localhook\Command;

use ElephantIO\Exception\ServerConnectionFailureException;
use Exception;
use Kasifi\Localhook\ConfigurationStorage;
use Kasifi\Localhook\Exceptions\NoConfigurationException;
use Kasifi\Localhook\SocketIoClientConnector;
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
            try {
                $this->socketIoClientConnector->ensureConnection();
            } catch (ServerConnectionFailureException $e) {
                $this->io->error('The Localhook server at ' . $this->configurationStorage->get()['server_url'] . ' seems to be stopped.');
                exit(1);
            }
        }
    }

    protected function retrieveWebHookConfiguration($endpoint)
    {
        if (!$endpoint) {
            if (
                isset($this->configurationStorage->get()['webhooks'])
                && $nbConfigs = count($webHooks = $this->configurationStorage->get()['webhooks'])
            ) {
                if ($nbConfigs > 1) {
                    $question = new ChoiceQuestion('Select a configured webhook', array_keys($webHooks));
                    $endpoint = $this->io->askQuestion($question);
                } else {
                    $endpoint = array_keys($webHooks)[0];
                }

            } else {
                $endpoint = $this->addWebHookConfiguration();
            }
        } elseif (!isset($endpoint, $this->configurationStorage->get()['webhooks'][$endpoint])) {
            $endpoint = $this->addWebHookConfiguration();
        }

        return array_merge($this->configurationStorage->get()['webhooks'][$endpoint], ['endpoint' => $endpoint]);
    }

    private function addWebHookConfiguration()
    {
        $privateKey = $this->io->ask('Private key', '1----------------------------------');
        $configuration = $this->socketIoClientConnector->retrieveConfigurationFromPrivateKey($privateKey);
        $endpoint = $configuration['endpoint'];
        if (!$endpoint) {
            throw new Exception('This private key doesn\'t match any endpoint');
        }
        $this->io->comment('Associated endpoint: ' . $endpoint);

        $url = $this->io->ask('Local URL to call when notification received', 'http://localhost/my-project/notifications');

        $this->configurationStorage->merge([
            'webhooks' => [
                $endpoint => ['privateKey' => $privateKey, 'localUrl' => $url],
            ],
        ])->save();

        return $endpoint;
    }
}
