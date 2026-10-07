<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (isset($_SESSION['auth_user'])) {
    echo json_encode(array(
        'authenticated' => true,
        'user' => $_SESSION['auth_user']
    ));
} else {
    echo json_encode(array(
        'authenticated' => false
    ));
}
