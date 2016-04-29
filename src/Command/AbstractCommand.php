<?php

namespace Localhook\Localhook\Command;

use Exception;
use Localhook\Localhook\ConfigurationStorage;
use Localhook\Localhook\Exceptions\NoConfigurationException;
use Localhook\Localhook\Ratchet\UserClient;
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

    /** @var UserClient */
    protected $socketUserClient;

    /** @var string */
    protected $webHookPrivateKey;

    /** @var string */
    protected $webHookLocalUrl;

    /** @var string */
    protected $serverUrl;

    /** @var boolean */
    protected $noConfigFile;

    /** @var string */
    protected $endpoint;

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->configurationStorage = new ConfigurationStorage($this->noConfigFile);
        $this->io = new SymfonyStyle($input, $output);
        $this->input = new $input;
        $this->output = new $output;
        $this->loadConfiguration();
    }

    protected function loadConfiguration()
    {
        try {
            $configuration = $this->configurationStorage->loadFromFile()->get();
            if (!$this->serverUrl && isset($configuration['server_url'])) {
                $this->serverUrl = $configuration['server_url'];
            }
        } catch (NoConfigurationException $e) {

            $this->io->comment($e->getMessage());

            if (!$this->serverUrl) {
                $this->serverUrl = $this->io->ask('Server URL', 'ws://127.0.0.1:1337');
            }

            $this->configurationStorage->merge(['server_url' => $this->serverUrl])->save();
        }
    }

    protected function detectWebHookConfiguration($endpoint, callable $onSuccess)
    {
        if (!$endpoint) {
            if (
                isset($this->configurationStorage->get()['webhooks'])
                && $nbConfigs = count($this->configurationStorage->get()['webhooks'])
            ) {
                $webHooks = $this->configurationStorage->get()['webhooks'];
                if ($nbConfigs > 1) {
                    $question = new ChoiceQuestion('Select a configured webhook', array_keys($webHooks));
                    $endpoint = $this->io->askQuestion($question);
                } else {
                    $endpoint = $webHooks[0]['endpoint'];
                }
                $this->endpoint = $endpoint;
                $webHookConfiguration = $this->getWebHookConfigurationBy('endpoint', $endpoint);
                $this->webHookPrivateKey = $webHookConfiguration['privateKey'];
                $onSuccess($webHookConfiguration);
            } else {
                $this->newWebHookConfiguration($onSuccess);
            }
        } elseif (!isset($endpoint, $this->configurationStorage->get()['webhooks'][$endpoint])) {
            $this->newWebHookConfiguration($onSuccess);
        }
    }

    private function newWebHookConfiguration($onSuccess)
    {
        if (!$this->webHookPrivateKey) {
            $this->webHookPrivateKey = $this->io->ask('Private key', '1----------------------------------');
        }
        $this->socketUserClient->executeRetrieveConfigurationFromSecret(
            $this->webHookPrivateKey, function ($msg) use ($onSuccess) {
            $endpoint = $msg['endpoint'];
            $this->endpoint = $endpoint;
            if (!$endpoint) {
                throw new Exception('This private key does not match any endpoint');
            }
            $this->io->comment('Associated endpoint: ' . $endpoint);
            if (!$this->webHookLocalUrl) {
                $this->webHookLocalUrl = $this->io->ask(
                    'Local URL to call when notification received',
                    'http://localhost/my-project/notifications'
                );
            }

            $webHookConfiguration = [
                'privateKey' => $this->webHookPrivateKey,
                'localUrl'   => $this->webHookLocalUrl,
                'endpoint'   => $endpoint,
            ];
            $this->configurationStorage->merge([
                'webhooks' => [$webHookConfiguration],
            ])->save();
            $onSuccess($webHookConfiguration);
        });
    }

    private function getWebHookConfigurationBy($key, $value)
    {
        $configuration = $this->configurationStorage->get();
        if (!isset($configuration['webhooks'])) {
            return null;
        }
        foreach ($configuration['webhooks'] as $webHook) {
            if ($webHook[$key] == $value) {
                return $webHook;
            }
        }

        return null;
    }
}
