<?php

declare(strict_types=1);

namespace App\Form;

use App\Episode\Revision;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Revision>
 */
final class RevisionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('instructions', TextareaType::class, [
            'label' => 'What should change?',
            'empty_data' => '',
            // a textarea is not guessed from the constraints of Revision: its limit is repeated for the browser
            'attr' => ['rows' => 2, 'maxlength' => 1000, 'placeholder' => 'Shorter intro, and more doubt about the favourites'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Revision::class]);
    }
}
