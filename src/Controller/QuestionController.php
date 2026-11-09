<?php

declare(strict_types=1);

namespace App\Controller;

use App\Mailbox\AskQuestion;
use App\Mailbox\Mailbox;
use App\Mailbox\Transcriber;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Listeners ask the hosts a question by voice; it waits in the mailbox for the
 * editor (the MCP client) to put it in an episode. The audio is not kept.
 */
final class QuestionController extends AbstractController
{
    public function __construct(
        private readonly Transcriber $transcriber,
        private readonly Mailbox $mailbox,
    ) {
    }

    #[Route('/questions', name: 'app_question_ask', methods: ['POST'], format: 'json')]
    public function ask(
        // what browsers record: webm (Chrome, Firefox) or mp4 (Safari), sometimes detected as video
        #[MapUploadedFile([new Assert\File(maxSize: '10M', mimeTypes: ['audio/*', 'video/webm', 'video/mp4'])])]
        UploadedFile $audio,
        #[MapRequestPayload] AskQuestion $form = new AskQuestion(),
    ): JsonResponse {
        try {
            $text = $this->transcriber->transcribe($audio->getPathname());
        } catch (ExceptionInterface) {
            return $this->json(['error' => 'Sorry, the hosts could not listen to it. Try again?'], Response::HTTP_BAD_GATEWAY);
        }

        if ('' === $text) {
            return $this->json(['error' => 'The hosts heard nothing. Is your microphone on?'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $question = $this->mailbox->post($text, '' === trim((string) $form->name) ? null : trim((string) $form->name));

        return $this->json(['question' => $question->text], Response::HTTP_CREATED);
    }
}
