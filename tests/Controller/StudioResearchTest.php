<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Episode\PublicWebClient;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * An editor gives a topic and lets the hosts find their sources, against recorded
 * Mistral and Tavily responses: the researcher searches and picks pages, the writer
 * drafts from them, the voices record. Without the cassette, it records one
 * (`make cassette`). The pages the search found are served by the test, all with
 * the text of fixtures/pages/bears.html: the cassette stays small and offline.
 */
#[Group('cassette')]
final class StudioResearchTest extends WebTestCase
{
    private const PAGE = __DIR__.'/../../fixtures/pages/bears.html';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $_SERVER['CASSETTE'] = 'fixtures/cassettes/bears.json';
        $this->client = self::createClient();
        // one container for the whole test: the pages the test serves stay in place
        $this->client->disableReboot();
        (new Filesystem())->remove([self::getContainer()->getParameter('app.episodes_dir'), self::getContainer()->getParameter('app.questions_dir')]);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['CASSETTE']);
        parent::tearDown();
    }

    public function testTheHostsFindTheirSourcesThenTheEpisodeIsPublished(): void
    {
        $read = [];
        self::getContainer()->set(PublicWebClient::class, new MockHttpClient(static function (string $method, string $url) use (&$read): MockResponse {
            $read[] = $url;

            return new MockResponse((string) file_get_contents(self::PAGE), ['response_headers' => ['Content-Type' => 'text/html; charset=UTF-8']]);
        }));

        $this->client->request('GET', '/studio');
        $this->client->submitForm('Write the script', [
            'new_episode[topic]' => 'Bears: how they get ready for winter',
            'new_episode[tone]' => 'curious and fun, for the whole family',
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertNotEmpty($read, 'the pages the researcher picked are read');
        self::assertLessThanOrEqual(5, \count($read));
        self::assertSelectorCount(\count($read), '.sources li');
        self::assertSelectorExists('.sources li a[href="'.$read[0].'"]');
        self::assertSelectorTextContains('.sources li', 'How bears get ready for winter');
        self::assertSelectorExists('.script p sup a[href="#source-1"]');
        $title = $this->client->getCrawler()->filter('h1')->text();

        $this->client->submitForm('Record and publish');

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', "\"{$title}\" is published.");
        self::assertSelectorTextContains('.episode .card-title', $title);
    }
}
