<?php
require_once __DIR__ . '/bootstrap.php';
require_login();
if (($_SESSION['role'] ?? '') !== 'seeker') { header('Location: ' . site_href('login.php')); exit; }

$service_id = $_GET["service_id"] ?? null;
$service = null;
$request_err = "";

// Fetch service details
if ($service_id) {
    $sql = "SELECT s.service_id, s.title, s.description, s.category, s.expertise_level, s.availability, s.preferred_exchange, u.username as giver_username FROM services s JOIN users u ON s.giver_id = u.user_id WHERE s.service_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $service_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $service = $result->fetch_assoc();
            } else {
                echo "Service not found.";
                exit;
            }
        } else {
            echo "Oops! Something went wrong. Please try again later.";
            exit;
        }
        $stmt->close();
    }
} else {
    echo "Invalid service ID.";
    exit;
}

// Process request submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $requested_dates = trim($_POST["requested_dates"]);

    if (empty($requested_dates)) {
        $request_err = "Please specify the requested dates/duration or details.";
    } else {
        $sql = "INSERT INTO requests (seeker_id, resource_type, resource_id, requested_dates, status) VALUES (?, ?, ?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $param_seeker_id = $_SESSION["user_id"];
            $param_resource_type = "service";
            $param_resource_id = $service_id;
            $param_requested_dates = $requested_dates;
            $param_status = "pending";

            $stmt->bind_param("sisss", $param_seeker_id, $param_resource_type, $param_resource_id, $param_requested_dates, $param_status);

            if ($stmt->execute()) {
                header("location: my_requests.php"); // Redirect to a page showing user's requests
                exit;
            } else {
                echo "Error: Could not submit request. Please try again later.";
            }
            $stmt->close();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Service: <?php echo htmlspecialchars($service["title"] ?? ''); ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
    <?php render_header(); ?>
    <div class="wrapper">
        <h2>Request Service: <?php echo htmlspecialchars($service["title"]); ?></h2>
        <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($service["description"])); ?></p>
        <p><strong>Category:</strong> <?php echo htmlspecialchars(ucfirst($service["category"])); ?></p>
        <p><strong>Expertise Level:</strong> <?php echo htmlspecialchars($service["expertise_level"]); ?></p>
        <p><strong>Availability:</strong> <?php echo nl2br(htmlspecialchars($service["availability"])); ?></p>
        <p><strong>Preferred Exchange:</strong> <?php echo nl2br(htmlspecialchars($service["preferred_exchange"])); ?></p>
        <p><strong>Offered by:</strong> <?php echo htmlspecialchars($service["giver_username"]); ?></p>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?service_id=" . $service_id; ?>" method="post">
            <div class="form-group <?php echo (!empty($request_err)) ? 'has-error' : ''; ?>">
                <label>Requested Dates/Details</label>
                <textarea name="requested_dates" class="form-control" placeholder="e.g., Aug 28, 2025 at 3 PM for 2 hours, or specific details of your request"></textarea>
                <span class="help-block"><?php echo $request_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Submit Request">
                <a href="<?php echo site_href('view_services.php'); ?>" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
    </main>
</body>
</html>

