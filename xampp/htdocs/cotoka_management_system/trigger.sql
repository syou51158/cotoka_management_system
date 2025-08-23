DELIMITER //

CREATE TRIGGER before_appointment_insert
BEFORE INSERT ON appointments
FOR EACH ROW
BEGIN
    DECLARE task_count INT;
    
    -- 業務との重複をチェック
    SELECT COUNT(*) INTO task_count
    FROM staff_tasks
    WHERE staff_id = NEW.staff_id
    AND task_date = NEW.appointment_date
    AND (
        (start_time <= NEW.start_time AND end_time > NEW.start_time)
        OR (start_time < NEW.end_time AND end_time >= NEW.end_time)
        OR (start_time >= NEW.start_time AND end_time <= NEW.end_time)
    )
    AND status = 'active';
    
    IF task_count > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'スタッフの業務と重複する時間帯には予約できません';
    END IF;
END //

DELIMITER ; 