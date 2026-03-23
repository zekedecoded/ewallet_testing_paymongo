<?php
// index.php — entry point redirect
// Visitors hitting the root URL get sent to login
header('Location: /login.php');
exit;
