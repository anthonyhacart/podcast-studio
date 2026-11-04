<?php

declare(strict_types=1);

namespace App\Tests\Episode;

use App\Episode\EpisodeRepository;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class EpisodeRepositoryTest extends TestCase
{
    private string $dir;
    private EpisodeRepository $episodes;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/episodes-'.bin2hex(random_bytes(4));
        $serializer = new Serializer([new ArrayDenormalizer(), new ObjectNormalizer()], [new JsonEncoder()]);
        $this->episodes = new EpisodeRepository(new Filesystem(), $serializer, new MockClock('2026-11-04 10:00:00'), $this->dir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testStoresTheBriefOfAnEpisode(): void
    {
        $id = $this->episodes->nextId();
        $this->episodes->saveBrief($id, Fixtures::brief());

        self::assertMatchesRegularExpression('/^20261104-100000-[0-9a-f]{4}$/', $id);
        self::assertFileExists("{$this->dir}/{$id}/brief.json");
        self::assertSame('Worlds 2026', $this->episodes->brief($id)->topic);
    }

    #[TestWith(['../../../etc/passwd'])]
    #[TestWith(['20261104-100000-abcd/../../x'])]
    #[TestWith([''])]
    public function testRejectsIdsThatAreNotEpisodeIds(string $id): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not an episode id');

        $this->episodes->script($id);
    }

    public function testSaysWhenAnEpisodeDoesNotExist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Episode "20261104-100000-abcd" has no script.json.');

        $this->episodes->script('20261104-100000-abcd');
    }
}
