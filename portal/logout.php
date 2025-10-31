<?php
session_start();
unset($_SESSION['portal_user']);
header('Location: login.php');
exit();
