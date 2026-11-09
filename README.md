# Podcast Studio

> Ask your AI assistant for a podcast episode on anything. It gathers the sources, two AI hosts write a sourced dialogue, you review the script in the conversation, and the voiced episode lands in a real podcast feed.

Built for the [Symfony AI & MCP Hackathon](https://symfony.com/hackathon) (November 2026).

🎬 **Demo video (90 s)**: _link_

## Run it in 5 minutes

Requirements: Docker with Compose, `make`.

```bash
git clone <repo-url> podcast-studio && cd podcast-studio
make install
make demo        # replays recorded API responses: no API key needed
```

Open http://localhost:8002 and play the episode. The feed for podcast apps is at http://localhost:8002/feed.xml.

The demo replays a recording made from an empty memory, so its hosts have no past episode to refer back to, and its episode is not added to the memory (`make console c="ai:store:index archive"` adds it).

### Live mode

One key does it all, for writing and voices: put a [Mistral](https://console.mistral.ai/) key in `.env.local` (never committed).

```dotenv
MISTRAL_API_KEY=
```

Then make an episode from a brief file, without review:

```bash
make console c="app:episode fixtures/briefs/worlds-2026.json"
```

Or, better, from an MCP client: see below.

## Using it from an MCP client

The app is an MCP server with four tools:

| Tool | What it does |
| --- | --- |
| `draft_episode` | Writes the script from a topic and the sources the client gathered |
| `revise_episode` | Rewrites it following the editor's instructions |
| `publish_episode` | Voices the approved script and publishes the episode to the feed |
| `search_episodes` | Searches what the hosts said in past episodes, by meaning |

**Claude Code** (HTTP, the stack must be running):

```bash
claude mcp add --transport http podcast-studio http://localhost:8002/mcp
```

**Claude Desktop** (STDIO through Docker), in `claude_desktop_config.json` under `mcpServers`:

```json
"podcast-studio": {
  "command": "docker",
  "args": ["compose", "-f", "/absolute/path/to/podcast-studio/compose.yaml",
           "exec", "-T", "-e", "APP_DEBUG=0", "php", "php", "bin/console", "mcp:server"]
}
```

Then ask: *"Make me a 2-minute episode about the League of Legends Worlds quarterfinals. Look for reliable sources first."* Read the script, ask for changes ("cut the prize money part, Jane more sarcastic"), approve, listen.

## What it unlocked

**The MCP client is the editor-in-chief; the server is the studio.** Each side does what it is best at:

- **The client brings the material.** Claude has web search, your files, your repository. It sends the server a brief *with its sources*; the hosts only use those facts. The server never browses the web.
- **The review is a conversation.** The server returns the script to the client, which compares it with the sources it gathered, flags what they do not state, and revises with you until you approve. The server's `instructions` tell any MCP client how to play that role.
- **The studio is consistent whatever the client.** The show format, the hosts' personalities, the rules and the voices live on the server, in config.
- **The show remembers.** Every published line is embedded into a vector store. The writer agent has a `search_past_episodes` tool and decides itself what to look up (the topic, then each team or person in the brief), so the hosts refer back to what they said instead of repeating it. The client searches the same memory with `search_episodes`.

## How it works

```
MCP client ──draft_episode(topic, sources)──▶ Studio ──▶ "writer" agent (Mistral, structured output)
           ◀──────── script, line by line ───┘      │      └─ tool: search_past_episodes ──▶ pgvector
                                                    └──▶ ScriptChecker (rules in code, one retry)
           ──revise_episode(id, instructions)─▶ same loop
           ──publish_episode(id)──────────────▶ Voice (Voxtral TTS, one voice per host and emotion)
                                                  ──▶ Mp3Joiner ──▶ episode.mp3 + transcript.vtt
                                                  ──▶ "archive" indexer (Mistral embeddings) ──▶ pgvector
           ──search_episodes(query)───────────▶ same memory
Browser / podcast app ◀── /, /feed.xml, /episodes/{id}.mp3, /episodes/{id}.vtt
```

| Symfony AI / MCP piece | Used for | Where |
| --- | --- | --- |
| AiBundle agent | The `writer` agent, its prompt in a file | `config/packages/ai.yaml`, `config/prompts/writer.md` |
| Agent toolbox | `search_past_episodes`, the writer's own tool, used during structured output | `src/Archive/PastEpisodes.php` |
| Store, Postgres bridge | The show's memory: one document per line, embedded with `mistral-embed`, indexed on publish | `src/Archive/EpisodeLoader.php`, `config/packages/ai.yaml` |
| Structured output | A `Script` of `Line`s: host, emotion, text, cited sources | `src/Episode/Script.php`, `Line.php` |
| Platform, Mistral bridge | Text to speech with Voxtral, all lines voiced concurrently | `src/Episode/Voice.php` |
| Replay cassettes | `make demo` and the end-to-end test, without a key | `src/Replay/` |
| MCP server (bundle + SDK) | The four tools, server `instructions`, readable tool errors | `src/Mcp/EpisodeTools.php`, `config/packages/mcp.yaml` |

A few choices worth knowing:

- **The code knows nothing about any topic.** The rules it checks are about structure: known hosts and emotions, sources that exist, length, and no "today" or "tomorrow" (people listen later).
- **Facts are checked by the client, not by a second agent.** A server-side fact-checking agent was tried and dropped: with the models at hand it flagged faithful paraphrases and missed real errors. The client, which gathered the sources, does it better during the review.
- **The voice service's moderation sometimes refuses a harmless line.** On publish, the writer rephrases that line only, the episode is voiced again, and the client is told what changed.
- **Models are pinned.** Mistral's `-latest` aliases can point to older models (`voxtral-mini-tts-latest` is the v1 model that rejects the current voices; `mistral-large-latest` is 2512). The writer uses `mistral-medium-2604`, the voices `voxtral-mini-tts-3-0`, the memory `mistral-embed`.
- **No ffmpeg.** Voxtral returns MP3 clips with the same format; `Mp3Joiner` concatenates their frames in PHP and adds up their duration, which also gives the transcript its timings.
- **Episodes are files, the memory is Postgres.** One folder per episode in `var/episodes/`: `brief.json`, `script.json`, `episode.mp3`, `transcript.vtt`, `episode.json`. The memory is derived from them: `make console c="ai:store:index archive"` rebuilds it.
- **The memory is searched by meaning only.** The Postgres store answers the bundle's `Retriever` with a hybrid query that keeps only lines sharing a keyword with the query; `PastEpisodes` sends a vector query itself.
- **A replay is exact.** With `CASSETTE` set, the clock stops on the recording day and the memory starts empty, in memory: the requests carry the date and what the writer finds, and they must match the recording (`src/Replay/`).

The show (title, hosts, personas, voices per emotion) is configured in the `app.show` parameter of `config/services.yaml`.

## Quality

```bash
make qa        # coding standards + PHPStan (max) + tests, including a full episode replayed from cassettes
make cassette  # records that cassette again, live
make e2e    # browser tests with Panther
```

## Feedback to upstream

- Voxtral text to speech in the Mistral bridge was contributed while building this: [symfony/ai#2873](https://github.com/symfony/ai/pull/2873) (merged). It is not in a tagged release yet, so the project requires the `symfony/ai-*` packages at `dev-main`.

## License

MIT
