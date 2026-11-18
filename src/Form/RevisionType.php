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
            'attr' => ['rows' => 2, 'placeholder' => 'Shorter intro, and Jane should doubt the favourites more'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Revision::class]);
    }
}
