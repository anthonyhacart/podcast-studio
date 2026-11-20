<p align="center"><img src="docs/logo.svg" alt="" width="160"></p>

# Podcast Studio

> Ask your AI assistant for a podcast episode on anything. It gathers the sources, two AI hosts write a sourced dialogue, you review the script in the conversation, and the voiced episode lands in a real podcast feed. Listeners ask the hosts questions by voice; the hosts remember what they said. No assistant? Do it in the browser, where the hosts can search the web themselves.

Built for the [Symfony AI & MCP Hackathon](https://symfony.com/hackathon) (November 2026). The whole show runs on [Mistral](https://mistral.ai): one API key writes, voices, listens and remembers. A second, optional key ([Tavily](https://tavily.com)) lets the hosts search the web.

🎬 **Demo video (90 s)**: https://youtu.be/JmGE-XR2vaU

🎧 **Try it online**: https://podcast.hacart.fr, a public demo that stays up for the hackathon. Listen, record a question for the hosts, or [make an episode in the browser](https://podcast.hacart.fr/studio): two a day for each visitor.

## Contributions to Symfony AI

Found while building this project:

- [symfony/ai#2873](https://github.com/symfony/ai/pull/2873): Voxtral text to speech in the Mistral bridge (merged).
- [symfony/ai#2894](https://github.com/symfony/ai/pull/2894): the Postgres store's hybrid query no longer drops documents that share no keyword with the query (merged).
- [symfony/ai#2966](https://github.com/symfony/ai/pull/2966): Mistral's moderation models in the Mistral bridge (open).

## Install

Two ways to run it. Both end with a first episode you can listen to **without any API key**: it is replayed from recorded API responses.

### The short way: Docker only

You need [Docker](https://docs.docker.com/get-docker/) and `make`. Nothing else: PHP, Composer and Postgres run in containers.

```bash
git clone https://github.com/anthonyhacart/podcast-studio.git && cd podcast-studio
make install   # builds the image, installs the dependencies, creates the database (a few minutes the first time)
make demo      # makes the first episode from recorded API responses
```

Open http://localhost:8002: the episode is there, with its transcript, chapters and sources.

`make` alone lists the other commands; `make down` stops everything.

### With PHP on your machine: the Symfony CLI

You need PHP 8.4 or later with the `pdo_pgsql` extension, [Composer](https://getcomposer.org), the [Symfony CLI](https://symfony.com/download), and Docker for the database only.

```bash
git clone https://github.com/anthonyhacart/podcast-studio.git && cd podcast-studio
composer install
docker compose up -d database           # Postgres with pgvector, on port 54433
symfony console ai:store:setup archive  # creates the table of the show's memory
symfony serve -d                        # the app, on http://127.0.0.1:8000

# the first episode, from recorded API responses
CASSETTE=fixtures/cassettes/worlds-2026.json symfony console app:episode fixtures/briefs/worlds-2026.json
symfony open:local
```

### Make your own episodes: add the keys

Create a file named `.env.local` at the root of the project (it is never committed):

```dotenv
MISTRAL_API_KEY=
TAVILY_API_KEY=
```

| Key | Needed for | Where to get it |
| --- | --- | --- |
| `MISTRAL_API_KEY` | Everything the hosts do: writing, voices, listening to questions, memory | [console.mistral.ai](https://console.mistral.ai/) |
| `TAVILY_API_KEY` | Optional: lets the hosts search the web for their sources in `/studio` | [tavily.com](https://tavily.com), the free plan is enough |

No restart is needed. Then make an episode [from an MCP client](#use-it-from-an-mcp-client) or [from the browser](#or-from-the-browser).

### If something goes wrong

- **A port is taken**: change `HTTP_PORT` or `POSTGRES_PORT` in `.env`, and the port in `DATABASE_URL` with it.
- **A database error**: check that the database runs (`docker compose ps`), then create its table again with `make db` (Docker only) or `symfony console ai:store:setup archive`.
- **Anything else**: the reason is in `var/log/dev.log`, and in the profiler at `/_profiler`.

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

## Or from the browser

No MCP client? Open http://127.0.0.1:8000/studio (port 8002 with Docker only): you are the editor.

1. **Give a topic**, a length, a tone, and tick the listener questions to answer.
2. **Choose where the facts come from.** With a `TAVILY_API_KEY`, "Let the hosts search the web for their sources" is ticked: the hosts find up to five pages themselves. Untick it to give your own links, one per line. Either way you can add notes of your own.
3. **Review the script**, each line next to the sources it cites, and ask for changes until it is right.
4. **Record and publish**: the episode lands on the home page and in the feed.

The same `Studio` service runs behind the MCP tools and behind these pages; the browser only adds what an MCP client does on its own, finding and reading the sources.

## How the AI is used

**The MCP client is the editor-in-chief, the server is the studio.** The client brings the sources (web search, files) and reviews the script with you; the server owns the show: hosts, rules, voices, memory. In the browser, you are the editor-in-chief, and the server reads the sources for you.

- **A writer agent** turns the brief into a structured script: lines with host, emotion, cited sources, chapters and a summary. Rules in code check it (known hosts, existing sources, length, no "today" in an episode heard later) and send it back once with the problems.
- **A researcher agent**, for an editor who brings no source: it searches the web and picks up to five pages. It only chooses: a pick the search did not return is dropped, and the writer gets the text of the pages, read by the server, not a summary by a model.
- **The show remembers.** Published lines are embedded into pgvector. The writer has its own tool, `search_past_episodes`, and decides what to look up, so the hosts refer back instead of repeating themselves.
- **Listeners have a voice.** A question recorded on the page is transcribed and waits for an episode; a host reads it out with the listener's name.
- **Voices per emotion.** Each line is voiced with the host's voice for its emotion, all lines at once, then joined in PHP (no ffmpeg). When the voice service's moderation refuses a line, the writer rephrases that line only.
- **Podcast-ready.** RSS feed with transcript (VTT) and chapters (Podcasting 2.0).

A few choices:

- **Facts are checked by the editor** during the review: the MCP client, which gathered the sources, or you in the browser, with the sources linked next to the script. A server-side fact-checking agent was tried and dropped: it flagged faithful paraphrases and missed real errors.
- **Models are pinned**: Mistral's `-latest` aliases can point to older models.
- **Episodes and questions are files** (`var/episodes/`, `var/questions/`); the memory is derived from them: `symfony console ai:store:index archive` rebuilds it.
- **A replay is exact**: with `CASSETTE` set, the clock stops on the recording day and the memory starts empty, so the requests match the recording (`src/Replay/`).

## A showcase, not a product

The project stops early on purpose: one show, two hosts, short episodes, and no login: anyone who reaches `/studio` or the MCP endpoint can spend your API keys, so run it locally. It is there to show what Symfony AI and MCP make possible in a Symfony app once it can write, speak, listen and remember. The same pieces (an agent with structured output, a voice per character and emotion, a memory, tools an assistant drives) could become:

- **Interactive stories**: each episode ends on a choice, listeners answer by voice, the next episode follows the vote.
- **A release podcast**: the assistant reads the changelog and the merged pull requests, the hosts tell what changed this week.
- **Language lessons**: dialogues at the learner's level, on the topics they ask for, with the transcript to read along.
- **Audio documentation**: two hosts walk through a guide or an RFC, with chapters to jump to.
- **A scheduled briefing**: Symfony Scheduler and a few feeds instead of a conversation, an episode every morning.

## Symfony AI inside

| Component | Used for |
| --- | --- |
| MCP Bundle + PHP MCP SDK | Five tools, server `instructions` for any client, readable tool errors, HTTP and STDIO |
| AI Bundle, Agent | Two agents, `writer` and `researcher` (prompts in `config/prompts/`), each with its toolbox |
| Agent, tools | `search_past_episodes`, the project's own tool (`#[AsTool]`), and the Tavily bridge for the web search; the sources that bridge collects (`include_sources`) limit what the researcher can pick |
| Platform, structured output | `Script`, `Line` and `Findings` DTOs as the agents' response formats |
| Platform, Mistral bridge | Chat (`mistral-medium-2604`), text to speech (`voxtral-mini-tts-3-0`), speech to text (`voxtral-mini-transcribe-3-0`), embeddings (`mistral-embed`) |
| Store, Postgres bridge | The show's memory: a vectorizer, an indexer with a custom loader, pgvector |
| Platform test replay | Cassettes: the demo and the end-to-end tests run without a key |

Also: Symfony UX Toolkit (Bootstrap kit), Stimulus (microphone, chapters), AssetMapper, Clock, Uid, Serializer, Validator (`#[MapUploadedFile]`), Form (with CSRF protection), and to read the pages given as sources, HttpClient (`NoPrivateNetworkHttpClient`: a link never reaches the private network) and DomCrawler.

## Quality

```bash
make qa        # coding standards, PHPStan (max), tests, including full episodes replayed from cassettes (over MCP and in the browser)
make cassette  # records the cassettes again, live (needs both keys in .env.local)
```

## License

MIT
