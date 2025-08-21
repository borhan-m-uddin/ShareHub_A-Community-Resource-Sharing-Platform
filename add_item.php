<?php
// Initialize the session
session_start();

// Check if the user is logged in and is a Giver, otherwise redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "giver"){
    header("location: login.php");
    exit;
}

require_once "config.php";

$title = $description = $category = $location = $terms_of_use = "";
$title_err = $description_err = $category_err = $location_err = $terms_of_use_err = "";
$image_paths = [];

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){

    // Validate title
    if(empty(trim($_POST["title"]))){
        $title_err = "Please enter a title for the item.";
    } else{
        $title = trim($_POST["title"]);
    }

    // Validate description
    if(empty(trim($_POST["description"]))){
        $description_err = "Please enter a description for the item.";
    } else{
        $description = trim($_POST["description"]);
    }

    // Validate category
    if(empty(trim($_POST["category"]))){
        $category_err = "Please select a category.";
    } else{
        $category = trim($_POST["category"]);
    }

    // Validate location
    if(empty(trim($_POST["location"]))){
        $location_err = "Please enter the item's location.";
    } else{
        $location = trim($_POST["location"]);
    }

    // Validate terms of use
    if(empty(trim($_POST["terms_of_use"]))){
        $terms_of_use_err = "Please enter terms of use for the item.";
    } else{
        $terms_of_use = trim($_POST["terms_of_use"]);
    }

    // Handle image uploads
    if(isset($_FILES["item_images"]) && !empty($_FILES["item_images"]["name"][0])) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        foreach($_FILES["item_images"]["name"] as $key => $name) {
            $target_file = $target_dir . basename($_FILES["item_images"]["name"][$key]);
            $uploadOk = 1;
            $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

            // Check if image file is a actual image or fake image
            $check = getimagesize($_FILES["item_images"]["tmp_name"][$key]);
            if($check !== false) {
                $uploadOk = 1;
            } else {
                echo "File is not an image.";
                $uploadOk = 0;
            }

            // Check file size
            if ($_FILES["item_images"]["size"][$key] > 500000) { // 500KB
                echo "Sorry, your file is too large.";
                $uploadOk = 0;
            }

            // Allow certain file formats
            if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
            && $imageFileType != "gif" ) {
                echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
                $uploadOk = 0;
            }

            // Check if $uploadOk is set to 0 by an error
            if ($uploadOk == 0) {
                echo "Sorry, your file was not uploaded.";
            // if everything is ok, try to upload file
            } else {
                if (move_uploaded_file($_FILES["item_images"]["tmp_name"][$key], $target_file)) {
                    $image_paths[] = $target_file;
                } else {
                    echo "Sorry, there was an error uploading your file.";
                }
            }
        }
    }

    // Check input errors before inserting in database
    if(empty($title_err) && empty($description_err) && empty($category_err) && empty($location_err) && empty($terms_of_use_err)){
        // Prepare an insert statement
        $sql = "INSERT INTO items (giver_id, title, description, category, location, image_paths, terms_of_use) VALUES (?, ?, ?, ?, ?, ?, ?)";

        if($stmt = $conn->prepare($sql)){
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("issssss", $param_giver_id, $param_title, $param_description, $param_category, $param_location, $param_image_paths, $param_terms_of_use);

            // Set parameters
            $param_giver_id = $_SESSION["user_id"];
            $param_title = $title;
            $param_description = $description;
            $param_category = $category;
            $param_location = $location;
            $param_image_paths = implode(",", $image_paths); // Store image paths as a comma-separated string
            $param_terms_of_use = $terms_of_use;

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
    <title>Add New Item</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="wrapper">
        <h2>Add New Item</h2>
        <p>Please fill this form to add a new item for sharing.</p>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            <div class="form-group <?php echo (!empty($title_err)) ? 'has-error' : ''; ?>">
                <label>Title</label>
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
                    <option value="tools" <?php echo ($category == "tools") ? "selected" : ""; ?>>Tools</option>
                    <option value="books" <?php echo ($category == "books") ? "selected" : ""; ?>>Books</option>
                    <option value="electronics" <?php echo ($category == "electronics") ? "selected" : ""; ?>>Electronics</option>
                    <option value="garden" <?php echo ($category == "garden") ? "selected" : ""; ?>>Garden Equipment</option>
                    <option value="other" <?php echo ($category == "other") ? "selected" : ""; ?>>Other</option>
                </select>
                <span class="help-block"><?php echo $category_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($location_err)) ? 'has-error' : ''; ?>">
                <label>Location</label>
                <input type="text" name="location" class="form-control" value="<?php echo $location; ?>">
                <span class="help-block"><?php echo $location_err; ?></span>
            </div>
            <div class="form-group">
                <label>Item Images (Optional)</label>
                <input type="file" name="item_images[]" class="form-control" multiple>
            </div>
            <div class="form-group <?php echo (!empty($terms_of_use_err)) ? 'has-error' : ''; ?>">
                <label>Terms of Use</label>
                <textarea name="terms_of_use" class="form-control"><?php echo $terms_of_use; ?></textarea>
                <span class="help-block"><?php echo $terms_of_use_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Add Item">
                <a href="dashboard.php" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>

