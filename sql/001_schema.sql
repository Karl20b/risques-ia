-- À importer uniquement dans la nouvelle base de validation dédiée. Aucune base portail.
-- Tables nouvelles, aucun DROP ni modification des tables existantes.
CREATE TABLE rgia_risks (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(24) NOT NULL UNIQUE,
 version INT UNSIGNED NOT NULL DEFAULT 1,
 data LONGTEXT NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE rgia_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 risk_id BIGINT UNSIGNED NOT NULL,
 actor VARCHAR(80) NOT NULL,
 event_type VARCHAR(40) NOT NULL,
 reason TEXT NOT NULL,
 before_json LONGTEXT NULL,
 after_json LONGTEXT NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(risk_id) REFERENCES rgia_risks(id),
 INDEX rgia_events_risk_date(risk_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE rgia_actions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 risk_id BIGINT UNSIGNED NOT NULL,
 data LONGTEXT NOT NULL,
 created_by VARCHAR(80) NOT NULL,
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(risk_id) REFERENCES rgia_risks(id), INDEX rgia_actions_risk(risk_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE rgia_assessments (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 risk_id BIGINT UNSIGNED NOT NULL,
 data LONGTEXT NOT NULL,
 actor VARCHAR(80) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(risk_id) REFERENCES rgia_risks(id), INDEX rgia_assess_risk(risk_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE rgia_decisions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 risk_id BIGINT UNSIGNED NOT NULL,
 data LONGTEXT NOT NULL,
 actor VARCHAR(80) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(risk_id) REFERENCES rgia_risks(id), INDEX rgia_decisions_risk(risk_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE rgia_drafts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 risk_id BIGINT UNSIGNED NOT NULL,
 actor VARCHAR(80) NOT NULL,
 model VARCHAR(160) NOT NULL,
 input_text TEXT NOT NULL,
 output_text TEXT NOT NULL,
 disposition VARCHAR(24) NOT NULL DEFAULT 'en_attente',
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(risk_id) REFERENCES rgia_risks(id), INDEX rgia_drafts_risk(risk_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Droits du compte web à limiter à : SELECT/INSERT/UPDATE sur risques et mesures ;
-- SELECT/INSERT sur événements, évaluations, décisions ; SELECT/INSERT/UPDATE(disposition) sur brouillons.
-- Sans DELETE, ALTER, DROP, GRANT ni accès aux bases métier. Compte migration séparé.
