#!/usr/bin/env bash

set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

commit_message=""
pr_title=""
body_file=""

usage() {
    cat <<'USAGE'
Usage: scripts/publish-draft-pr.sh \
  --commit-message <message> \
  --pr-title <title> \
  [--body-file <path>]

Commits only the already-staged scope, pushes the current branch, and opens a
draft pull request into main. This script never merges pull requests.
USAGE
}

while (($#)); do
    case "$1" in
        --commit-message)
            [[ $# -ge 2 ]] || { usage >&2; exit 2; }
            commit_message="$2"
            shift 2
            ;;
        --pr-title)
            [[ $# -ge 2 ]] || { usage >&2; exit 2; }
            pr_title="$2"
            shift 2
            ;;
        --body-file)
            [[ $# -ge 2 ]] || { usage >&2; exit 2; }
            body_file="$2"
            shift 2
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            usage >&2
            exit 2
            ;;
    esac
done

[[ -n "$commit_message" ]] || { echo "--commit-message is required." >&2; exit 2; }
[[ -n "$pr_title" ]] || { echo "--pr-title is required." >&2; exit 2; }
[[ -z "$body_file" || -f "$body_file" ]] || { echo "PR body file not found: $body_file" >&2; exit 2; }

branch="$(git branch --show-current)"

[[ -n "$branch" ]] || { echo "Detached HEAD is not publishable." >&2; exit 1; }
[[ "$branch" != main ]] || { echo "Refusing to publish directly from main." >&2; exit 1; }
command -v gh >/dev/null || { echo "GitHub CLI (gh) is required." >&2; exit 1; }
gh auth status >/dev/null || { echo "GitHub CLI is not authenticated. Run: gh auth login" >&2; exit 1; }

git diff --check
git diff --cached --check
git diff --quiet || { echo "Unstaged tracked changes exist; stage or stash them first." >&2; exit 1; }
[[ -z "$(git ls-files --others --exclude-standard)" ]] || { echo "Untracked files exist; stage or stash them first." >&2; exit 1; }
git diff --cached --quiet && { echo "No staged changes to publish." >&2; exit 1; }

git commit -m "$commit_message"
commit_hash="$(git rev-parse HEAD)"
git push --set-upstream origin "$branch"

pr_args=(
    pr create
    --draft
    --base main
    --head "$branch"
    --title "$pr_title"
)

if [[ -n "$body_file" ]]; then
    pr_args+=(--body-file "$body_file")
else
    pr_args+=(--body "Draft created by scripts/publish-draft-pr.sh. See the commit and checks for details.")
fi

pr_url="$(gh "${pr_args[@]}")"

printf 'Commit: %s\n' "$commit_hash"
printf 'Draft PR: %s\n' "$pr_url"
