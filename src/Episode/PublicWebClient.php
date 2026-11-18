<?php

declare(strict_types=1);

namespace App\Episode;

use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The HTTP client that reads the pages given as sources. Their URLs are typed in a
 * form: it never calls the private network, and gives up on a slow page.
 */
final class PublicWebClient implements HttpClientInterface
{
    use DecoratorTrait {
        DecoratorTrait::__construct as private decorate;
    }

    public function __construct(HttpClientInterface $client)
    {
        $this->decorate(new NoPrivateNetworkHttpClient($client->withOptions([
            'timeout' => 10,
            'max_duration' => 20,
            'max_redirects' => 5,
            'headers' => [
                'Accept' => 'text/html, text/plain',
                'User-Agent' => 'PodcastStudio (+https://github.com/anthonyhacart/podcast-studio)',
            ],
        ])));
    }
}
