<?php

declare(strict_types=1);

namespace App\Episode;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * What the editor wants changed in a draft script.
 */
final class Revision
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 1000)]
    public string $instructions = '';
}
