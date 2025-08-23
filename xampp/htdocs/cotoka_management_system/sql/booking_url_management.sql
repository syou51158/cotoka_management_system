-- サロン予約URL管理機能のためのSQL変更
-- 1. salonsテーブルにurl_slugカラムを追加
ALTER TABLE salons ADD COLUMN url_slug VARCHAR(100) UNIQUE DEFAULT NULL AFTER name;
ALTER TABLE salons ADD COLUMN default_booking_source_id INT DEFAULT NULL AFTER url_slug;

-- url_slugが未設定のサロンにランダムな値を設定
UPDATE salons SET url_slug = CONCAT(
    LOWER(
        REPLACE(
            REPLACE(
                REPLACE(name, ' ', '-'), 
                '　', '-'
            ),
            '.', ''
        )
    ), 
    '-', 
    FLOOR(RAND() * 1000)
) 
WHERE url_slug IS NULL;

-- 2. 予約ソーステーブルを作成
CREATE TABLE IF NOT EXISTS booking_sources (
    source_id INT AUTO_INCREMENT PRIMARY KEY,
    salon_id INT NOT NULL,
    source_name VARCHAR(100) NOT NULL,
    source_code VARCHAR(50) NOT NULL,
    tracking_url VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (salon_id) REFERENCES salons(salon_id) ON DELETE CASCADE,
    UNIQUE KEY source_unique (salon_id, source_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. 予約テーブルにsourceカラムを追加
ALTER TABLE appointments ADD COLUMN booking_source_id INT DEFAULT NULL AFTER salon_id;
ALTER TABLE appointments ADD CONSTRAINT fk_booking_source FOREIGN KEY (booking_source_id) REFERENCES booking_sources(source_id);

-- 4. 各サロンにデフォルトの予約ソースを追加
INSERT INTO booking_sources (salon_id, source_name, source_code, tracking_url)
SELECT salon_id, 'ウェブサイト', 'website', CONCAT('/public_booking/index.php?salon_id=', salon_id, '&source=website')
FROM salons;

INSERT INTO booking_sources (salon_id, source_name, source_code, tracking_url)
SELECT salon_id, 'Googleマップ', 'google', CONCAT('/public_booking/index.php?salon_id=', salon_id, '&source=google')
FROM salons;

INSERT INTO booking_sources (salon_id, source_name, source_code, tracking_url)
SELECT salon_id, 'Instagram', 'instagram', CONCAT('/public_booking/index.php?salon_id=', salon_id, '&source=instagram')
FROM salons;

-- 5. 各サロンのデフォルト予約ソースを設定
UPDATE salons s
JOIN booking_sources bs ON s.salon_id = bs.salon_id AND bs.source_code = 'website'
SET s.default_booking_source_id = bs.source_id;

