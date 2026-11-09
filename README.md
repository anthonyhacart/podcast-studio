# Podcast Studio

> Ask your AI assistant for a podcast episode on anything. It gathers the sources, two AI hosts write a sourced dialogue, you review the script in the conversation, and the voiced episode lands in a real podcast feed. Listeners ask the hosts questions by voice; the hosts remember what they said.

Built for the [Symfony AI & MCP Hackathon](https://symfony.com/hackathon) (November 2026). The whole show runs on [Mistral](https://mistral.ai): one API key writes, voices, listens and remembers.

🎬 **Demo video (90 s)**: _link_

## Run it

### With the Symfony CLI

Requirements: PHP 8.4+ (`pdo_pgsql`), Composer, the [Symfony CLI](https://symfony.com/download), Docker for Postgres.

```bash
git clone <repo-url> podcast-studio && cd podcast-studio
composer install
docker compose up -d database           # Postgres + pgvector: the Symfony CLI exposes its DATABASE_URL
symfony console ai:store:setup archive  # the table of the show's memory
symfony serve -d

# an episode replayed from recorded API responses: no API key needed
CASSETTE=fixtures/cassettes/worlds-2026.json symfony console app:episode fixtures/briefs/worlds-2026.json
symfony open:local
```

With Colima instead of Docker Desktop, export `DOCKER_HOST=unix://$HOME/.colima/default/docker.sock` so the Symfony CLI finds the database.

### With Docker only

```bash
make install   # FrankenPHP + Postgres
make demo      # the same replayed episode
```

Open http://localhost:8002.

### Live

Put a [Mistral](https://console.mistral.ai/) key in `.env.local` (never committed), then connect an MCP client.

```dotenv
MISTRAL_API_KEY=
```

## Use it from an MCP client

| Tool | What it does |
| --- | --- |
| `listener_questions` | Lists the questions listeners recorded on the show's page |
| `draft_episode` | Writes the script from a topic, the sources the client gathered and, optionally, listener questions |
| `revise_episode` | Rewrites it following the editor's instructions |
| `publish_episode` | Voices the approved script and publishes it to the feed |
| `search_episodes` | Searches what the hosts said in past episodes, by meaning |

```bash
claude mcp add --transport http podcast-studio http://127.0.0.1:8000/mcp   # Claude Code; port 8002 with Docker only
```

Claude Desktop speaks STDIO: run `bin/console mcp:server` with `APP_DEBUG=0` (with Docker only: `docker compose exec -T -e APP_DEBUG=0 php php bin/console mcp:server`).

Then ask: *"Check the listener questions and make a 2-minute episode about the Worlds final. Look for reliable sources first."* Read the script, ask for changes, approve, listen.

## How the AI is used

**The MCP client is the editor-in-chief, the server is the studio.** The client brings the sources (web search, files) and reviews the script with you; the server owns the show: hosts, rules, voices, memory.

- **A writer agent** turns the brief into a structured script: lines with host, emotion, cited sources, chapters and a summary. Rules in code check it (known hosts, existing sources, length, no "today" in an episode heard later) and send it back once with the problems.
- **The show remembers.** Published lines are embedded into pgvector. The writer has its own tool, `search_past_episodes`, and decides what to look up, so the hosts refer back instead of repeating themselves.
- **Listeners have a voice.** A question recorded on the page is transcribed and waits for an episode; a host reads it out with the listener's name.
- **Voices per emotion.** Each line is voiced with the host's voice for its emotion, all lines at once, then joined in PHP (no ffmpeg). When the voice service's moderation refuses a line, the writer rephrases that line only.
- **Podcast-ready.** RSS feed with transcript (VTT) and chapters (Podcasting 2.0).

A few choices:

- **Facts are checked by the client**, which gathered the sources, during the review. A server-side fact-checking agent was tried and dropped: it flagged faithful paraphrases and missed real errors.
- **Models are pinned**: Mistral's `-latest` aliases can point to older models.
- **Episodes and questions are files** (`var/episodes/`, `var/questions/`); the memory is derived from them: `symfony console ai:store:index archive` rebuilds it.
- **A replay is exact**: with `CASSETTE` set, the clock stops on the recording day and the memory starts empty, so the requests match the recording (`src/Replay/`).

## Symfony AI inside

| Component | Used for |
| --- | --- |
| MCP Bundle + PHP MCP SDK | Five tools, server `instructions` for any client, readable tool errors, HTTP and STDIO |
| AI Bundle, Agent | The `writer` agent (prompt in `config/prompts/writer.md`) and its toolbox (`#[AsTool]`) |
| Platform, structured output | `Script` and `Line` DTOs as the agent's response format |
| Platform, Mistral bridge | Chat (`mistral-medium-2604`), text to speech (`voxtral-mini-tts-3-0`), speech to text (`voxtral-mini-transcribe-3-0`), embeddings (`mistral-embed`) |
| Store, Postgres bridge | The show's memory: a vectorizer, an indexer with a custom loader, pgvector |
| Platform test replay | Cassettes: the demo and the end-to-end test run without a key |

Also: Symfony UX Toolkit (Bootstrap kit), Stimulus (microphone, chapters), AssetMapper, Clock, Uid, Serializer, Validator (`#[MapUploadedFile]`).

## Contributions to Symfony AI

Found while building this project:

- [symfony/ai#2873](https://github.com/symfony/ai/pull/2873): Voxtral text to speech in the Mistral bridge (merged). Not in a tagged release yet, so the project requires `symfony/ai-*` at `dev-main`.
- [symfony/ai#2894](https://github.com/symfony/ai/pull/2894): the Postgres store's hybrid query no longer drops documents that share no keyword with the query.

## Quality

```bash
make qa        # coding standards, PHPStan (max), tests, including a full episode replayed from cassettes
make cassette  # records the cassettes again, live
```

## License

MIT
