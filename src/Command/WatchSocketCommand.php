<?php

namespace Localhook\Localhook\Command\Client;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Localhook\Localhook\Ratchet\UserClient;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\VarDumper\VarDumper;

class WatchSocketCommand extends ContainerAwareCommand
{
    /** @var SymfonyStyle */
    private $io;

    /** @var int */
    private $timeout = 15;

    /** @var UserClient */
    private $socketUserClient;

    /** @var integer */
    private $max;

    /** @var string */
    private $secret;

    /** @var OutputInterface */
    private $output;

    protected function configure()
    {
        $this
            ->setName('app:client:watch-socket')
            ->addArgument('endpoint', InputArgument::REQUIRED, 'The name of the endpoint.')
            ->addOption('max', null, InputOption::VALUE_OPTIONAL, 'The maximum number of notification before stop watcher', null)
            ->setDescription('Watch for a notification and output it in JSON format.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $em = $this->getContainer()->get('doctrine.orm.default_entity_manager');

        $endpoint = $input->getArgument('endpoint');
        $this->max = $input->getOption('max');
        $webHook = $em->getRepository('AppBundle:WebHook')->findOneBy(['endpoint' => $endpoint]);
        if (!$webHook) {
            throw new Exception('No webhook found for this endpoint');
        }
        $this->secret = $webHook->getPrivateKey();

        $this->socketUserClient = $this->getContainer()->get('socket_user_client');
        $this->io->comment('Connecting to ' . $this->socketUserClient->getUrl() . ' ...');
        $this->socketUserClient->start(function () {
            $this->socketUserClient->executeSubscribeWebHook(function ($msg) {
                $this->io->success('Successfully subscribed to ' . $msg['endpoint']);
                $this->io->comment('Watching for a notification...');
            }, function ($request) {
                $url = 'http://localhost:8000/local-notif';
                if (count($request['query'])) {
                    $url .= '?' . http_build_query($request['query']);
                }
                $this->displayRequest($request, $url);
                $this->sendRequest($request, $url);
            }, function () {
                $this->socketUserClient->stop();
                $this->io->warning('Max forward reached (' . $this->max . ')');
                exit(0);
            }, $this->secret, $this->max);
        });
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
