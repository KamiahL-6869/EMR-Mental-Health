<?php
// ===============================
// DATABASE CONNECTION
// ===============================
$host = "localhost";
$dbname = "emr_mental_health";   // UPDATED DATABASE NAME
$username = "root";
$password = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    exit("Database connection failed: " . $e->getMessage());
}


// ===============================
// TABLE CREATION TEMPLATES
// ===============================

// This is a table for customer information to connect 'EMR mental health database.xlsx' related to the columns
// Author: Kamiah Long
CREATE TABLE customers (
    client_id VARCHAR(10) PRIMARY KEY,
    full_name VARCHAR(100),
    dob DATE,
    phone VARCHAR(20),
    email VARCHAR(100),
    status VARCHAR(20),
    last_session DATE
);

// This will display a table of the number of sessions per user
CREATE TABLE sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_date DATE,
    client_name VARCHAR(100),
    session_type VARCHAR(50),
    subjective TEXT,
    assessment TEXT,
    signoff VARCHAR(50)
);

// This is a table for billing (under the patient portal)
CREATE TABLE billing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    billing_date DATE,
    client_name VARCHAR(100),
    service_code VARCHAR(20),
    fee DECIMAL(10,2),
    insurance_status VARCHAR(50),
    paid_status VARCHAR(10)
);


// ===============================
// EXECUTE TABLE CREATION
// ===============================
$pdo->exec($createCustomers);
$pdo->exec($createSessions);
$pdo->exec($createBilling);


// ===============================
// EXAMPLE QUERIES
// ===============================

// Fetch all customers
function getCustomers($pdo) {
    $stmt = $pdo->query("SELECT * FROM customers ORDER BY full_name");
    return $stmt->fetchAll();
}

// Fetch sessions for a client
function getSessionsByClient($pdo, $clientName) {
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE client_name = ?");
    $stmt->execute([$clientName]);
    return $stmt->fetchAll();
}

// Add a billing entry
function addBilling($pdo, $date, $client, $code, $fee, $status, $paid) {
    $stmt = $pdo->prepare("
        INSERT INTO billing (billing_date, client_name, service_code, fee, insurance_status, paid_status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$date, $client, $code, $fee, $status, $paid]);
}
?>

