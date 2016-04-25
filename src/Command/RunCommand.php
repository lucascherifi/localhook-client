<?php

namespace Kasifi\Localhook\Command;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Kasifi\Localhook\Exceptions\DeletedChannelException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\VarDumper\VarDumper;

class RunCommand extends AbstractCommand

{
    /** @var integer */
    private $timeout;

    protected function configure()
    {
        $this
            ->setName('run')
            ->addArgument('endpoint', InputArgument::OPTIONAL, 'The name of the endpoint.')
            ->addOption('max', null, InputOption::VALUE_OPTIONAL, 'The maximum number of notification before stop watcher', null)
            ->setDescription('Watch for a notification and output it in JSON format.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->timeout = 15;
        parent::execute($input, $output);
        $this->ensureServerConnection();
        $endpoint = $input->getArgument('endpoint');
        // Retrieve configuration (and store it if necessary)
        $webHookConfiguration = $this->retrieveWebHookConfiguration($endpoint);
        $endpoint = $webHookConfiguration['endpoint'];
        $privateKey = $webHookConfiguration['privateKey'];
        $max = $input->getOption('max');
        $counter = 0;

        $output->writeln('Watch for notification to endpoint ' . $endpoint . ' ...');
        try {
            $this->socketIoClientConnector->subscribeChannel($endpoint, $privateKey);
        } catch (Exception $e) {// Todo use specific exception
            $configuration = $this->configurationStorage->get();
            unset($configuration['webhooks'][$endpoint]);
            $this->configurationStorage->replaceConfiguration($configuration)->save();
            throw new Exception(
                'Channel has been deleted by remote. ' .
                'Associated webhook configuration has been removed from your local configuration file.'
            );
        }

        while (true) {
            // apply max limitation
            if (!is_null($max) && $counter >= $max) {
                break;
            }
            $counter++;

            try {
                $notification = $this->socketIoClientConnector->waitForNotification();
            } catch (DeletedChannelException $e) {
                $configuration = $this->configurationStorage->get();
                unset($configuration['webhooks'][$endpoint]);
                $this->configurationStorage->replaceConfiguration($configuration)->save();
                throw new Exception(
                    'Channel has been deleted by remote. ' .
                    'Associated webhook configuration has been removed from your local configuration file.'
                );
            }

            $url = $webHookConfiguration['localUrl'];
            if (count($notification['query'])) {
                $url .= '?' . http_build_query($notification['query']);
            }

            $this->displayNotification($notification, $url);
            $this->sendNotification($notification, $url);
        }
        $this->socketIoClientConnector->closeConnection();
    }

    private function displayNotification($notification, $localUrl)
    {
        $vd = new VarDumper();

        // Local Request
        $this->io->section('Forwarding Notification');
        $this->output->writeln($notification['method'] . ' ' . $localUrl);

        // Headers
        $headers = [];
        foreach ($notification['headers'] as $key => $value) {
            $headers[] = [$key, implode(';', $value)];
        }
        $this->output->writeln('Headers:');
        (new Table($this->output))->setRows($headers)->render();

        // POST arguments
        if (count($notification['request'])) {
            $this->output->writeln('POST arguments:');
            $vd->dump($notification['request']);
        } else {
            $this->io->comment('No POST argument.');
        }
    }

    private function sendNotification($notification, $url)
    {
        $client = new Client();
        try {
            $this->io->comment('Waiting for response (timeout=' . $this->timeout . ')..');
            switch ($notification['method']) {
                case 'GET':
                    $response = $client->get($url, [
                        'headers' => $notification['headers'],
                        'timeout' => $this->timeout,
                    ]);
                    break;
                case 'POST':
                    $response = $client->post($url, [
                        'timeout'     => $this->timeout,
                        'headers'     => $notification['headers'],
                        'form_params' => [
                            $notification['request'],
                        ],
                    ]);
                    break;
                default:
                    throw new Exception(
                        'Request method "' . $notification['method'] . '" not managed in this version.' .
                        'Please request the feature in Github.'
                    );
            }
            $this->output->writeln('RESPONSE: ' . $response->getStatusCode());
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $this->output->writeln('RESPONSE: ' . $e->getResponse()->getStatusCode());
            }
        }
    }
}
