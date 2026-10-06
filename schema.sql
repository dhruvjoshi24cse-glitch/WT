CREATE TABLE IF NOT EXISTS users(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100),email VARCHAR(120) UNIQUE,pass VARCHAR(255),roll_no VARCHAR(30) UNIQUE NULL,role ENUM('student','faculty','admin') DEFAULT 'student',class_name ENUM('5','6','7','8','9','10') NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS attendance(id INT AUTO_INCREMENT PRIMARY KEY,student_id INT,date DATE,status ENUM('P','A'),UNIQUE(student_id,date));
CREATE TABLE IF NOT EXISTS fees(id INT AUTO_INCREMENT PRIMARY KEY,student_id INT,title VARCHAR(100),amount DECIMAL(10,2),paid TINYINT DEFAULT 0,due_date DATE);
CREATE TABLE IF NOT EXISTS marks(id INT AUTO_INCREMENT PRIMARY KEY,student_id INT,subject VARCHAR(80),score DECIMAL(5,2),max_score DECIMAL(5,2) DEFAULT 100);
CREATE TABLE IF NOT EXISTS events(id INT AUTO_INCREMENT PRIMARY KEY,title VARCHAR(150),date DATE,info VARCHAR(255));
CREATE TABLE IF NOT EXISTS notes(id INT AUTO_INCREMENT PRIMARY KEY,title VARCHAR(150),body TEXT,author VARCHAR(100),class_name ENUM('5','6','7','8','9','10') NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
-- Default admin: admin@school.com / admin123  (change after first login)
INSERT IGNORE INTO users(name,email,pass,role) VALUES('Admin','admin@school.com','$2y$10$b9aDh8e2gPyE0rLaXGz6v.mJSDK1ltg1hQuVVWB6wmscnC/xawzkS','admin');
