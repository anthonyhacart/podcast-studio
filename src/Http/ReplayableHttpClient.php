<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\AI\Platform\Test\Replay\CassetteHttpClient;
use Symfony\AI\Platform\Test\Replay\HttpCassette;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The HTTP client of the Mistral platform. With CASSETTE set, it records the API
 * responses to that file the first time, then replays them: `make demo` runs the
 * whole pipeline (writer agent, voice) without an API key.
 */
final class ReplayableHttpClient implements HttpClientInterface
{
    use DecoratorTrait {
        DecoratorTrait::__construct as private decorate;
    }

    public function __construct(
        HttpClientInterface $client,
        #[Autowire(env: 'default::CASSETTE')] ?string $cassette = null,
        #[Autowire('%kernel.project_dir%')] string $projectDir = '',
    ) {
        $this->decorate(null === $cassette || '' === $cassette ? $client : new CassetteHttpClient(new HttpCassette("{$projectDir}/{$cassette}"), $client));
    }
}
