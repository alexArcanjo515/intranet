<?php
session_start();
unset($_SESSION['rh_pin_ok'], $_SESSION['rh_pin_at'], $_SESSION['rh_pin_last']);
header('Location: /portal/rh/pin.php');
exit;
