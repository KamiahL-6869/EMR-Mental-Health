<?php
// This is the login file that processes the login form submission
// and handles user authentication for the EMR Mental Health System.

session_start();
require "db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, password_hash, role FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        if (password_verify($password, $user["password_hash"])) {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $username;
            $_SESSION["role"] = $user["role"];

            header("Location: dashboard.php");
            exit();
        } else {
            $message = "Incorrect password";
        }
    } else {
        $message = "Username not found";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EMR Mental Health – Secure Login</title>

    <style>
        :root{
            --space-indigo: #2E294E;
            --pastel-petal: #EFBCD5;
            --lilac: #BE97C6;
            --lavender: #8661C1;
            --charcoal: #4B5267;
            --bg: var(--charcoal);
            --card-bg: rgba(190,151,198,0.12);
            --max-width: 980px;
            --radius: 10px;
        }

        *{box-sizing:border-box}

        body{
            margin:0;
            font-family: Inter, 'Comic Sans MS', sans-serif;
            background: linear-gradient(180deg,var(--bg),#3f4456);
            color: var(--lavender);
            padding:28px;
            display:flex;
            justify-content:center;
        }

        .page{
            width:100%;
            max-width:var(--max-width);
            border:10px solid var(--space-indigo);
            border-radius:10px;
            background: linear-gradient(180deg, rgba(46,41,78,0.98), rgba(75,82,103,0.95));
            padding:28px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
        }

        .header-bar{
            display:flex;
            align-items:center;
            gap:18px;
            margin-bottom:20px;
            color:var(--pastel-petal);
        }

        .header-logo{
            width:64px;
            height:64px;
            border-radius:12px;
            background: linear-gradient(135deg,var(--lilac),var(--pastel-petal));
            display:flex;
            align-items:center;
            justify-content:center;
            font-weight:700;
            color:var(--space-indigo);
            font-size:20px;
        }

        .header-title{font-size:20px;margin:0;color:var(--pastel-petal);font-weight:700}

        .login-card {
            width: 100%;
            max-width: 420px;
            background: linear-gradient(180deg, rgba(46,41,78,0.85), rgba(75,82,103,0.65));
            margin: 10px auto;
            padding: 28px;
            border-radius: 12px;
            border: 1px solid rgba(190,151,198,0.28);
            box-shadow: 0 6px 20px rgba(0,0,0,0.18);
            color: #F2EAF9;
        }

        .login-card h2{margin:0 0 10px;color:var(--pastel-petal);font-size:20px}
        .login-card p{margin:0 0 18px;color:#E9DFF7}

        label{font-size:14px;color:#E9DFF7;font-weight:600}

        input{width:100%;padding:12px;margin:6px 0 16px;border-radius:10px;border:1px solid rgba(239,188,213,0.08);background:rgba(255,255,255,0.04);color:var(--pastel-petal);}

        button{width:100%;padding:12px;background:var(--pastel-petal);color:var(--space-indigo);border:none;border-radius:10px;font-size:16px;font-weight:700;cursor:pointer;box-shadow: 0 6px 14px rgba(46,41,78,0.12);}
        button:hover{transform:translateY(-2px);box-shadow: 0 14px 30px rgba(46,41,78,0.18)}

        .footer{margin-top:18px;color:#D6C7E8;text-align:center;font-size:13px}

        .error-message{
            background: rgba(220,53,69,0.15);
            border: 1px solid rgba(220,53,69,0.4);
            color: #f8d7da;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            text-align: center;
            font-size: 14px;
        }

        @media (max-width:560px){
            .header-title{font-size:18px}
        }
    </style>
</head>

<body>

    <div class="page">

        <div class="header-bar">
        <div class="header-logo">EMR</div>
        <div class="header-title">EMR Mental Health – Secure Access Portal</div>
    </div>

        <div class="login-card">
        <h2>Staff Login</h2>
        <p>Authorized personnel only. All access is monitored and logged.</p>

        <?php if ($message): ?>
            <div class="error-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Log In</button>
        </form>
    </div>

        <div class="footer">
            © 2025 EMR Mental Health System • HIPAA Compliant Access
        </div>

    </div>

</body>
</html>
