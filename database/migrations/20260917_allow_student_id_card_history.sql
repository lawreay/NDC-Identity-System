-- Legacy installations may contain this index from the original one-card-per-
-- student schema. Remove it before using Recalculate ID so revoked cards can
-- remain in the audit history beside the newly issued replacement card.
--
-- Safe to run repeatedly: MySQL does not support DROP INDEX IF EXISTS on all
-- supported versions, so use a conditional prepared statement instead.
SET @legacy_index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'student_id_cards'
      AND INDEX_NAME = 'unique_student_card'
);
SET @drop_legacy_index_sql := IF(
    @legacy_index_exists > 0,
    'ALTER TABLE `student_id_cards` DROP INDEX `unique_student_card`',
    'SELECT 1'
);
PREPARE drop_legacy_index_stmt FROM @drop_legacy_index_sql;
EXECUTE drop_legacy_index_stmt;
DEALLOCATE PREPARE drop_legacy_index_stmt;
