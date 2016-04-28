<?php

namespace Localhook\Localhook\Command;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Localhook\Localhook\Ratchet\UserClient;
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

    /** @var array */
    private $webHookConfiguration;

    /** @var int */
    private $counter;

    /** @var int */
    private $max;

    protected function configure()
    {
        if (!$this->getName()) {
            $this->setName('run');
        }
        $this
            ->addArgument('endpoint', InputArgument::OPTIONAL, 'The name of the endpoint')
            ->addArgument('private-key', InputArgument::OPTIONAL, 'The private key of the endpoint')
            ->addArgument('local-url', InputArgument::OPTIONAL, 'The local URL to call when a request in received from the server')
            ->addArgument('server-url', InputArgument::OPTIONAL, 'The server socket URL')
            ->addOption('max', null, InputOption::VALUE_OPTIONAL, 'The maximum number of notification before stop watcher', null)
            ->addOption('no-config-file', null, InputOption::VALUE_NONE, 'If you don\'t want to save the current configuration.', null)
            ->setDescription('Watch for a notification and output it in JSON format');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Retrieve configuration (and store it if necessary)
        $this->max = $input->getOption('max');
        $this->counter = 0;
        $this->timeout = 15;
        $this->endpoint = $input->getArgument('endpoint');
        $this->webHookPrivateKey = $input->getArgument('private-key');
        $this->webHookLocalUrl = $input->getArgument('local-url');
        $this->serverUrl = $input->getArgument('server-url');
        $this->noConfigFile = $input->getOption('no-config-file');

        parent::execute($input, $output);

        $this->socketUserClient = new UserClient($this->serverUrl);
        $this->io->comment('Connecting to ' . $this->socketUserClient->getUrl() . ' ...');
        $this->socketUserClient->start(function () {
            $this->detectWebHookConfiguration($this->endpoint, function ($webHookConfiguration) {
                $this->webHookConfiguration = $webHookConfiguration;
                $this->socketUserClient->executeSubscribeWebHook(function ($msg) {
                    $this->io->success('Successfully subscribed to ' . $this->endpoint);
                    $this->output->writeln('Watch for notification to endpoint ' . $this->endpoint . ' ...');
                }, function ($request) {
                    $url = $this->webHookConfiguration['localUrl'];
                    if (count($request['query'])) {
                        $url .= '?' . http_build_query($request['query']);
                    }
                    $this->displayRequest($request, $url);
                    $this->sendRequest($request, $url);
                }, function () {
                    $this->socketUserClient->stop();
                    $this->io->warning('Max forward reached (' . $this->max . ')');
                    exit(0);
                }, $this->webHookPrivateKey, $this->max);
            });
        });

//        try {
//            $this->socketIoClientConnector->subscribeChannel($endpoint, $privateKey);
//        } catch (Exception $e) {// Todo use specific exception
//            $configuration = $this->configurationStorage->get();
//            unset($configuration['webhooks'][$endpoint]);
//            $this->configurationStorage->replaceConfiguration($configuration)->save();
//            throw new Exception(
//                'Channel has been deleted by remote. ' .
//                'Associated webhook configuration has been removed from your local configuration file.'
//            );
//        }
//
//        while (true) {
//            // apply max limitation
//            if (!is_null($max) && $counter >= $max) {
//                break;
//            }
//            $counter++;
//
//            try {
//                $notification = $this->socketIoClientConnector->waitForNotification();
//            } catch (DeletedChannelException $e) {
//                $configuration = $this->configurationStorage->get();
//                unset($configuration['webhooks'][$endpoint]);
//                $this->configurationStorage->replaceConfiguration($configuration)->save();
//                throw new Exception(
//                    'Channel has been deleted by remote. ' .
//                    'Associated webhook configuration has been removed from your local configuration file.'
//                );
//            }
//
//            $url = $webHookConfiguration['localUrl'];
//            if (count($notification['query'])) {
//                $url .= '?' . http_build_query($notification['query']);
//            }
//
//            $this->displayNotification($notification, $url);
//            $this->sendNotification($notification, $url);
//        }
//        $this->socketIoClientConnector->closeConnection();
    }

    private function displayRequest($request, $localUrl)
    {
        $vd = new VarDumper();

        // Local Request
        $this->io->success($request['method'] . ' ' . $localUrl);

        // Headers
        $headers = [];
        foreach ($request['headers'] as $key => $value) {
            $headers[] = [$key, implode(';', $value)];
        }
        (new Table($this->output))->setHeaders(['Request Header', 'Value'])->setRows($headers)->render();

        // POST arguments
        if (count($request['request'])) {
            $this->io->comment('POST arguments');
            $vd->dump($request['request']);
        } else {
            $this->io->comment('No POST argument.');
        }
    }

    private function sendRequest($request, $url)
    {
        $client = new Client();
        try {
            $this->io->comment('Waiting for response (timeout=' . $this->timeout . ')..');
            switch ($request['method']) {
                case 'GET':
                    $response = $client->get($url, [
                        'headers' => $request['headers'],
                        'timeout' => $this->timeout,
                    ]);
                    break;
                case 'POST':
                    $response = $client->post($url, [
                        'timeout'     => $this->timeout,
                        'headers'     => $request['headers'],
                        'form_params' => [
                            $request['request'],
                        ],
                    ]);
                    break;
                default:
                    throw new Exception(
                        'Request method "' . $request['method'] . '" not managed in this version.' .
                        'Please request the feature in Github.'
                    );
            }
            $this->io->comment('LOCAL RESPONSE: ' . $response->getStatusCode());
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $this->io->warning('LOCAL RESPONSE: ' . $e->getResponse()->getStatusCode());
            }
        }
    }
}
