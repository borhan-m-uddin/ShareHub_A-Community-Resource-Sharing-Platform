<?php
require_once __DIR__ . '/bootstrap.php';
require_login();
if (($_SESSION['role'] ?? '') !== 'seeker') { header('Location: ' . site_href('login.php')); exit; }

$item_id = $_GET["item_id"] ?? null;
$item = null;
$request_err = "";

// Fetch item details
if ($item_id) {
    $sql = "SELECT i.item_id, i.title, i.description, i.category, i.location, i.terms_of_use, u.username as giver_username FROM items i JOIN users u ON i.giver_id = u.user_id WHERE i.item_id = ? AND i.availability_status = \"available\"";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $item_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $item = $result->fetch_assoc();
            } else {
                echo "Item not found or not available.";
                exit;
            }
        } else {
            echo "Oops! Something went wrong. Please try again later.";
            exit;
        }
        $stmt->close();
    }
} else {
    echo "Invalid item ID.";
    exit;
}

// Process request submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) { echo "Invalid request. Please refresh and try again."; exit; }
    $requested_dates = trim($_POST["requested_dates"]);

    if (empty($requested_dates)) {
        $request_err = "Please specify the requested dates/duration.";
    } elseif (mb_strlen($requested_dates) > 1000) {
        $request_err = "Requested details must be 1000 characters or less.";
    } else {
        $sql = "INSERT INTO requests (seeker_id, resource_type, resource_id, requested_dates, status) VALUES (?, ?, ?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $param_seeker_id = $_SESSION["user_id"];
            $param_resource_type = "item";
            $param_resource_id = $item_id;
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
    <title>Request Item: <?php echo htmlspecialchars($item["title"] ?? ''); ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
    <?php render_header(); ?>
    <div class="wrapper">
        <h2>Request Item: <?php echo htmlspecialchars($item["title"]); ?></h2>
        <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($item["description"])); ?></p>
        <p><strong>Category:</strong> <?php echo htmlspecialchars(ucfirst($item["category"])); ?></p>
        <p><strong>Location:</strong> <?php echo htmlspecialchars($item["location"]); ?></p>
        <p><strong>Terms of Use:</strong> <?php echo nl2br(htmlspecialchars($item["terms_of_use"])); ?></p>
        <p><strong>Listed by:</strong> <?php echo htmlspecialchars($item["giver_username"]); ?></p>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?item_id=" . $item_id; ?>" method="post">
            <?php echo csrf_field(); ?>
            <div class="form-group <?php echo (!empty($request_err)) ? 'has-error' : ''; ?>">
                <label>Requested Dates/Duration</label>
                <textarea name="requested_dates" class="form-control" placeholder="e.g., Aug 25-27, 2025 or for 3 days"></textarea>
                <span class="help-block"><?php echo $request_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Submit Request">
                <a href="<?php echo site_href('view_items.php'); ?>" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
    </main>
</body>
</html>

