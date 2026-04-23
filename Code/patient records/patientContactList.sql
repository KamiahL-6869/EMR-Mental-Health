--This is a table for customer information to connect 'EMR mental health database.xlsx' related to the columns
-- Author: Kamiah Long
CREATE TABLE customers (
    client_id VARCHAR(10) PRIMARY KEY,
    full_name VARCHAR(100),
    dob DATE,
    phone VARCHAR(20),
    email VARCHAR(100),
    status VARCHAR(20),
    last_session DATE
);

-- This will display a table of the number of sessions per user
CREATE TABLE sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_date DATE,
    client_name VARCHAR(100),
    session_type VARCHAR(50),
    subjective TEXT,
    assessment TEXT,
    signoff VARCHAR(50)
);

-- This is a table for billing (under the patient portal)
CREATE TABLE billing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    billing_date DATE,
    client_name VARCHAR(100),
    service_code VARCHAR(20),
    fee DECIMAL(10,2),
    insurance_status VARCHAR(50),
    paid_status VARCHAR(10)
);
