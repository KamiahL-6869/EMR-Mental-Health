<?php
session_start();
require "db.php";

// Redirect to login if not authenticated
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION["user_id"];
$username = $_SESSION["username"];
$role = $_SESSION["role"];
$isAdmin = ($role === "admin");
$isDoctor = ($role === "doctor");
$isPatient = ($role === "patient");

// Fetch live data for patients
$appointments = [];
$notifications = [];
$nextAppointment = null;
$unreadCount = 0;
$doctorAppointments = [];
$searchResults = [];
$searchQuery = '';

$patientMessage = '';
$patientMessageType = '';

if ($isPatient) {
    // Handle patient appointment actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $apptId = $_POST['appointment_id'] ?? '';
        
        switch ($_POST['action']) {
            case 'confirm_appointment':
                if (confirmAppointment($pdo, $apptId, $userId)) {
                    $patientMessage = 'Appointment confirmed successfully.';
                    $patientMessageType = 'success';
                } else {
                    $patientMessage = 'Unable to confirm appointment.';
                    $patientMessageType = 'error';
                }
                break;
                
            case 'request_change':
                $newDate = $_POST['new_date'] ?? '';
                $newTime = $_POST['new_time'] ?? '';
                if ($newDate && $newTime) {
                    $newDateTime = $newDate . ' ' . $newTime . ':00';
                    if (requestChangeAppointment($pdo, $apptId, $userId, $newDateTime)) {
                        $patientMessage = 'Change request submitted. Waiting for doctor approval.';
                        $patientMessageType = 'success';
                    } else {
                        $patientMessage = 'Unable to submit change request.';
                        $patientMessageType = 'error';
                    }
                }
                break;
                
            case 'request_cancel':
                if (requestCancelAppointment($pdo, $apptId, $userId)) {
                    $patientMessage = 'Cancellation request submitted. Waiting for doctor approval.';
                    $patientMessageType = 'success';
                } else {
                    $patientMessage = 'Unable to submit cancellation request.';
                    $patientMessageType = 'error';
                }
                break;
        }
    }
    
    $appointments = getPatientAppointments($pdo, $userId);
    $notifications = getUserNotifications($pdo, $userId);
    $nextAppointment = getNextAppointment($pdo, $userId);
    $unreadCount = getUnreadNotificationCount($pdo, $userId);
}

$doctorMessage = '';
$doctorMessageType = '';

$pendingRequests = [];

if ($isDoctor) {
    // Handle appointment creation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'create_appointment') {
            $patientUserId = $_POST['patient_id'] ?? '';
            $apptDate = $_POST['appointment_date'] ?? '';
            $apptTime = $_POST['appointment_time'] ?? '';
            $apptType = trim($_POST['appointment_type'] ?? '');
            $department = trim($_POST['department'] ?? '');
            
            if (empty($patientUserId) || empty($apptDate) || empty($apptTime) || empty($apptType)) {
                $doctorMessage = 'Please fill in all required fields.';
                $doctorMessageType = 'error';
            } else {
                try {
                    $apptDateTime = $apptDate . ' ' . $apptTime . ':00';
                    createAppointment($pdo, $patientUserId, $userId, $apptDateTime, $apptType, $department, 'scheduled');
                    
                    // Get patient name for message
                    $patientStmt = $pdo->prepare("SELECT c.full_name FROM users u LEFT JOIN customers c ON u.customer_id = c.client_id WHERE u.id = ?");
                    $patientStmt->execute([$patientUserId]);
                    $patientName = $patientStmt->fetchColumn() ?: 'Patient';
                    
                    $doctorMessage = "Appointment scheduled with {$patientName} for " . date('M j, Y \a\t g:i A', strtotime($apptDateTime));
                    $doctorMessageType = 'success';
                } catch (PDOException $e) {
                    $doctorMessage = 'Error creating appointment: ' . $e->getMessage();
                    $doctorMessageType = 'error';
                }
            }
        } else if ($action === 'approve_request') {
            $apptId = $_POST['appointment_id'] ?? '';
            if (approvePendingRequest($pdo, $apptId, $userId)) {
                $doctorMessage = 'Request approved successfully.';
                $doctorMessageType = 'success';
            } else {
                $doctorMessage = 'Unable to approve request.';
                $doctorMessageType = 'error';
            }
        } else if ($action === 'deny_request') {
            $apptId = $_POST['appointment_id'] ?? '';
            if (denyPendingRequest($pdo, $apptId, $userId)) {
                $doctorMessage = 'Request denied. Appointment restored.';
                $doctorMessageType = 'success';
            } else {
                $doctorMessage = 'Unable to deny request.';
                $doctorMessageType = 'error';
            }
        }
    }
    
    $doctorAppointments = getDoctorAppointments($pdo, $userId);
    $pendingRequests = getDoctorPendingRequests($pdo, $userId);
    
    // Handle patient search
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $searchQuery = trim($_GET['search']);
        $searchResults = searchCustomers($pdo, $searchQuery);
    }
}

