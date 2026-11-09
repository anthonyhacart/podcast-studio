<?php

declare(strict_types=1);

namespace App\Replay;

use Symfony\AI\Store\Document\VectorDocumentInterface;
use Symfony\AI\Store\InMemory\Store as InMemoryStore;
use Symfony\AI\Store\Query\QueryInterface;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The store of the show's memory: Postgres. With CASSETTE set, an empty store in
 * memory: what the writer finds in the memory goes into its requests, and a replay
 * must send the very requests the recording sent, from an empty memory.
 */
#[AsAlias('app.archive')]
final readonly class ReplayableStore implements StoreInterface
{
    private StoreInterface $store;

    public function __construct(
        #[Autowire(service: 'ai.store.postgres.archive', lazy: true)] StoreInterface $archive,
        #[Autowire(env: 'default::CASSETTE')] ?string $cassette = null,
    ) {
        $this->store = null === $cassette || '' === $cassette ? $archive : new InMemoryStore();
    }

    public function add(VectorDocumentInterface|array $documents): void
    {
        $this->store->add($documents);
    }

    public function remove(string|array $ids, array $options = []): void
    {
        $this->store->remove($ids, $options);
    }

    public function clear(array $options = []): void
    {
        $this->store->clear($options);
    }

    public function query(QueryInterface $query, array $options = []): iterable
    {
        return $this->store->query($query, $options);
    }

    public function supports(string $queryClass): bool
    {
        return $this->store->supports($queryClass);
    }

    public function count(): int
    {
        return \count($this->store);
    }
}
