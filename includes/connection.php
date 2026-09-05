<?php
$conn = mysqli_connect("127.0.0.1", "root", "", "online_voting");
if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }


?>