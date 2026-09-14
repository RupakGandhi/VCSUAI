-- Run this FIRST, before the restore script, just to see the scope.

-- Should be 630 if the sync only updated existing rows. If it's higher, the
-- extra rows are ones the sync created fresh (no matching deliverable existed
-- for that module/role/deliverable-number before) -- those aren't covered by
-- restore_helena_deliverables.sql and would need to be deleted separately.
SELECT COUNT(*) AS apply_deliverables_row_count FROM apply_deliverables;

-- Should be 0 in the backup. Anything here now was added fresh by the sync.
SELECT COUNT(*) AS challenge_rows FROM module_overview_sections WHERE section = 'challenge';
