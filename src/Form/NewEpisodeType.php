<?php

declare(strict_types=1);

namespace App\Form;

use App\Episode\NewEpisode;
use App\Episode\Researcher;
use App\Episode\SourceReader;
use App\Mailbox\Mailbox;
use App\Mailbox\Question;
use App\Show\Show;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<NewEpisode>
 */
final class NewEpisodeType extends AbstractType
{
    public function __construct(
        private readonly Mailbox $mailbox,
        private readonly Researcher $researcher,
        private readonly Show $show,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // no type for the short texts: Symfony guesses it, with "required" and "maxlength", from the constraints
        // of NewEpisode, so the browser checks them before sending the form
        $builder->add('topic', null, [
            'label' => 'What is the episode about?',
            'empty_data' => '',
            'attr' => ['placeholder' => 'Worlds 2026: the knockout stage starts'],
        ]);

        if ($this->researcher->isAvailable()) {
            $builder->add('search', CheckboxType::class, [
                'label' => 'Let the hosts search the web for their sources',
                'help' => 'They pick up to five pages, listed next to the script for you to check.',
                'required' => false,
                'attr' => ['data-sources-target' => 'search', 'data-action' => 'sources#toggle'],
            ]);
        }

        $builder
            ->add('links', TextareaType::class, [
                'label' => 'Links the hosts should read',
                'help' => 'One per line, five at most. The hosts only use the facts found on these pages.',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'https://en.wikipedia.org/wiki/…', 'data-action' => 'input->sources#check'],
                // hidden while the hosts search themselves (assets/controllers/sources_controller.js)
                'row_attr' => ['data-sources-target' => 'links'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Your own notes',
                'help' => 'Facts to add, or the text of a page the hosts cannot read.',
                'required' => false,
                'attr' => ['rows' => 3, 'maxlength' => SourceReader::MAX_CHARACTERS, 'data-sources-target' => 'notes', 'data-action' => 'input->sources#check'],
            ])
            ->add('minutes', ChoiceType::class, [
                'label' => 'Length',
                'choices' => ['1 minute' => 1, '2 minutes' => 2, '3 minutes' => 3, '4 minutes' => 4, '5 minutes' => 5],
            ])
            ->add('tone', null, [
                'attr' => ['placeholder' => 'fun, for fans who missed the news'],
            ])
            ->add('hosts', ChoiceType::class, [
                'help' => 'Two of them make the episode, each with their own voice.',
                'choices' => array_keys($this->show->hosts),
                'choice_label' => fn (string $id): string => \sprintf('%s · %s', $this->show->hosts[$id]->name, $this->show->hosts[$id]->persona),
                'multiple' => true,
                'expanded' => true,
                // two and no more, checked in the browser too (assets/controllers/pick_controller.js)
                'attr' => ['data-controller' => 'pick', 'data-action' => 'change->pick#check', 'data-pick-count-value' => 2, 'data-pick-message-value' => NewEpisode::TWO_HOSTS],
            ])
        ;

        // one link per line
        $builder->get('links')->addModelTransformer(new CallbackTransformer(self::lines(...), self::links(...)));

        if ([] !== $questions = $this->mailbox->waiting()) {
            $builder->add('questions', ChoiceType::class, [
                'label' => 'Listener questions to answer on air',
                'choices' => $questions,
                'choice_value' => 'id',
                'choice_label' => static fn (Question $question): string => \sprintf('%s: %s', $question->name ?? 'A listener', $question->text),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => NewEpisode::class]);
    }

    /**
     * @param list<string> $links
     */
    private static function lines(array $links): string
    {
        return implode("\n", $links);
    }

    /**
     * @return list<string>
     */
    private static function links(?string $lines): array
    {
        return array_values(array_filter(array_map(trim(...), preg_split('/\R/', (string) $lines) ?: []), static fn (string $link) => '' !== $link));
    }
}
