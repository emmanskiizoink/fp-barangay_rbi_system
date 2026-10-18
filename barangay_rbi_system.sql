CREATE TABLE `masterlist` (
     `id` int(11) NOT NULL AUTO_INCREMENT,
        `last_name` varchar(100) NOT NULL,
        `first_name` varchar(100) NOT NULL,
        `middle_name` varchar(100) DEFAULT NULL,
        `qualifier` varchar(20) DEFAULT NULL,
        `house_no` varchar(50) DEFAULT NULL,
        `st_name` varchar(100) DEFAULT NULL,
        `purok_no` varchar(50) NOT NULL,
        `date_of_birth` date NOT NULL,
        `place_of_birth` varchar(150) DEFAULT NULL,
        `sex` varchar(10) DEFAULT NULL,
        `civil_status` varchar(50) DEFAULT NULL,
        `citizenship` varchar(50) DEFAULT 'Filipino',
        `occupation` varchar(100) DEFAULT NULL,
        `relationship_to_head` varchar(100) DEFAULT NULL,
        `is_4ps` varchar(10) DEFAULT 'No',
        `is_ips` varchar(10) DEFAULT 'No',
        PRIMARY KEY (`id`)

);

CREATE TABLE `users` (
     `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL UNIQUE,
        `password` varchar(255) NOT NULL,
        `full_name` varchar(100) DEFAULT NULL,
        `role` varchar(50) DEFAULT 'Encoder',
        PRIMARY KEY (`id`)
);

CREATE TABLE `audit_logs` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `action` varchar(100) NOT NULL,
    `details` text,
    `timestamp` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
);
