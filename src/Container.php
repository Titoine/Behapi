<?php
namespace Behapi;

use Psr\Container\ContainerInterface;

use Behat\Behat\HelperContainer\Exception\ServiceNotFoundException;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use Http\Message\StreamFactory;
use Http\Message\MessageFactory;

use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\UriFactoryDiscovery;
use Http\Discovery\StreamFactoryDiscovery;
use Http\Discovery\MessageFactoryDiscovery;

use Http\Client\HttpClient;
use Http\Client\Common\PluginClient;
use Http\Client\Common\Plugin\BaseUriPlugin;
use Http\Client\Common\Plugin\HistoryPlugin;
use Http\Client\Common\Plugin\ContentLengthPlugin;

use Behapi\HttpHistory\History as HttpHistory;

final class Container implements ContainerInterface
{
    /** @var object[] Instantiated services */
    private $services = [];

    /** @var string BaseURL for api requests */
    private $baseUrl;

    public function __construct(HttpHistory $history, string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
        $this->services[HttpHistory::class] = $history;
    }

    /** {@inheritDoc} */
    public function has($id)
    {
        static $services = [
            HttpClient::class,
            HttpHistory::class,
            StreamFactory::class,
            MessageFactory::class,
            EventDispatcherInterface::class,
        ];

        return in_array($id, $services);
    }

    /** {@inheritDoc} */
    public function get($id)
    {
        if (array_key_exists($id, $this->services)) {
            return $this->services[$id];
        }

        switch ($id) {
            case HttpClient::class:
                return $this->services[$id] = $this->getHttpClient();

            case MessageFactory::class:
                return $this->services[$id] = MessageFactoryDiscovery::find();

            case StreamFactory::class:
                return $this->services[$id] = StreamFactoryDiscovery::find();

            case EventDispatcherInterface::class:
                return $this->services[$id] = new EventDispatcher;
        }

        throw new ServiceNotFoundException("Service {$id} is not available", $id);
    }

    private function getHttpClient(): HttpClient
    {
        $uriFactory = UriFactoryDiscovery::find();
        $baseUri = $uriFactory->createUri($this->baseUrl);

        $plugins = [
            new ContentLengthPlugin,
            new BaseUriPlugin($baseUri),
            new HistoryPlugin($this->services[HttpHistory::class])
        ];

        $http = HttpClientDiscovery::find();

        return new PluginClient($http, $plugins);
    }
}