// Admin: Handle user creation
$adminMessage = '';
$adminMessageType = '';
$allUsers = [];

if ($isAdmin) {
    // Fetch all users for the admin panel
    $allUsers = $pdo->query("
        SELECT u.*, c.full_name as customer_name 
        FROM users u 
        LEFT JOIN customers c ON u.customer_id = c.client_id 
        ORDER BY u.created_at DESC
    ")->fetchAll();
    
    // Handle new user form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
        $newUsername = trim($_POST['username'] ?? '');
        $newPassword = $_POST['password'] ?? '';
        $newRole = $_POST['role'] ?? 'patient';
        $fullName = trim($_POST['full_name'] ?? '');
        $dob = $_POST['dob'] ?? null;
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $status = $_POST['status'] ?? 'Active';
        
        // Validation
        if (empty($newUsername) || empty($newPassword) || empty($fullName)) {
            $adminMessage = 'Username, password, and full name are required.';
            $adminMessageType = 'error';
        } else {
            try {
                // Check if username exists
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $checkStmt->execute([$newUsername]);
                if ($checkStmt->fetchColumn() > 0) {
                    $adminMessage = 'Username already exists.';
                    $adminMessageType = 'error';
                } else {
                    // Generate client_id for customer record
                    $clientId = 'C' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
                    
                    // Create customer record first
                    $custStmt = $pdo->prepare("
                        INSERT INTO customers (client_id, full_name, dob, phone, email, status) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $custStmt->execute([$clientId, $fullName, $dob ?: null, $phone, $email, $status]);
                    
                    // Create user account linked to customer
                    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $userStmt = $pdo->prepare("
                        INSERT INTO users (username, password_hash, role, customer_id) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $userStmt->execute([$newUsername, $passwordHash, $newRole, $clientId]);
                    
                    $adminMessage = "User '{$newUsername}' created successfully with Client ID: {$clientId}";
                    $adminMessageType = 'success';
                    
                    // Refresh user list
                    $allUsers = $pdo->query("
                        SELECT u.*, c.full_name as customer_name 
                        FROM users u 
                        LEFT JOIN customers c ON u.customer_id = c.client_id 
                        ORDER BY u.created_at DESC
                    ")->fetchAll();
                }
            } catch (PDOException $e) {
                $adminMessage = 'Error creating user: ' . $e->getMessage();
                $adminMessageType = 'error';
            }
        }
    }
    
    // Fetch login logs for admin
    $loginFilter = $_GET['log_filter'] ?? null;
    $loginLogs = getLoginLogs($pdo, 50, 0, $loginFilter);
    $loginStats = getLoginStats($pdo);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>EMR Mental Health - Dashboard</title>
    <style>
        :root {
            --space-indigo: #2E294E;
            --pastel-petal: #EFBCD5;
            --lilac: #BE97C6;
            --lavender: #8661C1;
            --charcoal: #4B5267;
            --bg: var(--charcoal);
            --card-bg: rgba(190,151,198,0.12);
            --max-width: 980px;
        }

        * { box-sizing: border-box }

        body {
            margin: 0;
            font-family: Inter, 'Comic Sans MS', sans-serif;
            background: linear-gradient(180deg,var(--bg),#3f4456);
            color: var(--lavender);
            padding: 28px;
            display: flex;
            justify-content: center;
        }

        .page {
            width: 100%;
            max-width: var(--max-width);
            border: 10px solid var(--space-indigo);
            border-radius: 10px;
            background: linear-gradient(180deg, rgba(46,41,78,0.98), rgba(75,82,103,0.95));
            padding: 28px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 20px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .logo {
            width: 64px;
            height: 64px;
            border-radius: 12px;
            background: linear-gradient(135deg,var(--lilac),var(--pastel-petal));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--space-indigo);
            font-size: 20px;
        }

        h1 { margin: 0; font-size: 24px; color: var(--pastel-petal) }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #EAD7F0;
            font-size: 14px;
        }

        .role-badge {
            background: <?php echo $isAdmin ? 'var(--pastel-petal)' : ($isDoctor ? 'var(--lilac)' : 'var(--lavender)'); ?>;
            color: var(--space-indigo);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .logout-btn {
            background: transparent;
            color: var(--pastel-petal);
            border: 1px solid rgba(239,188,213,0.18);
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            font-size: 13px;
        }

        .logout-btn:hover {
            background: rgba(239,188,213,0.08);
        }

        .tab {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .tab-btn {
            background: transparent;
            color: var(--lavender);
            border: 1px solid transparent;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .tab-btn:hover {
            background: rgba(190,151,198,0.06);
            border-color: rgba(190,151,198,0.14);
        }

        .tab-btn.active, .tab-btn[aria-selected="true"] {
            background: linear-gradient(90deg,var(--lilac),var(--pastel-petal));
            color: var(--space-indigo);
            box-shadow: 0 6px 18px rgba(46,41,78,0.4);
            border-color: rgba(46,41,78,0.12);
        }

        .tab-btn:focus {
            outline: 2px solid rgba(239,188,213,0.32);
            outline-offset: 2px;
        }

        .tabcontent { margin-top: 12px }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--pastel-petal);
            color: var(--space-indigo);
            padding: 10px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            font-size: 14px;
            transition: transform 0.12s ease, box-shadow 0.12s ease;
            box-shadow: 0 4px 12px rgba(46,41,78,0.15);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(46,41,78,0.25);
        }

        .btn.secondary {
            background: transparent;
            color: var(--pastel-petal);
            border: 1px solid rgba(239,188,213,0.25);
            box-shadow: none;
        }

        .btn.secondary:hover {
            background: rgba(239,188,213,0.08);
        }

        .notification-item {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(190,151,198,0.15);
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 10px;
        }

        .notification-item:last-child {
            margin-bottom: 0;
        }

        .notification-item .time {
            font-size: 12px;
            color: var(--lilac);
            margin-top: 6px;
        }

        .notification-item.unread {
            border-left: 3px solid var(--pastel-petal);
        }

        .appointment-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 16px;
        }

        .appointment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(190,151,198,0.15);
            border-radius: 8px;
            padding: 14px 18px;
        }

        .appointment-item .details h4 {
            margin: 0 0 4px;
            color: var(--pastel-petal);
            font-size: 15px;
        }

        .appointment-item .details p {
            margin: 0;
            font-size: 13px;
            color: #C9B8D9;
        }

        .appointment-item .status {
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 600;
        }

        .status.upcoming {
            background: rgba(134,97,193,0.25);
            color: var(--lilac);
        }

        .status.confirmed {
            background: rgba(72,187,120,0.2);
            color: #68D391;
        }

        .status.pending {
            background: rgba(237,187,106,0.2);
            color: #EDB96A;
        }

        .status.scheduled {
            background: rgba(134,97,193,0.25);
            color: var(--lilac);
        }

        .pending-request {
            background: rgba(237,187,106,0.08);
            border: 1px solid rgba(237,187,106,0.25);
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 12px;
        }

        .pending-request h4 {
            margin: 0 0 6px;
            color: #EDB96A;
            font-size: 14px;
        }

        .pending-request p {
            margin: 0 0 4px;
            font-size: 13px;
            color: #C9B8D9;
        }

        .pending-request .actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .empty-state {
            text-align: center;
            padding: 32px;
            color: #A89BBF;
        }

        .patient-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .patient-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(190,151,198,0.15);
            border-radius: 8px;
            padding: 16px 18px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .patient-info h4 {
            margin: 0 0 8px;
            color: var(--pastel-petal);
            font-size: 16px;
        }

        .patient-info p {
            margin: 0 0 4px;
            font-size: 13px;
            color: #C9B8D9;
        }

        .patient-info p strong {
            color: #E9DFF7;
        }

        .patient-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid rgba(190,151,198,0.28);
            padding: 16px;
            border-radius: 10px;
            color: #F2EAF9;
        }

        .card h2 { margin-top: 0; color: var(--pastel-petal) }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-size: 13px;
            color: var(--lilac);
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid rgba(190,151,198,0.25);
            background: rgba(255,255,255,0.05);
            color: var(--pastel-petal);
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--lilac);
            background: rgba(255,255,255,0.08);
        }

        .form-group select option {
            background: var(--space-indigo);
            color: var(--pastel-petal);
        }

        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .alert.success {
            background: rgba(72,187,120,0.15);
            border: 1px solid rgba(72,187,120,0.3);
            color: #68D391;
        }

        .alert.error {
            background: rgba(220,53,69,0.15);
            border: 1px solid rgba(220,53,69,0.3);
            color: #FC8181;
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            font-size: 13px;
        }

        .user-table th,
        .user-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid rgba(190,151,198,0.15);
        }

        .user-table th {
            color: var(--lilac);
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }

        .user-table td {
            color: #E9DFF7;
        }

        .user-table tr:hover td {
            background: rgba(255,255,255,0.02);
        }

        footer {
            margin-top: 22px;
            color: #D6C7E8;
            text-align: center;
            font-size: 13px;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: linear-gradient(180deg, rgba(46,41,78,0.98), rgba(75,82,103,0.95));
            border: 2px solid var(--lilac);
            border-radius: 12px;
            padding: 24px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }

        .modal h3 {
            margin: 0 0 8px;
            color: var(--pastel-petal);
            font-size: 18px;
        }

        .modal p {
            margin: 0 0 20px;
            color: var(--lilac);
            font-size: 14px;
        }

        .modal .form-group {
            margin-bottom: 16px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        @media (max-width:560px) {
            h1 { font-size: 20px }
            header { flex-direction: column; align-items: flex-start; }
            .user-info { margin-top: 12px; }
        }
    </style>
</head>
<body>
    <div class="page">
        <header>
            <div class="header-left">
                <div class="logo">EMR</div>
                <div>
                    <h1>EMR Mental Health</h1>
                </div>
            </div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($username); ?></span>
                <span class="role-badge"><?php echo $role; ?></span>
                <a href="logout.php" class="logout-btn">Log Out</a>
            </div>
        </header>

        <div class="tab" role="tablist" aria-label="Dashboard tabs">
            <button type="button" class="tab-btn" data-target="dashboard" aria-selected="true">Dashboard</button>
            <?php if ($isAdmin): ?>
                <button type="button" class="tab-btn" data-target="schedule" aria-selected="false">Schedule</button>
                <button type="button" class="tab-btn" data-target="users" aria-selected="false">Users</button>
                <button type="button" class="tab-btn" data-target="loginlogs" aria-selected="false">Login Logs</button>
            <?php elseif ($isDoctor): ?>
                <button type="button" class="tab-btn" data-target="schedule" aria-selected="false">My Schedule</button>
                <button type="button" class="tab-btn" data-target="patients" aria-selected="false">My Patients</button>
            <?php else: ?>
                <button type="button" class="tab-btn" data-target="appointments" aria-selected="false">My Appointments</button>
                <button type="button" class="tab-btn" data-target="notifications" aria-selected="false">Notifications</button>
            <?php endif; ?>
            <button type="button" class="tab-btn" data-target="patchnotes" aria-selected="false">Patch Notes</button>
        </div>

        <section id="dashboard" class="tabcontent">
            <div class="card">
                <h2>Overview</h2>
                <?php if ($isAdmin): ?>
                    <p>Quick stats and recent activity for administrators.</p>
                <?php elseif ($isDoctor): ?>
                    <p>Welcome to your dashboard. View your schedule and patient information.</p>
                <?php else: ?>
                    <p>Welcome back, <?php echo htmlspecialchars($username); ?>! Here's a summary of your upcoming appointments and recent updates from your care team.</p>
                    <div style="display:flex;gap:16px;margin-top:16px;flex-wrap:wrap;">
                        <div class="card" style="flex:1;min-width:200px;">
                            <h3 style="margin:0 0 8px;font-size:14px;color:var(--lilac);">Next Appointment</h3>
                            <?php if ($nextAppointment): ?>
                                <p style="margin:0;font-size:18px;color:var(--pastel-petal);"><?php echo date('M j, Y \a\t g:i A', strtotime($nextAppointment['appointment_date'])); ?></p>
                                <p style="margin:4px 0 0;font-size:13px;"><?php echo formatDoctorName($nextAppointment['doctor_name']); ?> - <?php echo htmlspecialchars($nextAppointment['appointment_type']); ?></p>
                            <?php else: ?>
                                <p style="margin:0;font-size:16px;color:#A89BBF;">No upcoming appointments</p>
                            <?php endif; ?>
                        </div>
                        <div class="card" style="flex:1;min-width:200px;">
                            <h3 style="margin:0 0 8px;font-size:14px;color:var(--lilac);">Unread Notifications</h3>
                            <p style="margin:0;font-size:18px;color:var(--pastel-petal);"><?php echo $unreadCount; ?> new message<?php echo $unreadCount != 1 ? 's' : ''; ?></p>
                            <p style="margin:4px 0 0;font-size:13px;">From your care team</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section id="schedule" class="tabcontent" hidden>
            <div class="card">
                <h2><?php echo $isAdmin ? 'Schedule' : 'My Schedule'; ?></h2>
                <?php if ($isDoctor): ?>
                    <?php if ($doctorMessage): ?>
                        <div class="alert <?php echo $doctorMessageType; ?>" style="margin-bottom:16px;">
                            <?php echo htmlspecialchars($doctorMessage); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($pendingRequests)): ?>
                        <h3 style="color:#EDB96A;font-size:14px;margin:0 0 12px;">Pending Requests (<?php echo count($pendingRequests); ?>)</h3>
                        <?php foreach ($pendingRequests as $req): ?>
                            <div class="pending-request">
                                <h4>
                                    <?php echo $req['pending_action'] === 'cancel' ? 'Cancellation Request' : 'Reschedule Request'; ?>
                                    from <?php echo htmlspecialchars($req['patient_name'] ?? $req['patient_username']); ?>
                                </h4>
                                <p><strong>Appointment:</strong> <?php echo htmlspecialchars($req['appointment_type']); ?> on <?php echo date('M j, Y \a\t g:i A', strtotime($req['appointment_date'])); ?></p>
                                <?php if ($req['pending_action'] === 'change' && $req['pending_date']): ?>
                                    <p><strong>Requested new time:</strong> <?php echo date('M j, Y \a\t g:i A', strtotime($req['pending_date'])); ?></p>
                                <?php endif; ?>
                                <div class="actions">
                                    <form method="POST" action="?tab=schedule" style="display:inline;">
                                        <input type="hidden" name="action" value="approve_request">
                                        <input type="hidden" name="appointment_id" value="<?php echo $req['id']; ?>">
                                        <button type="submit" class="btn" style="padding:6px 12px;font-size:13px;">Approve</button>
                                    </form>
                                    <form method="POST" action="?tab=schedule" style="display:inline;">
                                        <input type="hidden" name="action" value="deny_request">
                                        <input type="hidden" name="appointment_id" value="<?php echo $req['id']; ?>">
                                        <button type="submit" class="btn secondary" style="padding:6px 12px;font-size:13px;">Deny</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <hr style="border:none;border-top:1px solid rgba(190,151,198,0.2);margin:20px 0;">
                    <?php endif; ?>

                    <p>Your upcoming appointments with patients.</p>
                    <div class="appointment-list">
                        <?php if (empty($doctorAppointments)): ?>
                            <div class="empty-state">
                                <p>No upcoming appointments scheduled.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($doctorAppointments as $appt): ?>
                                <div class="appointment-item">
                                    <div class="details">
                                        <h4><?php echo htmlspecialchars($appt['appointment_type']); ?> with <?php echo htmlspecialchars($appt['patient_name'] ?? $appt['patient_username']); ?></h4>
                                        <p><?php echo date('M j, Y \a\t g:i A', strtotime($appt['appointment_date'])); ?> • <?php echo htmlspecialchars($appt['department']); ?></p>
                                    </div>
                                    <span class="status <?php echo $appt['status'] === 'confirmed' ? 'confirmed' : 'scheduled'; ?>">
                                        <?php echo ucfirst($appt['status']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p>Appointments, shifts, and calendar actions live here.</p>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($isAdmin): ?>
        <section id="users" class="tabcontent" hidden>
            <div class="card">
                <h2>Users</h2>
                <p>Create and manage user accounts. Each user is linked to a customer record.</p>

                <?php if ($adminMessage): ?>
                    <div class="alert <?php echo $adminMessageType; ?>">
                        <?php echo htmlspecialchars($adminMessage); ?>
                    </div>
                <?php endif; ?>

                <h3 style="color:var(--lilac);font-size:14px;margin:20px 0 0;">Create New User</h3>
                <form method="POST" action="?tab=users">
                    <input type="hidden" name="action" value="create_user">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" required placeholder="e.g., john_doe">
                        </div>
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" required placeholder="Min 8 characters">
                        </div>
                        <div class="form-group">
                            <label for="role">Role *</label>
                            <select id="role" name="role" required>
                                <option value="patient">Patient</option>
                                <option value="doctor">Doctor</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Pending">Pending</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" required placeholder="e.g., John Doe">
                        </div>
                        <div class="form-group">
                            <label for="dob">Date of Birth</label>
                            <input type="date" id="dob" name="dob">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" placeholder="e.g., (555) 123-4567">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" placeholder="e.g., john@example.com">
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn">Create User</button>
                            <button type="reset" class="btn secondary">Clear</button>
                        </div>
                    </div>
                </form>

                <h3 style="color:var(--lilac);font-size:14px;margin:28px 0 0;">All Users (<?php echo count($allUsers); ?>)</h3>
                <div style="overflow-x:auto;">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Role</th>
                                <th>Client ID</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allUsers as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['customer_name'] ?? '—'); ?></td>
                                    <td>
                                        <span class="role-badge" style="background:<?php 
                                            echo $user['role'] === 'admin' ? 'var(--pastel-petal)' : 
                                                ($user['role'] === 'doctor' ? 'var(--lilac)' : 'var(--lavender)'); 
                                        ?>;">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['customer_id'] ?? '—'); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <section id="loginlogs" class="tabcontent" hidden>
            <div class="card">
                <h2>Login Logs</h2>
                <p>Security audit log of all login attempts. All access is monitored and recorded.</p>

                <div style="display:flex;gap:16px;margin:16px 0;flex-wrap:wrap;">
                    <div class="card" style="flex:1;min-width:140px;padding:16px;">
                        <h3 style="margin:0 0 4px;font-size:12px;color:var(--lilac);">Today's Attempts</h3>
                        <p style="margin:0;font-size:24px;color:var(--pastel-petal);"><?php echo $loginStats['today_total']; ?></p>
                    </div>
                    <div class="card" style="flex:1;min-width:140px;padding:16px;">
                        <h3 style="margin:0 0 4px;font-size:12px;color:var(--lilac);">Successful</h3>
                        <p style="margin:0;font-size:24px;color:#68D391;"><?php echo $loginStats['today_success']; ?></p>
                    </div>
                    <div class="card" style="flex:1;min-width:140px;padding:16px;">
                        <h3 style="margin:0 0 4px;font-size:12px;color:var(--lilac);">Failed</h3>
                        <p style="margin:0;font-size:24px;color:#FC8181;"><?php echo $loginStats['today_failed']; ?></p>
                    </div>
                    <div class="card" style="flex:1;min-width:140px;padding:16px;">
                        <h3 style="margin:0 0 4px;font-size:12px;color:var(--lilac);">Total All Time</h3>
                        <p style="margin:0;font-size:24px;color:var(--pastel-petal);"><?php echo $loginStats['total']; ?></p>
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <a href="?tab=loginlogs" class="btn <?php echo !$loginFilter ? '' : 'secondary'; ?>" style="padding:6px 12px;font-size:13px;">All</a>
                    <a href="?tab=loginlogs&log_filter=success" class="btn <?php echo $loginFilter === 'success' ? '' : 'secondary'; ?>" style="padding:6px 12px;font-size:13px;">Successful</a>
                    <a href="?tab=loginlogs&log_filter=failed" class="btn <?php echo $loginFilter === 'failed' ? '' : 'secondary'; ?>" style="padding:6px 12px;font-size:13px;">Failed</a>
                </div>

                <div style="overflow-x:auto;">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Username</th>
                                <th>Outcome</th>
                                <th>Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($loginLogs)): ?>
                                <tr><td colspan="4" style="text-align:center;color:#A89BBF;">No login attempts recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($loginLogs as $log): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($log['username']); ?>
                                            <?php if ($log['user_role']): ?>
                                                <span class="role-badge" style="font-size:10px;padding:2px 6px;background:<?php 
                                                    echo $log['user_role'] === 'admin' ? 'var(--pastel-petal)' : 
                                                        ($log['user_role'] === 'doctor' ? 'var(--lilac)' : 'var(--lavender)'); 
                                                ?>;"><?php echo ucfirst($log['user_role']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status <?php echo $log['outcome'] === 'success' ? 'confirmed' : 'pending'; ?>">
                                                <?php echo $log['outcome'] === 'success' ? 'Success' : 'Failed'; ?>
                                            </span>
                                        </td>
                                        <td style="color:#A89BBF;font-size:12px;"><?php echo htmlspecialchars($log['error_message'] ?? '—'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <?php elseif ($isDoctor): ?>
        <section id="patients" class="tabcontent" hidden>
            <div class="card">
                <h2>My Patients</h2>
                <p>Search for patients by name to view their records or schedule appointments.</p>

                <?php if ($doctorMessage): ?>
                    <div class="alert <?php echo $doctorMessageType; ?>">
                        <?php echo htmlspecialchars($doctorMessage); ?>
                    </div>
                <?php endif; ?>
                
                <form method="GET" action="" style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;">
                    <input type="hidden" name="tab" value="patients">
                    <input type="text" name="search" placeholder="Search by patient name or ID..." 
                           value="<?php echo htmlspecialchars($searchQuery); ?>"
                           style="flex:1;min-width:200px;padding:10px 14px;border-radius:8px;border:1px solid rgba(190,151,198,0.25);background:rgba(255,255,255,0.05);color:var(--pastel-petal);font-size:14px;">
                    <button type="submit" class="btn">Search</button>
                </form>

                <?php if (!empty($searchQuery)): ?>
                    <div style="margin-top:20px;">
                        <h3 style="color:var(--lilac);font-size:14px;margin-bottom:12px;">
                            Search Results for "<?php echo htmlspecialchars($searchQuery); ?>" (<?php echo count($searchResults); ?> found)
                        </h3>
                        <?php if (empty($searchResults)): ?>
                            <div class="empty-state">
                                <p>No patients found matching your search.</p>
                            </div>
                        <?php else: ?>
                            <div class="patient-list">
                                <?php foreach ($searchResults as $patient): ?>
                                    <div class="patient-item">
                                        <div class="patient-info">
                                            <h4><?php echo htmlspecialchars($patient['full_name']); ?></h4>
                                            <p>
                                                <strong>ID:</strong> <?php echo htmlspecialchars($patient['client_id']); ?> • 
                                                <strong>DOB:</strong> <?php echo $patient['dob'] ? date('M j, Y', strtotime($patient['dob'])) : 'N/A'; ?> • 
                                                <strong>Status:</strong> <?php echo htmlspecialchars($patient['status'] ?? 'Active'); ?>
                                            </p>
                                            <p>
                                                <strong>Phone:</strong> <?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?> • 
                                                <strong>Email:</strong> <?php echo htmlspecialchars($patient['email'] ?? 'N/A'); ?>
                                            </p>
                                            <?php if ($patient['last_session']): ?>
                                                <p style="color:var(--lilac);font-size:12px;">Last session: <?php echo date('M j, Y', strtotime($patient['last_session'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="patient-actions">
                                            <?php if ($patient['user_id']): ?>
                                                <button class="btn secondary" onclick="alert('View patient record feature coming soon!');">View Record</button>
                                                <button class="btn" onclick="openScheduleModal(<?php echo $patient['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($patient['full_name'])); ?>');">Schedule</button>
                                            <?php else: ?>
                                                <span style="font-size:12px;color:#A89BBF;">No user account linked</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php else: ?>
        <section id="appointments" class="tabcontent" hidden>
            <div class="card">
                <h2>My Appointments</h2>
                <p>View and manage your scheduled appointments with your care team.</p>

                <?php if ($patientMessage): ?>
                    <div class="alert <?php echo $patientMessageType; ?>">
                        <?php echo htmlspecialchars($patientMessage); ?>
                    </div>
                <?php endif; ?>

                <div class="appointment-list" style="margin-top:16px;">
                    <?php if (empty($appointments)): ?>
                        <div class="empty-state">
                            <p>No upcoming appointments scheduled.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($appointments as $appt): ?>
                            <div class="appointment-item" style="flex-direction:column;align-items:stretch;">
                                <div style="display:flex;justify-content:space-between;align-items:center;width:100%;">
                                    <div class="details">
                                        <h4><?php echo htmlspecialchars($appt['appointment_type']); ?> with <?php echo formatDoctorName($appt['doctor_name']); ?></h4>
                                        <p><?php echo date('M j, Y \a\t g:i A', strtotime($appt['appointment_date'])); ?> • <?php echo htmlspecialchars($appt['department']); ?></p>
                                    </div>
                                    <span class="status <?php echo $appt['status'] === 'confirmed' ? 'confirmed' : ($appt['status'] === 'pending' ? 'pending' : 'upcoming'); ?>">
                                        <?php echo ucfirst($appt['status']); ?>
                                        <?php if ($appt['status'] === 'pending' && isset($appt['pending_action'])): ?>
                                            (<?php echo $appt['pending_action'] === 'cancel' ? 'Cancellation' : 'Change'; ?> Requested)
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if ($appt['status'] !== 'pending'): ?>
                                <div class="appointment-actions" style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;">
                                    <?php if ($appt['status'] === 'scheduled'): ?>
                                        <form method="POST" action="?tab=appointments" style="display:inline;">
                                            <input type="hidden" name="action" value="confirm_appointment">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                            <button type="submit" class="btn" style="padding:6px 12px;font-size:13px;">Confirm</button>
                                        </form>
                                    <?php endif; ?>
                                    <button type="button" class="btn secondary" style="padding:6px 12px;font-size:13px;" 
                                            onclick="openChangeModal(<?php echo $appt['id']; ?>, '<?php echo date('Y-m-d', strtotime($appt['appointment_date'])); ?>', '<?php echo date('H:i', strtotime($appt['appointment_date'])); ?>');">
                                        Request Change
                                    </button>
                                    <form method="POST" action="?tab=appointments" style="display:inline;" onsubmit="return confirm('Are you sure you want to request cancellation?');">
                                        <input type="hidden" name="action" value="request_cancel">
                                        <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                        <button type="submit" class="btn secondary" style="padding:6px 12px;font-size:13px;color:#FC8181;border-color:rgba(252,129,129,0.3);">Cancel</button>
                                    </form>
                                </div>
                                <?php else: ?>
                                <div style="margin-top:12px;font-size:13px;color:var(--lilac);">
                                    Waiting for doctor approval...
                                    <?php if ($appt['pending_action'] === 'change' && $appt['pending_date']): ?>
                                        <br>Requested new time: <?php echo date('M j, Y \a\t g:i A', strtotime($appt['pending_date'])); ?>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <section id="notifications" class="tabcontent" hidden>
            <div class="card">
                <h2>Notifications</h2>
                <p>Updates and messages from your care team.</p>
                <div style="margin-top:16px;">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <p>No notifications yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notif): ?>
                            <div class="notification-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                                <strong><?php echo htmlspecialchars($notif['title']); ?></strong>
                                <p style="margin:6px 0 0;"><?php echo htmlspecialchars($notif['message']); ?></p>
                                <div class="time"><?php echo timeAgo($notif['created_at']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <section id="patchnotes" class="tabcontent" hidden>
            <div class="card">
                <h2>Patch Notes</h2>
                <p>Version 0.0.0 Updated by Clover</p>
            </div>
        </section>

        <footer>
            © 2025 EMR Mental Health System • HIPAA Compliant Access
        </footer>
    </div>

    <?php if ($isDoctor): ?>
    <!-- Schedule Appointment Modal -->
    <div id="scheduleModal" class="modal-overlay" onclick="if(event.target === this) closeScheduleModal();">
        <div class="modal">
            <h3>Schedule Appointment</h3>
            <p id="modalPatientName">with Patient Name</p>
            
            <form method="POST" action="?tab=patients">
                <input type="hidden" name="action" value="create_appointment">
                <input type="hidden" name="patient_id" id="modalPatientId">
                
                <div class="form-group">
                    <label for="appointment_date">Date *</label>
                    <input type="date" id="appointment_date" name="appointment_date" required 
                           min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="appointment_time">Time *</label>
                    <input type="time" id="appointment_time" name="appointment_time" required>
                </div>
                
                <div class="form-group">
                    <label for="appointment_type">Appointment Type *</label>
                    <select id="appointment_type" name="appointment_type" required>
                        <option value="">Select type...</option>
                        <option value="Initial Consultation">Initial Consultation</option>
                        <option value="Follow-up Session">Follow-up Session</option>
                        <option value="Therapy Session">Therapy Session</option>
                        <option value="Medication Review">Medication Review</option>
                        <option value="Check-in">Check-in</option>
                        <option value="Crisis Intervention">Crisis Intervention</option>
                        <option value="Group Therapy">Group Therapy</option>
                        <option value="Assessment">Assessment</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="department">Department</label>
                    <select id="department" name="department">
                        <option value="General">General</option>
                        <option value="Psychiatry">Psychiatry</option>
                        <option value="Psychology">Psychology</option>
                        <option value="Counseling">Counseling</option>
                        <option value="Social Work">Social Work</option>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn secondary" onclick="closeScheduleModal();">Cancel</button>
                    <button type="submit" class="btn">Schedule Appointment</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($isPatient): ?>
    <!-- Request Change Modal -->
    <div id="changeModal" class="modal-overlay" onclick="if(event.target === this) closeChangeModal();">
        <div class="modal">
            <h3>Request Appointment Change</h3>
            <p>Select a new date and time for your appointment. Your doctor will need to approve this change.</p>
            
            <form method="POST" action="?tab=appointments">
                <input type="hidden" name="action" value="request_change">
                <input type="hidden" name="appointment_id" id="changeApptId">
                
                <div class="form-group">
                    <label for="new_date">New Date *</label>
                    <input type="date" id="new_date" name="new_date" required 
                           min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="new_time">New Time *</label>
                    <input type="time" id="new_time" name="new_time" required>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn secondary" onclick="closeChangeModal();">Cancel</button>
                    <button type="submit" class="btn">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        (function(){
            const tabs = document.querySelectorAll('.tab-btn');
            const contents = document.querySelectorAll('.tabcontent');

            function show(targetId){
                contents.forEach(c => {
                    c.hidden = (c.id !== targetId);
                });
                tabs.forEach(t => {
                    const selected = t.getAttribute('data-target') === targetId;
                    t.classList.toggle('active', selected);
                    t.setAttribute('aria-selected', selected ? 'true' : 'false');
                });
            }

            tabs.forEach(t => {
                t.addEventListener('click', () => show(t.getAttribute('data-target')));
            });

            // Check URL params for tab preference (e.g., after search or form submission)
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            const searchParam = urlParams.get('search');
            
            // If there's a search param, show the patients tab
            if (searchParam) {
                show('patients');
            } else if (tabParam && document.getElementById(tabParam)) {
                show(tabParam);
            } else if (tabs.length) {
                show(tabs[0].getAttribute('data-target'));
            }
        })();

        // Doctor: Modal functions for scheduling
        function openScheduleModal(patientId, patientName) {
            const modal = document.getElementById('scheduleModal');
            if (modal) {
                document.getElementById('modalPatientId').value = patientId;
                document.getElementById('modalPatientName').textContent = 'with ' + patientName;
                modal.classList.add('active');
            }
        }

        function closeScheduleModal() {
            const modal = document.getElementById('scheduleModal');
            if (modal) modal.classList.remove('active');
        }

        // Patient: Modal functions for change request
        function openChangeModal(apptId, currentDate, currentTime) {
            const modal = document.getElementById('changeModal');
            if (modal) {
                document.getElementById('changeApptId').value = apptId;
                document.getElementById('new_date').value = currentDate;
                document.getElementById('new_time').value = currentTime;
                modal.classList.add('active');
            }
        }

        function closeChangeModal() {
            const modal = document.getElementById('changeModal');
            if (modal) modal.classList.remove('active');
        }

        // Close modals on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeScheduleModal();
                closeChangeModal();
            }
        });
    </script>
</body>
</html>
