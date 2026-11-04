<?php

declare(strict_types=1);

namespace App\Controller;

use App\Episode\Brief;
use App\Episode\EpisodeRepository;
use App\Episode\Published;
use App\Episode\Script;
use App\Show\Show;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class PodcastController extends AbstractController
{
    private const ID = '\d{8}-\d{6}-[0-9a-f]{4}';

    public function __construct(
        private readonly EpisodeRepository $episodes,
        private readonly Show $show,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('podcast/home.html.twig', ['show' => $this->show, 'episodes' => $this->episodes()]);
    }

    #[Route('/feed.xml', name: 'app_feed', methods: ['GET'], format: 'xml')]
    public function feed(): Response
    {
        $response = $this->render('podcast/feed.xml.twig', ['show' => $this->show, 'episodes' => $this->episodes()]);
        $response->headers->set('Content-Type', 'application/rss+xml; charset=UTF-8');

        return $response;
    }

    #[Route('/episodes/{id}.mp3', name: 'app_episode_audio', requirements: ['id' => self::ID], methods: ['GET', 'HEAD'])]
    public function audio(string $id): BinaryFileResponse
    {
        // BinaryFileResponse answers Range requests: podcast apps need them to seek
        $response = $this->file($this->published(fn () => $this->episodes->audioPath($id)), "{$id}.mp3", ResponseHeaderBag::DISPOSITION_INLINE);
        $response->headers->set('Content-Type', 'audio/mpeg');

        return $response;
    }

    #[Route('/episodes/{id}.vtt', name: 'app_episode_transcript', requirements: ['id' => self::ID], methods: ['GET'])]
    public function transcript(string $id): BinaryFileResponse
    {
        $response = new BinaryFileResponse($this->published(fn () => $this->episodes->transcriptPath($id)));
        $response->headers->set('Content-Type', 'text/vtt; charset=UTF-8');

        return $response;
    }

    /**
     * @return list<array{episode: Published, script: Script, brief: Brief, bytes: int}>
     */
    private function episodes(): array
    {
        $episodes = [];
        foreach ($this->episodes->published() as $episode) {
            $episodes[] = [
                'episode' => $episode,
                'script' => $this->episodes->script($episode->id),
                'brief' => $this->episodes->brief($episode->id),
                'bytes' => (int) filesize($this->episodes->audioPath($episode->id)),
            ];
        }

        return $episodes;
    }

    /**
     * @param callable(): string $path
     */
    private function published(callable $path): string
    {
        try {
            return $path();
        } catch (InvalidArgumentException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }
    }
}
