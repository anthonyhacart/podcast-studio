<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Episode\EpisodeRepository;
use App\Episode\Findings;
use App\Episode\PublicWebClient;
use App\Episode\Researcher;
use App\Episode\SourceReader;
use App\Mailbox\Mailbox;
use App\Tests\Episode\Fixtures;
use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\ExecutionInterface;
use Symfony\AI\Agent\Toolbox\Source\Source as Found;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * An editor without an MCP client makes an episode in the browser. The pages given
 * as links are served by the test; the hosts are the recorded Mistral responses of
 * the brief in fixtures/, so the pages hold exactly its sources.
 */
final class StudioControllerTest extends WebTestCase
{
    private const BRIEF = __DIR__.'/../../fixtures/briefs/worlds-2026.json';
    private const ID = '20261104-100000-abcd';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $_SERVER['CASSETTE'] = 'fixtures/cassettes/worlds-2026.json';
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

    #[Group('cassette')]
    public function testDraftsFromLinksThenPublishesTheReviewedScript(): void
    {
        /** @var array{topic: string, sources: list<array{title: string, notes: string, url: string}>, minutes: int, tone: string} $brief */
        $brief = json_decode((string) file_get_contents(self::BRIEF), true, flags: \JSON_THROW_ON_ERROR);
        $this->serve(...array_map(static fn (array $source) => new MockResponse(
            \sprintf('<html><head><title>%s</title></head><body><nav>Menu</nav><main><p>%s</p></main><script>track()</script></body></html>', htmlspecialchars($source['title']), htmlspecialchars($source['notes'])),
            ['response_headers' => ['Content-Type' => 'text/html; charset=UTF-8']],
        ), $brief['sources']));

        $this->client->request('GET', '/');
        $this->client->clickLink('make one here');
        $this->client->submitForm('Write the script', [
            'new_episode[topic]' => $brief['topic'],
            'new_episode[search]' => false,
            'new_episode[links]' => implode("\n", array_column($brief['sources'], 'url')),
            'new_episode[minutes]' => (string) $brief['minutes'],
            'new_episode[tone]' => $brief['tone'],
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.script h2');
        self::assertSelectorTextContains('.script p strong', 'Paul');
        self::assertSelectorExists('.script p sup a[href="#source-1"]');
        self::assertSelectorCount(4, '.sources li a[href="https://en.wikipedia.org/wiki/2026_League_of_Legends_World_Championship"]');
        self::assertSelectorTextContains('#source-4', '2026 League of Legends World Championship: quarterfinal schedule');
        $title = $this->client->getCrawler()->filter('h1')->text();

        $this->client->submitForm('Record and publish');

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', "\"{$title}\" is published.");
        self::assertSelectorTextContains('.episode .card-title', $title);
        self::assertSelectorExists('.episode audio');
    }

    public function testAsksForASource(): void
    {
        $this->client->request('GET', '/studio');
        $this->client->submitForm('Write the script', ['new_episode[topic]' => 'Worlds 2026', 'new_episode[search]' => false]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'Give the hosts a link or some notes, or let them search: they only use the facts found there.');
    }

    public function testRefusesWhatIsNotALink(): void
    {
        $this->client->request('GET', '/studio');
        $this->client->submitForm('Write the script', ['new_episode[topic]' => 'Worlds 2026', 'new_episode[search]' => false, 'new_episode[links]' => "https://example.com/worlds\nfile:///etc/passwd"]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', '"file:///etc/passwd" is not a link the hosts can read.');
    }

    public function testTellsWhichLinkCouldNotBeRead(): void
    {
        $this->serve(new MockResponse('Not found', ['http_code' => 404]));

        $this->client->request('GET', '/studio');
        $this->client->submitForm('Write the script', ['new_episode[topic]' => 'Worlds 2026', 'new_episode[search]' => false, 'new_episode[links]' => 'https://example.com/worlds']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'Could not read https://example.com/worlds: the site answered with an error 404.');
        self::assertSame([], glob(self::getContainer()->getParameter('app.episodes_dir').'/*'));
    }

    public function testTheHostsSearchForTheirSourcesUnlessTheEditorUnticksIt(): void
    {
        // the search returns a page that cannot be read
        $this->research(['https://example.com/worlds'], [new MockResponse('Gone', ['http_code' => 410])]);

        $this->client->request('GET', '/studio');
        self::assertSelectorExists('[data-controller="sources"] #new_episode_search[checked][data-action="sources#toggle"]');
        self::assertSelectorExists('[data-sources-target="links"] #new_episode_links');
        $this->client->submitForm('Write the script', ['new_episode[topic]' => 'Worlds 2026']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.alert-danger', 'The hosts found nothing they could read about "Worlds 2026". Give them a link or some notes.');
        self::assertSelectorExists('#new_episode_search[checked]');
    }

    public function testWithoutAKeyForTheWebSearchTheEditorBringsTheSources(): void
    {
        $this->research([], key: '');

        $this->client->request('GET', '/studio');
        self::assertSelectorNotExists('#new_episode_search');
        $this->client->submitForm('Write the script', ['new_episode[topic]' => 'Worlds 2026']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'Give the hosts a link or some notes');
    }

    public function testOffersTheWaitingListenerQuestions(): void
    {
        self::getContainer()->get(Mailbox::class)->post('Who wins the final?', 'Marie');

        $this->client->request('GET', '/studio');

        self::assertSelectorTextContains('#new_episode_questions label', 'Marie: Who wins the final?');
    }

    public function testShowsADraftForReviewWithItsSources(): void
    {
        $this->draft();

        $this->client->request('GET', '/studio/'.self::ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Episode');
        self::assertSelectorTextContains('.script h2', 'The format');
        self::assertSelectorTextContains('#source-1', 'Format');
        self::assertSelectorExists('form[action="/studio/'.self::ID.'/publish"]');
    }

    public function testAnUnknownDraftIsNotFound(): void
    {
        $this->client->request('GET', '/studio/20261104-100000-ffff');

        self::assertResponseStatusCodeSame(404);
    }

    private function serve(MockResponse ...$pages): void
    {
        self::getContainer()->set(PublicWebClient::class, new MockHttpClient($pages));
    }

    /**
     * Replaces the researcher by one whose agent found and picked $urls, read from $pages.
     *
     * @param list<string>       $urls
     * @param list<MockResponse> $pages
     */
    private function research(array $urls, array $pages = [], string $key = 'a-key'): void
    {
        $answer = new TextResult('');
        $answer->getMetadata()->add('sources', new SourceCollection(array_map(static fn (string $url) => new Found('A result', $url, ''), $urls)));
        $execution = self::createStub(ExecutionInterface::class);
        $execution->method('getResult')->willReturn($answer);
        $execution->method('asObject')->willReturn(new Findings($urls));
        $agent = self::createStub(AgentInterface::class);
        $agent->method('call')->willReturn($execution);

        self::getContainer()->set(Researcher::class, new Researcher($agent, new SourceReader(new MockHttpClient($pages)), new MockClock(), $key));
    }

    private function draft(): void
    {
        $episodes = self::getContainer()->get(EpisodeRepository::class);
        $episodes->saveBrief(self::ID, Fixtures::brief());
        $episodes->saveScript(self::ID, Fixtures::script());
    }
}
