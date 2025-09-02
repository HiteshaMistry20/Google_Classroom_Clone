<?php
// Redirect to the root class_details.php file
$class_id = isset($_GET['id']) ? $_GET['id'] : '';
header("Location: ../class_details.php?id=" . $class_id);
exit();
?>
