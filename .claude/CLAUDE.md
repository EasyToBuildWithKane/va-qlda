# VA-Workspace — Claude Code (`.claude/`)

Full project instructions: [`../CLAUDE.md`](../CLAUDE.md).

---

## Memory — project docs

Project docs live in `_dev/`. Read relevant files before answering about commands, workflows, or project conventions.

_dev/README.md          — quick reference index
_dev/commands.md        — all CLI commands
_dev/workflows.md       — dev, PR, deploy, hotfix flows
_dev/conventions.md     — commit format, naming rules
_dev/ci-cd.md           — GitHub Actions explained
_dev/testing.md         — Playwright E2E guide
_dev/realtime.md        — Socket.IO realtime comments (Node server + Redis)
_dev/troubleshooting.md — common errors + fixes
_dev/vi/               — Vietnamese explanations (see _dev/vi/README.md for full index)
  tong-quan.md         — overview
  lenh-cli.md          — commands
  quy-trinh.md         — workflows
  quy-uoc.md           — conventions
  ci-cd.md             — CI/CD
  kiem-thu.md          — Playwright testing
  realtime.md          — realtime
  loi-thuong-gap.md   — troubleshooting

### How to use

1. User asks about a command → read `_dev/commands.md` first
2. User asks about workflow  → read `_dev/workflows.md` first
3. User reports an error    → check `_dev/troubleshooting.md`
4. User asks in Vietnamese for explanation → read `_dev/vi/` first; update EN canonical file if facts change, then sync VI

### Keeping _dev/ up to date

When implementing something new (new npm script, new CI job, new Husky hook), always update the relevant `_dev/` file in the same response. Treat `_dev/` as the project's single source of truth for operational knowledge. Add or update `_dev/vi/` when the team needs a Vietnamese explanation.
