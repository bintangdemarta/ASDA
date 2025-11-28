-- This file extends the initial database setup with tables needed for the AI service

-- Create ai_conversations table if it doesn't exist
CREATE TABLE IF NOT EXISTS ai_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    question TEXT NOT NULL,
    response TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- Insert some initial training data for the AI model
INSERT INTO ai_conversations (user_id, question, response) VALUES
(1, 'status klaim saya', 'Anda dapat melihat status klaim Anda di dashboard. Silakan login dan pilih menu "Status Klaim" untuk informasi terbaru.'),
(1, 'cek klaim', 'Anda dapat melihat status klaim Anda di dashboard. Silakan login dan pilih menu "Status Klaim" untuk informasi terbaru.'),
(1, 'dana cair', 'Proses pencairan dana biasanya memakan waktu 1-3 hari kerja setelah klaim disetujui. Anda akan menerima notifikasi email dan SMS jika dana sudah cair.'),
(1, 'syarat klaim', 'Untuk pengajuan klaim, Anda perlu menyediakan: 1) Formulir klaim yang telah diisi, 2) Fotokopi KTP/SIM, 3) Fotokopi bukti kejadian, 4) Dokumen pendukung lainnya sesuai jenis klaim Anda.'),
(1, 'kontak admin', 'Anda dapat menghubungi admin melalui menu "Hubungi Admin" di dashboard, atau dengan membuat jadwal konsultasi langsung.');