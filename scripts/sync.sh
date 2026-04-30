#!/usr/bin/env bash
set -euo pipefail

DEFAULT_BRANCH="main"
BRANCH="$DEFAULT_BRANCH"
MODE="release"
FORCE=0

usage() {
  cat <<EOF
Usage: $0 [--branch BRANCH] [--branch-only] [--release] [--force]

Options:
  --branch BRANCH   Pull the specified branch (default: main)
  --branch-only     Pull branch instead of switching to latest release
  --release         Checkout the latest tag (default)
  --force           Discard uncommitted changes (use with caution)
  --help            Show this help
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --branch)
      BRANCH="$2"; shift 2;;
    --branch-only)
      MODE="branch"; shift;;
    --release)
      MODE="release"; shift;;
    --force)
      FORCE=1; shift;;
    --help)
      usage; exit 0;;
    *)
      echo "Unknown argument: $1"; usage; exit 2;;
  esac
done

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "Not a git repository. Run this inside the app folder." >&2
  exit 2
fi

# fetch origin & tags
git fetch --prune --tags origin

# check for uncommitted changes
if [ -n "$(git status --porcelain)" ]; then
  if [ "$FORCE" -eq 1 ]; then
    echo "Discarding local changes (force)."
    git reset --hard
    git clean -fd
  else
    echo "Uncommitted changes detected. Commit, stash, or run with --force to discard." >&2
    git status --porcelain
    exit 3
  fi
fi

if [ "$MODE" = "release" ]; then
  latest_tag=$(git tag --sort=-v:refname | head -n1 || true)
  if [ -z "$latest_tag" ]; then
    echo "No tags found; falling back to branch $BRANCH"
    MODE="branch"
  else
    current_tag=$(git describe --tags --exact-match 2>/dev/null || true)
    if [ "$current_tag" = "$latest_tag" ]; then
      echo "Already on latest tag $latest_tag"
    else
      echo "Checking out latest tag: $latest_tag"
      git checkout "$latest_tag"
    fi
  fi
fi

if [ "$MODE" = "branch" ]; then
  echo "Switching to branch $BRANCH and pulling latest from origin/$BRANCH"
  git fetch origin
  git checkout "$BRANCH" || git checkout -b "$BRANCH" "origin/$BRANCH"
  git pull --ff-only origin "$BRANCH" || git pull origin "$BRANCH"
fi

echo "Sync complete. Current HEAD: $(git rev-parse --abbrev-ref HEAD 2>/dev/null || git rev-parse --short HEAD)"
