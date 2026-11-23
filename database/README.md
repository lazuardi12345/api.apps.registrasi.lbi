# Database Ownership

## Source of truth

- `database/migrations` owns schema, indexes, constraints, and one-time data repairs.
- Legacy source snapshots are private local inputs kept outside this repository in the workspace's sibling `private-backend-data/legacy-imports` folder.
- Marketing-managed content is stored in the application tables and must not be overwritten by a later import.

## Import policy

1. Run normal migrations with `php artisan migrate`.
2. Keep the legacy JSON snapshots and Webform SQL dump in the workspace's sibling `private-backend-data` folder, then run `php artisan legacy:imports --directory=../private-backend-data/legacy-imports --sql-file=../private-backend-data/legacy_webform_subset.sql --dry-run` from the backend project root and review the record counts.
3. Run `php artisan legacy:imports` to import source content, settings, taxonomy, catalog mappings, and Webform records into Laravel database tables. The command validates all sources before writing and can be rerun safely.
4. Verify destination counts and application behavior before removing source files from `database/imports`; those files remain the recovery source until verification is complete.
5. Use `legacy_nid` for matching. Preserve existing slugs, sort order, and non-empty editorial details; import source details only into empty fields.
6. Keep schema and repeatable data-repair behavior in migrations separate from the one-time backup import.
7. Do not match schedules to catalogs by title prefix when an explicit catalog ID is available.

## Relationship rules

- A schedule may reference a service and catalog, or remain unassigned while it is being reviewed.
- `course_schedules.service_id` and `course_schedules.course_catalog_id` are protected by foreign keys after the relationship audit migration.
- Deleting a service or catalog sets those nullable schedule links to `NULL`; it does not delete schedule history.
- `course_catalogs.slug` is the public catalog identity. Schedule batches must use their own distinct `course_slug` values.

## Legacy files

Private files under the workspace's sibling `private-backend-data` folder are one-time inputs to `legacy:imports`, not runtime application dependencies. Keep them out of source control and shared servers. Remove them only after the database import has completed and its record counts have been verified.
