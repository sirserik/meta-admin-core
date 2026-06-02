# Server Ops — backups, ops-PIN gate, storage commands

The "Server Ops" group manages a self-hosted node from the admin panel
**without giving the web process any privilege**. Same principle as the
firewall (see `docs/firewall.md`): PHP writes a request/table, a **root cron
agent** does the privileged work, and root never executes application PHP.

## Step-up ops-PIN gate

A second factor on top of admin auth for the dangerous server pages
(firewall, backups). Enable:

```env
ADMIN_OPS_PIN=482915          # set → firewall/backups require it
ADMIN_OPS_PIN_TTL=1800        # unlock lasts this many seconds
```

Empty PIN = the `admin-core.ops-pin` middleware is a no-op (pages stay behind
plain admin auth). Unlock at `/{prefix}/unlock`, lock at `/{prefix}/lock`.

## Backups (BackupFeature)

```env
FEATURE_BACKUP=true
BACKUP_PREFIX=meta                       # db dump filename prefix + retention scope
BACKUP_DB_DIR=/var/backups/meta/db
BACKUP_FILES_DIR=/var/backups/meta/files
BACKUP_SPOOL=/var/spool/admin-core-ops
BACKUP_KEEP_DAYS=30
# config/admin-core.php → backup.files_paths = ['storage/app','public','.env'] (override per site)
```

Admin page: **Сервер → Бэкапы** (`/{prefix}/backups`). It only drops JSON
requests into `{spool}/requests`; install the **root cron agent** to execute
them (DB-agnostic: pgsql / mysql / sqlite):

```bash
sudo php artisan admin-core:backup-agent-script --web-group=www-data > /usr/local/sbin/admin-core-backup-agent
sudo chmod 700 /usr/local/sbin/admin-core-backup-agent
( sudo crontab -l 2>/dev/null; \
  echo '* * * * * /usr/local/sbin/admin-core-backup-agent >> /var/log/admin-core-backup.log 2>&1' ) | sudo crontab -
```

Schedule periodic backups with a root cron that writes a request file (e.g.
every 6h: a JSON `{"action":"backup-db"}` into `{spool}/requests/`).

- DB credentials are read from `.env` at runtime (rotation-safe).
- **Restore** always takes a protective `pre-restore-*.sql.gz` dump first.
- The agent dir must let the web user read backups for download — pass
  `--web-group` (chgrps `status.json`) and make the backup dirs group-readable.
- The legacy in-process `/{prefix}/backup` (simple SQLite/storage zip) stays
  for managed/Plesk single-node installs; BackupFeature supersedes it for
  self-hosted Postgres/MySQL nodes.

## Storage commands

Fix the usual "media 403 / broken images after deploy" issues:

```bash
php artisan admin-core:storage-check             # diagnose symlink/perms/disk
php artisan admin-core:storage-relink            # real public/storage dir → proper symlink
php artisan admin-core:storage-fix-permissions   # 0755 dirs / 0644 files under storage/app/public
php artisan admin-core:storage-cleanup-backup    # remove storage_old_backup after verifying
```
