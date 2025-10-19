# Active Task: PR Merge Session

- Sync local `main` with `origin/main` (done)
- Merge PRs sequentially into `main` (prefer incoming PR changes)
  - Completed: #1–#9
  - In progress: #10 (conflicts resolved by preferring PR files)
  - Next: #11–#36
- Push after each successful merge
- Delete local PR branches after merge
- Final sync: fetch/prune, verify `main` up to date

# Active Task Checklist: Laravel Boost Setup

- [ ] Create root `todo.md` with prioritized steps
- [x] Confirm project root and composer availability
- [ ] Install `laravel/boost` via Composer (dev dependency)
- [ ] Run `php artisan boost:install`
- [ ] Verify generated files and MCP server entry points
- [ ] Document MCP server registration for the editor
- [ ] Capture any installer output or warnings
- [ ] Plan next steps: blade routes audit and Tailwind migration

Notes
- Keep adherence to project rules: no CDN, no Livewire, Tailwind only.
- Do not run `php artisan serve`.


