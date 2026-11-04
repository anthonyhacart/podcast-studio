<?php

declare(strict_types=1);

namespace App\Show;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The podcast and its hosts, as configured in the "app.show" parameter.
 */
final readonly class Show
{
    public string $title;
    public string $language;
    /** @var array<string, Host> */
    public array $hosts;

    /**
     * @param array{title: string, language: string, hosts: array<string, array{name: string, persona: string, voices: array<string, string>}>} $config
     */
    public function __construct(#[Autowire(param: 'app.show')] array $config)
    {
        $this->title = $config['title'];
        $this->language = $config['language'];
        $hosts = [];
        foreach ($config['hosts'] as $id => $host) {
            $hosts[$id] = new Host($id, $host['name'], $host['persona'], $host['voices']);
        }
        $this->hosts = $hosts;
    }
}
