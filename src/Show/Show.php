<?php

declare(strict_types=1);

namespace App\Show;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The podcast and its hosts, as configured in the "app.show" parameter. An episode
 * is a dialogue: two of the hosts make it, the show's usual duo unless its editor
 * chooses others.
 */
final readonly class Show
{
    public string $title;
    public string $description;
    public string $author;
    public string $language;
    /** @var array<string, Host> every host an editor can choose */
    public array $hosts;
    /** @var list<string> the ids of the usual duo */
    public array $cast;

    /**
     * @param array{title: string, description: string, author: string, language: string, cast: list<string>, hosts: array<string, array{name: string, persona: string, voices: array<string, string>}>} $config
     */
    public function __construct(#[Autowire(param: 'app.show')] array $config)
    {
        $this->title = $config['title'];
        $this->description = $config['description'];
        $this->author = $config['author'];
        $this->language = $config['language'];
        $hosts = [];
        foreach ($config['hosts'] as $id => $host) {
            $hosts[$id] = new Host($id, $host['name'], $host['persona'], $host['voices']);
        }
        $this->hosts = $hosts;
        $this->cast = $config['cast'];
    }

    /**
     * The two hosts of an episode.
     *
     * @param list<string> $ids the hosts its editor chose, none for the usual duo
     *
     * @return array<string, Host>
     *
     * @throws InvalidArgumentException when these are not two hosts of the show
     */
    public function cast(array $ids = []): array
    {
        $ids = array_values(array_unique(array_map(strtolower(...), [] === $ids ? $this->cast : $ids)));
        if ([] !== $unknown = array_diff($ids, array_keys($this->hosts))) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a host of the show (hosts: %s).', implode('", "', $unknown), implode(', ', array_keys($this->hosts))));
        }
        if (2 !== \count($ids)) {
            throw new InvalidArgumentException(\sprintf('An episode has two hosts, choose two among %s.', implode(', ', array_keys($this->hosts))));
        }

        return [$ids[0] => $this->hosts[$ids[0]], $ids[1] => $this->hosts[$ids[1]]];
    }
}
