# ShadiEvents CRM

## Version-one rules

- Registration is public; new users are inactive team members until an administrator approves them.
- Administrators see all leads. Active owners and collaborators can view and update their leads, record discussions, and manage follow-ups.
- Only administrators reassign ownership, delete/restore leads, and manage users. Owners can manage collaborators.
- Lifecycle: New, Contacted, Requirement Discussed, Proposal Sent, Negotiation, Won, Lost. Stages may be skipped; Lost requires a reason. Reopening preserves history.
- Temperature is independent: unassessed, Hot, Warm, Cold.
- Name plus email or phone is required. Wedding dates and locations may be unknown.
- For matching contact details, a separate enquiry requires both a different wedding location and different wedding dates. Unknown wedding information requires explicit review; duplicate checks never reveal another team's client data.
- Follow-ups are unlimited. Completed/cancelled records remain. Closing a lead cancels pending follow-ups; reopening does not revive them.
- All operational times display in Asia/Kolkata and are stored in UTC. Reminders are due at the scheduled time for the responsible active team member. The scheduler checks every minute. They appear on the dashboard and Notifications page when the page is loaded/refreshed.
- Email is disabled by user request. In-app notifications are delivered immediately using the database channel, without needing a queue worker. Optional email notification support is prepared but not connected.

## Deferred integrations

WhatsApp and website forms will enter through the same validation and lead workflow. No external integration is connected in version one.

## Development

Existing application code was backed up to `storage/app/crm-before-completion.zip` before the completion pass. Real leads must not be used as test fixtures. PHPUnit uses SQLite in memory.

## Running locally

Start MySQL in Laragon. From the project folder, run `composer run dev` to start Laravel, the reminder scheduler, and Vite together. Stop any separately running Laravel server or scheduler first to avoid duplicate processes. Ctrl+C stops the group. You must start this again after restarting your computer.

For an existing running Laravel server, start only `php artisan schedule:work` in a second terminal to process reminders. `php artisan crm:send-reminders` performs one immediate check. In-app updates do not require SMTP or an email provider.

Run `php artisan test --compact` for regression tests. Tests use SQLite in memory and do not touch the Laragon database. `npm run build` compiles production assets. A code backup taken before this implementation is in `storage/app/crm-before-completion.zip`.

## Everyday workflow

1. Register a team member, then approve their account from Team as an active administrator. Administrators cannot deactivate/demote themselves or remove the last active administrator.
2. Add an enquiry with name and email or phone. Enter phone numbers with a country code for consistent duplicate matching. The creator initially owns the enquiry.
3. Open an enquiry to record discussions, schedule follow-ups, edit details/status/temperature, and manage its team. Collaborators can edit the lead and manage discussions/follow-ups; only the owner or administrator manages collaborators, and only administrators reassign ownership.
4. Closing a lead as Lost requires a reason. Closing as Won or Lost cancels pending follow-ups. Reopen by choosing an open lifecycle stage; old follow-ups remain cancelled.
5. Deleting a lead is administrator-only and recoverable under Leads → View → Deleted leads. Restoration retains history and does not reactivate cancelled follow-ups.
6. Removing/deactivating a responsible member cancels pending follow-ups they can no longer access. Their history stays visible. User account deletion is intentionally unavailable.

## Later: deployment and email

Hosting and email setup are deferred by the user. Before team rollout, configure HTTPS, production APP_URL, APP_DEBUG=false, protected environment variables, database/file backups, and a persistent scheduler. Keep the existing APP_KEY when moving the database. Production scheduler: run `php artisan schedule:run` every minute from the project directory.

To enable optional queued notification emails later, configure SMTP, set CRM_EMAIL_ENABLED=true, and run a supervised `php artisan queue:work database --tries=5 --timeout=60` process. Keep the database queue on the same database connection as the app so reminder enqueueing and the sent marker commit together. Monitor `php artisan queue:failed`; retry with `php artisan queue:retry <id>`. External email delivery may repeat after ambiguous provider failures; in-app reminder creation is transactional. Password-reset emails remain in the local log until an email provider is configured.

WhatsApp and website integrations remain future work.
