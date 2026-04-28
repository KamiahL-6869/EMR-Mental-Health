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

// Users table for authentication
$createUsers = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'doctor', 'patient') NOT NULL DEFAULT 'patient',
    customer_id VARCHAR(10) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// This is a table for customer information to connect 'EMR mental health database.xlsx' related to the columns
// Author: Kamiah Long
$createCustomers = "CREATE TABLE IF NOT EXISTS customers (
    client_id VARCHAR(10) PRIMARY KEY,
    full_name VARCHAR(100),
    dob DATE,
    phone VARCHAR(20),
    email VARCHAR(100),
    status VARCHAR(20),
    last_session DATE
)";

// This will display a table of the number of sessions per user
$createSessions = "CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_date DATE,
    client_name VARCHAR(100),
    session_type VARCHAR(50),
    subjective TEXT,
    assessment TEXT,
    signoff VARCHAR(50)
)";

// This is a table for billing (under the patient portal)
$createBilling = "CREATE TABLE IF NOT EXISTS billing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    billing_date DATE,
    client_name VARCHAR(100),
    service_code VARCHAR(20),
    fee DECIMAL(10,2),
    insurance_status VARCHAR(50),
    paid_status VARCHAR(10)
)";

// Appointments table for scheduling
$createAppointments = "CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATETIME NOT NULL,
    appointment_type VARCHAR(100),
    department VARCHAR(50),
    status ENUM('scheduled', 'confirmed', 'completed', 'cancelled') DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
)";

// Notifications table for patient updates
$createNotifications = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sender_id INT,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    type ENUM('message', 'reminder', 'update', 'billing') DEFAULT 'message',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
)";


// ===============================
// EXECUTE TABLE CREATION
// ===============================
$pdo->exec($createUsers);
$pdo->exec($createCustomers);

// Add customer_id column to users if it doesn't exist (links user account to customer record)
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN customer_id VARCHAR(10) DEFAULT NULL");
} catch (PDOException $e) {
    // Column already exists
}

// Add foreign key constraint if not exists (wrapped in try-catch for safety)
try {
    $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_user_customer FOREIGN KEY (customer_id) REFERENCES customers(client_id) ON DELETE SET NULL");
} catch (PDOException $e) {
    // Constraint already exists or customers table empty
}
$pdo->exec($createSessions);
$pdo->exec($createBilling);
$pdo->exec($createAppointments);
$pdo->exec($createNotifications);

// Add role column if it doesn't exist (for existing tables)
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('admin', 'doctor', 'patient') NOT NULL DEFAULT 'patient'");
} catch (PDOException $e) {
    // Column already exists - update ENUM to include patient
    try {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'doctor', 'patient') NOT NULL DEFAULT 'patient'");
    } catch (PDOException $e2) {
        // Ignore if already correct
    }
}

// Insert sample users (password: "password123" for all)
// Check if users already exist to avoid re-seeding on every page load
$checkUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'")->fetchColumn();
if ($checkUsers == 0) {
    $sampleUser = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
    $sampleUser->execute(['admin', '$2y$12$UYe0ETMHbIy3AStQqTgSOuANfn3TObC5v4DtIzxTgHlIs3/jl9iiy', 'admin']);
    $sampleUser->execute(['dr_smith', '$2y$12$UYe0ETMHbIy3AStQqTgSOuANfn3TObC5v4DtIzxTgHlIs3/jl9iiy', 'doctor']);
    $sampleUser->execute(['dr_johnson', '$2y$12$UYe0ETMHbIy3AStQqTgSOuANfn3TObC5v4DtIzxTgHlIs3/jl9iiy', 'doctor']);
    $sampleUser->execute(['nurse_lee', '$2y$12$UYe0ETMHbIy3AStQqTgSOuANfn3TObC5v4DtIzxTgHlIs3/jl9iiy', 'doctor']);
    $sampleUser->execute(['patient', '$2y$12$UYe0ETMHbIy3AStQqTgSOuANfn3TObC5v4DtIzxTgHlIs3/jl9iiy', 'patient']);
    $sampleUser->execute(['jane_doe', '$2y$12$UYe0ETMHbIy3AStQqTgSOuANfn3TObC5v4DtIzxTgHlIs3/jl9iiy', 'patient']);

    // Get user IDs for sample data
    $patientId = $pdo->query("SELECT id FROM users WHERE username = 'patient'")->fetchColumn();
    $drSmithId = $pdo->query("SELECT id FROM users WHERE username = 'dr_smith'")->fetchColumn();
    $drJohnsonId = $pdo->query("SELECT id FROM users WHERE username = 'dr_johnson'")->fetchColumn();
    $nurseLeeId = $pdo->query("SELECT id FROM users WHERE username = 'nurse_lee'")->fetchColumn();

    // Insert sample appointments
    $insertAppt = $pdo->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_type, department, status) VALUES (?, ?, ?, ?, ?, ?)");
    $insertAppt->execute([$patientId, $drSmithId, date('Y-m-d H:i:s', strtotime('+5 days 14:00')), 'Follow-up Session', 'Psychiatry', 'confirmed']);
    $insertAppt->execute([$patientId, $drJohnsonId, date('Y-m-d H:i:s', strtotime('+12 days 10:30')), 'Therapy Session', 'Psychology', 'scheduled']);
    $insertAppt->execute([$patientId, $nurseLeeId, date('Y-m-d H:i:s', strtotime('+18 days 15:00')), 'Check-in', 'General', 'scheduled']);

    // Insert sample notifications
    $insertNotif = $pdo->prepare("INSERT INTO notifications (user_id, sender_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $insertNotif->execute([$patientId, $drSmithId, 'Dr. Smith sent you a message', 'Your lab results are in. Everything looks good! We can discuss more at your next appointment.', 'message', 0, date('Y-m-d H:i:s', strtotime('-2 hours'))]);
    $insertNotif->execute([$patientId, null, 'Appointment Reminder', 'Your appointment with Dr. Smith is scheduled for ' . date('F j, Y', strtotime('+5 days')) . ' at 2:00 PM.', 'reminder', 0, date('Y-m-d H:i:s', strtotime('-1 day'))]);
    $insertNotif->execute([$patientId, $nurseLeeId, 'Nurse Lee updated your care plan', 'Your medication schedule has been updated. Please review the changes in your care plan.', 'update', 1, date('Y-m-d H:i:s', strtotime('-3 days'))]);
    $insertNotif->execute([$patientId, null, 'Billing Update', 'Your insurance claim for the April 15 visit has been processed.', 'billing', 1, date('Y-m-d H:i:s', strtotime('-1 week'))]);
}


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

