-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS admin_panel;

-- Create the user if it doesn't exist and grant privileges
CREATE USER IF NOT EXISTS 'admin_panel'@'%' IDENTIFIED BY 'admin_panel';
GRANT ALL PRIVILEGES ON admin_panel.* TO 'admin_panel'@'%';
FLUSH PRIVILEGES;

-- Use the database
USE admin_panel;

-- Add any initial tables or data here if needed