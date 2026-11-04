# podcast-studio

> One sentence: what it does and why it is fun or useful.

Built for the [Symfony AI & MCP Hackathon](https://symfony.com/hackathon) (November 2026).

🎬 **Demo video (90 s)**: _link_

## Run it in 5 minutes

Requirements: Docker with Compose, `make`.

```bash
git clone <repo-url> podcast-studio && cd podcast-studio
make install
make demo        # replay mode: no API key needed
```

Open http://localhost:8002.

### Live mode (optional)

Put your keys in `.env.local` (never committed):

```dotenv
OPENAI_API_KEY=
ANTHROPIC_API_KEY=
MISTRAL_API_KEY=
HUGGINGFACE_API_KEY=
ELEVEN_LABS_API_KEY=
```

Fully local, no key at all: `make ollama-pull m=<model>` then …

## What it unlocked

What Symfony AI and the PHP MCP SDK made possible here, that was not possible (or not reasonable) before. One claim, then where to see it in the code.

## How it works

Short architecture overview: main components, data flow, where the LLM is (and is not) in charge.

| Symfony AI / MCP piece | Used for | Where |
| --- | --- | --- |
| Platform | | |
| Agent / Toolbox | | |
| Structured output | | |
| MCP server / MCP App | | |

## Using it from an MCP client

Claude Desktop / VS Code configuration snippet and the tools exposed.

## Quality

```bash
make qa     # coding standards + PHPStan (max) + tests
make e2e    # browser tests with Panther
```

## Feedback to upstream

Issues and PRs opened on `symfony/ai` and `modelcontextprotocol/php-sdk` while building this.

## License

MIT
