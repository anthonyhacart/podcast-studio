<?php

declare(strict_types=1);

namespace App\Speech;

use Symfony\AI\Platform\Model;

/**
 * A voice model of a server that speaks OpenAI's POST /v1/audio/speech (VoiceStudio, Kokoro-FastAPI, ...).
 *
 * Temporary copy of the generic bridge's TextToSpeechModel (symfony/ai#3058): delete src/Speech once it is
 * released, and configure "ai.platform.generic.voicestudio" with "supports_text_to_speech: true" instead.
 */
final class SpeechModel extends Model
{
}
