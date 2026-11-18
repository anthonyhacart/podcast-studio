<?php

declare(strict_types=1);

namespace App\Episode;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\String\TruncateMode;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\String\u;

/**
 * Reads a web page into a source, for an editor who brings links instead of notes:
 * the page's own text, not a summary of it, so nothing is made up on the way.
 */
final readonly class SourceReader
{
    /** What the writer gets from one page: about 1,500 words. */
    public const MAX_CHARACTERS = 10_000;

    private const MAX_BYTES = 2_000_000;

    public function __construct(
        #[Autowire(service: PublicWebClient::class)] private HttpClientInterface $client,
    ) {
    }

    public function read(string $url): Source
    {
        try {
            $response = $this->client->request('GET', $url);
            $type = $response->getHeaders()['content-type'][0] ?? 'text/html';
            $body = '';
            foreach ($this->client->stream($response) as $chunk) {
                $body .= $chunk->getContent();
                if (\strlen($body) > self::MAX_BYTES) {
                    $response->cancel();
                    break;
                }
            }
        } catch (HttpExceptionInterface $e) {
            throw new UnreadableSourceException($url, \sprintf('the site answered with an error %d', $e->getResponse()->getStatusCode()), $e);
        } catch (ExceptionInterface $e) {
            throw new UnreadableSourceException($url, 'the site could not be reached', $e);
        }

        if (!str_starts_with($type, 'text/')) {
            throw new UnreadableSourceException($url, 'it is not a web page');
        }

        [$title, $text] = str_contains($type, 'html') ? $this->page($body, $type, $url) : [null, trim($body)];
        if ('' === $text) {
            throw new UnreadableSourceException($url, 'no text was found on the page');
        }

        return new Source(
            $title ?? (string) parse_url($url, \PHP_URL_HOST),
            u($text)->truncate(self::MAX_CHARACTERS, '…', TruncateMode::WordBefore)->toString(),
            $url,
        );
    }

    /**
     * @return array{?string, string} the page's title and the text of its main content
     */
    private function page(string $html, string $type, string $url): array
    {
        $page = new Crawler(uri: $url);
        $page->addContent($html, $type);

        $title = $page->filter('title');
        $title = 0 === $title->count() || '' === $title->text() ? null : $title->text();

        // what is around the content, on most sites
        foreach ($page->filter('script, style, noscript, template, svg, nav, header, footer, aside, form') as $node) {
            $node->parentNode?->removeChild($node);
        }

        // table cells and list items have no space between them
        foreach ($page->filter('p, br, li, tr, td, th, dt, dd, div, h1, h2, h3, h4, h5, h6') as $node) {
            if (null !== $document = $node->ownerDocument) {
                $node->parentNode?->insertBefore($document->createTextNode(' '), $node->nextSibling);
            }
        }

        foreach (['main', 'article', 'body'] as $selector) {
            $content = $page->filter($selector);
            if (0 !== $content->count() && '' !== $text = $content->text()) {
                return [$title, $text];
            }
        }

        return [$title, ''];
    }
}
