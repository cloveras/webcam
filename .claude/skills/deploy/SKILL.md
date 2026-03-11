---
name: deploy
description: Deploy changed webcam files to the server, commit to git, and push to GitHub
---

Deploy all changed files to the live server, then commit and push to GitHub.

## Steps

1. Run `git diff --name-only HEAD` and `git status --short` to identify changed and untracked files that are relevant (PHP, Python, CSS, shell scripts, JSON data files, CLAUDE.md — not images, .pt files, or background PNGs).

2. Rsync each changed PHP/CSS/shell file to the server using the correct path:
   - Root webcam files → `lilleviklofoten@login.domeneshop.no:www/webcam/`
   - `viktun/` files → `lilleviklofoten@login.domeneshop.no:www/webcam/viktun/`
   - `data/` JSON files → `lilleviklofoten@login.domeneshop.no:www/webcam/data/`
   - `util/` scripts → do NOT deploy (Mac-side only)
   - Use: `rsync -az -e "ssh -p 22" <file> lilleviklofoten@login.domeneshop.no:www/webcam/<file>`

3. Stage the relevant changed files with `git add` (never use `git add -A` or `git add .`).

4. Commit with a concise message describing what changed and why. End with:
   `Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>`

5. Push to GitHub: `git push`

6. Report what was deployed and committed.

## Notes

- Never deploy: `*.pt`, `data/background-*.png`, `data/people-*.json`, `viktun/data/`, loose `.jpg` files, `.env`
- If CLAUDE.md was updated, deploy it too (`www/webcam/CLAUDE.md` is NOT served publicly but keep it in sync via git)
- The server uses nginx + PHP; OPcache may cache old bytecode — if behaviour doesn't change after deploy, remind the user to wait ~5 min or touch the file again
