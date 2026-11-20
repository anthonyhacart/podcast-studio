<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;

/**
 * The "Record and publish" button of a draft: a form without fields, for its CSRF
 * protection, since recording spends the voices.
 *
 * @extends AbstractType<null>
 */
final class PublishType extends AbstractType
{
}
