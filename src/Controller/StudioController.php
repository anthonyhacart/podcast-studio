<?php

declare(strict_types=1);

namespace App\Controller;

use App\Episode\AlreadyPublishingException;
use App\Episode\Brief;
use App\Episode\Briefing;
use App\Episode\EpisodeRepository;
use App\Episode\InvalidScriptException;
use App\Episode\NewEpisode;
use App\Episode\NothingFoundException;
use App\Episode\Researcher;
use App\Episode\Revision;
use App\Episode\Script;
use App\Episode\Studio;
use App\Episode\UnreadableSourceException;
use App\Episode\UnspeakableLineException;
use App\Form\NewEpisodeType;
use App\Form\PublishType;
use App\Form\RevisionType;
use App\Show\Show;
use InvalidArgumentException;
use Symfony\AI\Agent\Exception\ExceptionInterface as AgentException;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The control room in the browser, for an editor without an MCP client: the same
 * draft, review and publish steps as the MCP tools, with links read as sources, given
 * by the editor or found by the "researcher" agent.
 */
#[Route('/studio', name: 'app_studio_')]
final class StudioController extends AbstractController
{
    private const ID = '\d{8}-\d{6}-[0-9a-f]{4}';
    private const UNAVAILABLE = 'The hosts are not available right now. Try again in a moment.';

    public function __construct(
        private readonly Studio $studio,
        private readonly EpisodeRepository $episodes,
        private readonly Briefing $briefing,
        private readonly Researcher $researcher,
        private readonly Show $show,
    ) {
    }

    #[Route('', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        // the hosts search for their sources unless the editor unticks it
        $episode = new NewEpisode(search: $this->researcher->isAvailable());
        $form = $this->createForm(NewEpisodeType::class, $episode)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                [$id] = $this->studio->draft($this->briefing->brief($episode));

                return $this->redirectToRoute('app_studio_review', ['id' => $id], Response::HTTP_SEE_OTHER);
            } catch (UnreadableSourceException $e) {
                $form->get('links')->addError(new FormError($e->getMessage()));
            } catch (NothingFoundException|InvalidScriptException $e) {
                $form->addError(new FormError($e->getMessage()));
            } catch (AgentException|PlatformException) {
                $form->addError(new FormError(self::UNAVAILABLE));
            }
        }

        return $this->render('studio/new.html.twig', ['show' => $this->show, 'form' => $form]);
    }

    #[Route('/{id}', name: 'review', requirements: ['id' => self::ID], methods: ['GET', 'POST'])]
    public function review(string $id, Request $request): Response
    {
        [$brief, $script] = $this->draft($id);
        if ($this->episodes->isPublished($id)) {
            return $this->redirectToRoute('app_home');
        }

        $form = $this->createForm(RevisionType::class, $revision = new Revision())->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->studio->revise($id, $revision->instructions);

                return $this->redirectToRoute('app_studio_review', ['id' => $id], Response::HTTP_SEE_OTHER);
            } catch (InvalidScriptException $e) {
                $form->addError(new FormError($e->getMessage()));
            } catch (AgentException|PlatformException) {
                $form->addError(new FormError(self::UNAVAILABLE));
            }
        }

        return $this->render('studio/review.html.twig', ['show' => $this->show, 'brief' => $brief, 'script' => $script, 'form' => $form, 'publish' => $this->publishForm($id)]);
    }

    #[Route('/{id}/publish', name: 'publish', requirements: ['id' => self::ID], methods: ['POST'])]
    public function publish(string $id, Request $request): Response
    {
        $this->draft($id);
        if ($this->episodes->isPublished($id)) {
            return $this->redirectToRoute('app_home', status: Response::HTTP_SEE_OTHER);
        }

        $form = $this->publishForm($id)->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('danger', 'The episode was not recorded, try again.');

            return $this->redirectToRoute('app_studio_review', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        try {
            $episode = $this->studio->publish($id);
        } catch (AlreadyPublishingException|UnspeakableLineException|InvalidScriptException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('app_studio_review', ['id' => $id], Response::HTTP_SEE_OTHER);
        } catch (AgentException|PlatformException) {
            $this->addFlash('danger', self::UNAVAILABLE);

            return $this->redirectToRoute('app_studio_review', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', \sprintf('"%s" is published.', $episode->title).(null === $episode->rephrased ? '' : " {$episode->rephrased}."));

        return $this->redirectToRoute('app_home', status: Response::HTTP_SEE_OTHER);
    }

    /**
     * @return FormInterface<null>
     */
    private function publishForm(string $id): FormInterface
    {
        return $this->createForm(PublishType::class, options: ['action' => $this->generateUrl('app_studio_publish', ['id' => $id])]);
    }

    /**
     * Turns an unknown draft into a 404.
     *
     * @return array{Brief, Script}
     */
    private function draft(string $id): array
    {
        try {
            return [$this->episodes->brief($id), $this->episodes->script($id)];
        } catch (InvalidArgumentException $e) {
            throw $this->createNotFoundException($e->getMessage(), $e);
        }
    }
}
