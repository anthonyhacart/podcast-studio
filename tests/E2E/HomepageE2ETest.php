<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Panther\PantherTestCase;

/**
 * Real browser test. Chromium is started with fake media devices so voice
 * features can be exercised without a human: pass
 * --use-fake-device-for-media-stream --use-fake-ui-for-media-stream
 * --use-file-for-fake-audio-capture=fixtures/audio/<file>.wav
 * through PANTHER_CHROME_ARGUMENTS in the tests that need it.
 */
#[Group('e2e')]
final class HomepageE2ETest extends PantherTestCase
{
    public function testHomepageRendersInABrowser(): void
    {
        $client = self::createPantherClient();
        $client->request('GET', '/');

        self::assertSelectorExists('h1');
    }
}