// Get upcoming appointments for a patient
function getPatientAppointments($pdo, $patientId) {
    $stmt = $pdo->prepare("
        SELECT a.*, u.username as doctor_name 
        FROM appointments a 
        JOIN users u ON a.doctor_id = u.id 
        WHERE a.patient_id = ? AND a.appointment_date >= NOW() AND a.status != 'cancelled'
        ORDER BY a.appointment_date ASC
    ");
    $stmt->execute([$patientId]);
    return $stmt->fetchAll();
}

// Get next appointment for a patient
function getNextAppointment($pdo, $patientId) {
    $stmt = $pdo->prepare("
        SELECT a.*, u.username as doctor_name 
        FROM appointments a 
        JOIN users u ON a.doctor_id = u.id 
        WHERE a.patient_id = ? AND a.appointment_date >= NOW() AND a.status != 'cancelled'
        ORDER BY a.appointment_date ASC
        LIMIT 1
    ");
    $stmt->execute([$patientId]);
    return $stmt->fetch();
}

// Get notifications for a user
function getUserNotifications($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT n.*, u.username as sender_name 
        FROM notifications n 
        LEFT JOIN users u ON n.sender_id = u.id 
        WHERE n.user_id = ? 
        ORDER BY n.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

// Get unread notification count
function getUnreadNotificationCount($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

// Format doctor username for display (dr_smith -> Dr. Smith)
function formatDoctorName($username) {
    $name = str_replace(['dr_', 'nurse_'], ['Dr. ', 'Nurse '], $username);
    return ucwords(str_replace('_', ' ', $name));
}

// Format relative time (e.g., "2 hours ago")
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 6) return floor($diff->d / 7) . ' week' . (floor($diff->d / 7) > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}

// ===============================
// CUSTOMER/PATIENT MANAGEMENT
// ===============================

// Search customers by name (for doctor lookup)
function searchCustomers($pdo, $searchTerm) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.id as user_id, u.username 
        FROM customers c 
        LEFT JOIN users u ON u.customer_id = c.client_id 
        WHERE c.full_name LIKE ? OR c.client_id LIKE ?
        ORDER BY c.full_name ASC
        LIMIT 20
    ");
    $term = '%' . $searchTerm . '%';
    $stmt->execute([$term, $term]);
    return $stmt->fetchAll();
}

// Get customer by client_id
function getCustomerById($pdo, $clientId) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.id as user_id, u.username 
        FROM customers c 
        LEFT JOIN users u ON u.customer_id = c.client_id 
        WHERE c.client_id = ?
    ");
    $stmt->execute([$clientId]);
    return $stmt->fetch();
}

// Get customer record linked to a user
function getCustomerByUserId($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT c.* 
        FROM customers c 
        JOIN users u ON u.customer_id = c.client_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// Link a user account to a customer record
function linkUserToCustomer($pdo, $userId, $clientId) {
    $stmt = $pdo->prepare("UPDATE users SET customer_id = ? WHERE id = ?");
    return $stmt->execute([$clientId, $userId]);
}

// Get all patients (users with role='patient') with their customer info
function getAllPatients($pdo) {
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.customer_id, c.full_name, c.phone, c.email, c.status, c.last_session
        FROM users u
        LEFT JOIN customers c ON u.customer_id = c.client_id
        WHERE u.role = 'patient'
        ORDER BY COALESCE(c.full_name, u.username) ASC
    ");
    return $stmt->fetchAll();
}

// Get appointments for a doctor
function getDoctorAppointments($pdo, $doctorId) {
    $stmt = $pdo->prepare("
        SELECT a.*, u.username as patient_username, c.full_name as patient_name
        FROM appointments a 
        JOIN users u ON a.patient_id = u.id 
        LEFT JOIN customers c ON u.customer_id = c.client_id
        WHERE a.doctor_id = ? AND a.appointment_date >= NOW() AND a.status != 'cancelled'
        ORDER BY a.appointment_date ASC
    ");
    $stmt->execute([$doctorId]);
    return $stmt->fetchAll();
}

// Create an appointment (doctor scheduling for patient)
function createAppointment($pdo, $patientId, $doctorId, $date, $type, $department, $status = 'scheduled') {
    $stmt = $pdo->prepare("
        INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_type, department, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$patientId, $doctorId, $date, $type, $department, $status]);
}
?>

