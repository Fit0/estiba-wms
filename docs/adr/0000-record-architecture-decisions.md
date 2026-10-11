# 0000. Record architecture decisions

- Status: Accepted
- Date: 2026-10-12

## Context

Estiba makes architecture decisions that future readers (including reviewers and my future self) need to understand: why a pattern was chosen, which alternatives were considered and what trade-offs were accepted.

## Decision

Record every significant architecture decision as an Architecture Decision Record (ADR), following Michael Nygard's format, in `docs/adr/NNNN-title.md`. ADRs are immutable once accepted; a later ADR supersedes an earlier one instead of editing it.

## Consequences

- Decisions and their reasoning live next to the code and are reviewed in pull requests.
- New ADRs start from [`template.md`](template.md).
