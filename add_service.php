<?php
require_once __DIR__ . '/bootstrap.php';
// Check if the user is logged in and is a Giver, otherwise redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "giver"){
    header("location: " . site_href('login.php'));
    exit;
}

$title = $description = $category = $expertise_level = $availability = $preferred_exchange = "";
$title_err = $description_err = $category_err = $expertise_level_err = $availability_err = $preferred_exchange_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){

    // CSRF protection
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        echo "Invalid request. Please refresh and try again.";
        exit;
    }

    // Validate title
    if(empty(trim($_POST["title"]))){
        $title_err = "Please enter a title for the service.";
    } else{
        $title = trim($_POST["title"]);
        if (mb_strlen($title) > 150) { $title_err = "Title must be 150 characters or less."; }
    }

    // Validate description
    if(empty(trim($_POST["description"]))){
        $description_err = "Please enter a description for the service.";
    } else{
        $description = trim($_POST["description"]);
        if (mb_strlen($description) > 2000) { $description_err = "Description must be 2000 characters or less."; }
    }

    // Validate category
    if(empty(trim($_POST["category"]))){
        $category_err = "Please select a category.";
    } else{
        $category = trim($_POST["category"]);
    }

    // Validate expertise level
    if(empty(trim($_POST["expertise_level"]))){
        $expertise_level_err = "Please enter your expertise level.";
    } else{
        $expertise_level = trim($_POST["expertise_level"]);
        if (mb_strlen($expertise_level) > 100) { $expertise_level_err = "Expertise must be 100 characters or less."; }
    }

    // Validate availability
    if(empty(trim($_POST["availability"]))){
        $availability_err = "Please specify your availability.";
    } else{
        $availability = trim($_POST["availability"]);
        if (mb_strlen($availability) > 2000) { $availability_err = "Availability must be 2000 characters or less."; }
    }

    // Validate preferred exchange
    if(empty(trim($_POST["preferred_exchange"]))){
        $preferred_exchange_err = "Please specify preferred exchange terms.";
    } else{
        $preferred_exchange = trim($_POST["preferred_exchange"]);
        if (mb_strlen($preferred_exchange) > 1000) { $preferred_exchange_err = "Preferred exchange must be 1000 characters or less."; }
    }

    // Check input errors before inserting in database
    if(empty($title_err) && empty($description_err) && empty($category_err) && empty($expertise_level_err) && empty($availability_err) && empty($preferred_exchange_err)){
        // Prepare an insert statement
        $sql = "INSERT INTO services (giver_id, title, description, category, expertise_level, availability, preferred_exchange) VALUES (?, ?, ?, ?, ?, ?, ?)";

        if($stmt = $conn->prepare($sql)){
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("issssss", $param_giver_id, $param_title, $param_description, $param_category, $param_expertise_level, $param_availability, $param_preferred_exchange);

            // Set parameters
            $param_giver_id = $_SESSION["user_id"];
            $param_title = $title;
            $param_description = $description;
            $param_category = $category;
            $param_expertise_level = $expertise_level;
            $param_availability = $availability;
            $param_preferred_exchange = $preferred_exchange;

            // Attempt to execute the prepared statement
            if($stmt->execute()){
                header("location: dashboard.php");
            } else{
                echo "Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }

    // Close connection
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Service</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
</head>
<body>
    <?php render_header(); ?>
    <div class="wrapper">
        <h2>Add New Service</h2>
        <p>Please fill this form to offer a new service for sharing.</p>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <?php echo csrf_field(); ?>
            <div class="form-group <?php echo (!empty($title_err)) ? 'has-error' : ''; ?>">
                <label>Service Title</label>
                <input type="text" name="title" class="form-control" value="<?php echo $title; ?>">
                <span class="help-block"><?php echo $title_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($description_err)) ? 'has-error' : ''; ?>">
                <label>Description</label>
                <textarea name="description" class="form-control"><?php echo $description; ?></textarea>
                <span class="help-block"><?php echo $description_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($category_err)) ? 'has-error' : ''; ?>">
                <label>Category</label>
                <select name="category" class="form-control">
                    <option value="">Select Category</option>
                    <option value="tutoring" <?php echo ($category == "tutoring") ? "selected" : ""; ?>>Tutoring</option>
                    <option value="repairs" <?php echo ($category == "repairs") ? "selected" : ""; ?>>Minor Repairs</option>
                    <option value="gardening" <?php echo ($category == "gardening") ? "selected" : ""; ?>>Gardening</option>
                    <option value="tech_support" <?php echo ($category == "tech_support") ? "selected" : ""; ?>>Tech Support</option>
                    <option value="other" <?php echo ($category == "other") ? "selected" : ""; ?>>Other</option>
                </select>
                <span class="help-block"><?php echo $category_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($expertise_level_err)) ? 'has-error' : ''; ?>">
                <label>Expertise Level</label>
                <input type="text" name="expertise_level" class="form-control" value="<?php echo $expertise_level; ?>" placeholder="e.g., Beginner, Intermediate, Advanced">
                <span class="help-block"><?php echo $expertise_level_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($availability_err)) ? 'has-error' : ''; ?>">
                <label>Availability</label>
                <textarea name="availability" class="form-control" placeholder="e.g., Weekends, Mon-Wed evenings"><?php echo $availability; ?></textarea>
                <span class="help-block"><?php echo $availability_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($preferred_exchange_err)) ? 'has-error' : ''; ?>">
                <label>Preferred Exchange Terms</label>
                <textarea name="preferred_exchange" class="form-control" placeholder="e.g., Barter for gardening help, small fee, goodwill"><?php echo $preferred_exchange; ?></textarea>
                <span class="help-block"><?php echo $preferred_exchange_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Add Service">
                <a href="<?php echo site_href('dashboard.php'); ?>" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
    <?php render_footer(); ?>
</body>
</html>

