-- Legacy installations may contain this index from the original one-card-per-
-- student schema. Remove it before using Recalculate ID so revoked cards can
-- remain in the audit history beside the newly issued replacement card.
--
-- Run only when SHOW INDEX FROM student_id_cards shows `unique_student_card`.
DROP INDEX unique_student_card ON student_id_cards;
