<?php

declare(strict_types=1);

namespace App\Tests\Episode;

use App\Episode\Source;
use App\Episode\SourceReader;
use App\Episode\UnreadableSourceException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SourceReaderTest extends TestCase
{
    private const URL = 'https://example.com/worlds';

    public function testReadsTheContentOfAPageWithoutWhatIsAroundIt(): void
    {
        $source = $this->read(<<<'HTML'
            <!doctype html>
            <html>
                <head><title>Worlds 2026 &amp; its format</title><style>p { color: red }</style></head>
                <body>
                    <header>The Esports Gazette</header>
                    <nav><a href="/">Home</a></nav>
                    <main>
                        <h1>Worlds 2026</h1>
                        <p>The final is on 14 November
                           at Barclays Center.</p>
                        <aside>Read also: the Swiss stage</aside>
                    </main>
                    <footer>All rights reserved</footer>
                    <script>track('worlds')</script>
                </body>
            </html>
            HTML);

        self::assertSame('Worlds 2026 & its format', $source->title);
        self::assertSame('Worlds 2026 The final is on 14 November at Barclays Center.', $source->notes);
        self::assertSame(self::URL, $source->url);
    }

    public function testReadsThePageInItsOwnEncoding(): void
    {
        $source = $this->read(mb_convert_encoding('<html><head><title>Résumé</title></head><body><p>Karmine Corp est qualifiée.</p></body></html>', 'ISO-8859-1', 'UTF-8'), 'text/html; charset=ISO-8859-1');

        self::assertSame('Résumé', $source->title);
        self::assertSame('Karmine Corp est qualifiée.', $source->notes);
    }

    public function testKeepsTheBeginningOfALongPage(): void
    {
        $source = $this->read('<html><body><article>'.str_repeat('word ', 5000).'</article></body></html>');

        self::assertSame('example.com', $source->title);
        self::assertLessThanOrEqual(SourceReader::MAX_CHARACTERS + 1, mb_strlen($source->notes));
        self::assertStringEndsWith('word…', $source->notes);
    }

    public function testReadsPlainText(): void
    {
        $source = $this->read("Final on 14 November.\n", 'text/plain');

        self::assertSame('example.com', $source->title);
        self::assertSame('Final on 14 November.', $source->notes);
    }

    public function testRefusesAPageTheSiteDoesNotServe(): void
    {
        $this->expectException(UnreadableSourceException::class);
        $this->expectExceptionMessage('Could not read https://example.com/worlds: the site answered with an error 404.');

        $this->read('Not found', status: 404);
    }

    public function testRefusesWhatIsNotAPage(): void
    {
        $this->expectException(UnreadableSourceException::class);
        $this->expectExceptionMessage('it is not a web page');

        $this->read('%PDF-1.7', 'application/pdf');
    }

    public function testRefusesAPageWithoutText(): void
    {
        $this->expectException(UnreadableSourceException::class);
        $this->expectExceptionMessage('no text was found on the page');

        $this->read('<html><body><div id="app"></div><script>render()</script></body></html>');
    }

    private function read(string $body, string $type = 'text/html; charset=UTF-8', int $status = 200): Source
    {
        $reader = new SourceReader(new MockHttpClient(new MockResponse($body, ['http_code' => $status, 'response_headers' => ['Content-Type' => $type]])));

        return $reader->read(self::URL);
    }
}
