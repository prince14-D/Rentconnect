<?php
session_start();
include "app_init.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$message = '';

if (isset($_GET['action'], $_GET['request_id'])) {
    $action = $_GET['action'];
    $request_id = intval($_GET['request_id']);

    if ($action === 'approve' || $action === 'decline') {
        $new_status = ($action === 'approve') ? 'approved' : 'declined';

      if (rc_mig_update_request_status_for_landlord($conn, $request_id, $landlord_id, $new_status)) {
            $message = "Request $new_status successfully.";
        } else {
            $message = "Could not update request. It may be handled already or not your property.";
        }
    }
}

  $results = rc_mig_get_landlord_requests_grouped($conn, $landlord_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="apple-touch-icon" href="/favicon.svg" />
<link rel="icon" href="/favicon.svg" />
<title>Rental Requests - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --ink: #1f2430;
  --muted: #5d6579;
  --brand: #1f8f67;
  --brand-deep: #15543e;
  --line: rgba(31, 36, 48, 0.12);
  --shadow: 0 18px 36px rgba(19, 36, 33, 0.14);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Manrope', sans-serif;
  color: var(--ink);
  background:
    radial-gradient(circle at 10% 4%, rgba(255, 122, 47, 0.22), transparent 34%),
    radial-gradient(circle at 92% 8%, rgba(31, 143, 103, 0.2), transparent 30%),
    linear-gradient(165deg, #f9f6ef 0%, #f2f7f8 58%, #fffdfa 100%);
}
.container { width: min(1140px, 94vw); margin: 0 auto; }
.header-card {
  margin-top: 22px;
  border-radius: 18px;
  color: #fff;
  padding: 18px;
  background: linear-gradient(140deg, rgba(16, 62, 79, 0.93), rgba(31, 143, 103, 0.86));
  box-shadow: var(--shadow);
}
.header-card h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: clamp(1.4rem, 2.8vw, 2rem);
  letter-spacing: -0.02em;
  margin-bottom: 6px;
}

.message {
  margin-top: 10px;
  padding: 10px 12px;
  border-radius: 10px;
  color: #145734;
  background: rgba(39, 165, 106, 0.12);
  border: 1px solid rgba(39, 165, 106, 0.3);
  font-weight: 700;
}

.grid {
  margin-top: 14px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 12px;
}

.card {
  background: rgba(255,255,255,0.93);
  border: 1px solid rgba(255,255,255,0.9);
  border-radius: 14px;
  box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
  padding: 14px;
}
.card h2 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1.1rem;
  margin-bottom: 6px;
}
.card .meta { color: var(--muted); margin-bottom: 8px; font-size: 0.92rem; }

.renter {
  border: 1px solid var(--line);
  border-radius: 11px;
  padding: 10px;
  margin-top: 8px;
}

.renter-head {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 6px;
}

.renter-avatar {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  object-fit: cover;
  border: 1px solid var(--line);
  background: #f1f4f8;
}

.renter-name {
  font-weight: 700;
}

.renter-meta {
  color: var(--muted);
  font-size: 0.85rem;
  margin: 2px 0;
}

