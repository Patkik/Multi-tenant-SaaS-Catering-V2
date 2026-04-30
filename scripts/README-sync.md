# Sync scripts

This folder contains helpers to sync the repository on another machine and ensure it updates to the latest release by default.

Files
- `scripts/sync.sh` — Bash script for Unix/macOS/WSL.
- `scripts/sync.ps1` — PowerShell script for Windows.

Behavior
- By default the scripts fetch `origin` and tags, determine the latest tag (sorted by semver-like ordering), and check out that tag.
- If no tags exist, they fall back to pulling the `main` branch.
- The scripts refuse to proceed if there are uncommitted changes unless `--force` (Bash) or `-Force` (PowerShell) is provided.

Examples

Unix / WSL / macOS
```bash
# checkout latest release (default)
./scripts/sync.sh

# pull latest from a branch instead
./scripts/sync.sh --branch develop --branch-only

# force discard local changes and pull
./scripts/sync.sh --force --branch develop --branch-only
```

Windows PowerShell
```powershell
# checkout latest release (default)
.\n+\scripts\sync.ps1 -Release

# pull latest from branch
.
\scripts\sync.ps1 -Branch feature/my-branch

# force discard local changes
.
\scripts\sync.ps1 -Branch dev -Force
```

Verification
- After running, confirm current HEAD or tag:

```bash
git rev-parse --abbrev-ref HEAD || git describe --tags --exact-match
```

Notes
- These scripts operate locally and require `git` to be available and network access to `origin`.
- If you want the script to always update to the latest commit on `main` instead of the latest tag, invoke with `--branch main --branch-only`.