.status {
  display: inline-block;
  padding: 4px 9px;
  border-radius: 999px;
  color: #fff;
  font-size: 0.78rem;
  font-weight: 800;
  margin-bottom: 6px;
}
.pending { background: #ff9f2f; }
.approved { background: #1f8f67; }
.declined { background: #e5554f; }

.actions {
  margin-top: 7px;
  display: flex;
  gap: 7px;
  flex-wrap: wrap;
}
.actions a {
  text-decoration: none;
  border-radius: 8px;
  padding: 7px 10px;
  font-size: 0.82rem;
  font-weight: 700;
  color: #fff;
}
.approve { background: linear-gradient(140deg, #1f8f67, #15543e); }
.decline { background: linear-gradient(140deg, #e5554f, #d43c35); }
.chat { background: linear-gradient(140deg, #2374cc, #1b5ca3); }

.back {
  display: inline-block;
  margin: 14px 0 24px;
  text-decoration: none;
  font-weight: 700;
  color: #15543e;
}
</style>
</head>
<body>
<main class="container">
  <section class="header-card">
    <h1>Requests for Your Properties</h1>
  </section>

  <?php if ($message): ?>
    <p class="message"><?php echo htmlspecialchars($message); ?></p>
  <?php endif; ?>

  <section class="grid">
    <?php if (!empty($results)): ?>
      <?php foreach ($results as $row): ?>
        <article class="card">
          <h2><?php echo htmlspecialchars($row['property_title']); ?></h2>
          <p class="meta">$<?php echo number_format($row['price']); ?> | <?php echo htmlspecialchars($row['location']); ?></p>
          <?php
          $renters_list = [];
          if (isset($row['renters_list']) && is_array($row['renters_list'])) {
              $renters_list = $row['renters_list'];
          } elseif (!empty($row['renters'])) {
              $renters = explode('||', (string) $row['renters']);
              foreach ($renters as $r) {
                  if (trim($r) === '') {
                      continue;
                  }
                  $parts = explode('|', $r);
                  $renters_list[] = [
                      'name' => (string) ($parts[0] ?? 'Renter'),
                      'id' => (int) ($parts[1] ?? 0),
                      'status' => (string) ($parts[2] ?? 'pending'),
                      'request_id' => (int) ($parts[3] ?? 0),
                      'email' => (string) ($parts[4] ?? ''),
                      'phone' => (string) ($parts[5] ?? ''),
                      'profile_pic' => (string) ($parts[6] ?? ''),
                      'requested_at' => '',
                  ];
              }
          }

          foreach ($renters_list as $renter):
              $r_name = (string) ($renter['name'] ?? 'Renter');
              $r_id = (int) ($renter['id'] ?? 0);
              $r_status = (string) ($renter['status'] ?? 'pending');
              $r_request_id = (int) ($renter['request_id'] ?? 0);
              $r_email = (string) ($renter['email'] ?? '');
              $r_phone = (string) ($renter['phone'] ?? '');
              $r_profile_pic = (string) ($renter['profile_pic'] ?? '');
              $r_requested_at = (string) ($renter['requested_at'] ?? '');
              if ($r_profile_pic === '') {
                  $r_profile_pic = 'images/default-avatar.png';
              }
          ?>
            <div class="renter">
              <span class="status <?php echo htmlspecialchars($r_status); ?>"><?php echo ucfirst($r_status); ?></span>
              <div class="renter-head">
                <img src="<?php echo htmlspecialchars($r_profile_pic); ?>" alt="Renter photo" class="renter-avatar">
                <div>
                  <p class="renter-name"><?php echo htmlspecialchars($r_name); ?></p>
                  <?php if ($r_email !== ''): ?>
                    <p class="renter-meta"><?php echo htmlspecialchars($r_email); ?></p>
                  <?php endif; ?>
                </div>
              </div>
              <?php if ($r_phone !== ''): ?>
                <p class="renter-meta">Phone: <?php echo htmlspecialchars($r_phone); ?></p>
              <?php endif; ?>
              <?php if ($r_requested_at !== ''): ?>
                <p class="renter-meta">Requested: <?php echo htmlspecialchars($r_requested_at); ?></p>
              <?php endif; ?>
              <div class="actions">
                <?php if ($r_status === 'pending'): ?>
                  <a href="?action=approve&request_id=<?php echo (int) $r_request_id; ?>" class="approve">Approve</a>
                  <a href="?action=decline&request_id=<?php echo (int) $r_request_id; ?>" class="decline">Decline</a>
                <?php elseif ($r_status === 'approved'): ?>
                  <a href="chat.php?property_id=<?php echo (int) $row['property_id']; ?>&with=<?php echo (int) $r_id; ?>" class="chat">Chat</a>
                <?php else: ?>
                  <span>Declined</span>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </article>
      <?php endforeach; ?>
    <?php else: ?>
      <p>No requests found for your properties.</p>
    <?php endif; ?>
  </section>

  <a href="landlord_dashboard.php" class="back">Back to Dashboard</a>
</main>
</body>
</html>
