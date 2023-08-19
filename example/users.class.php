<?php
require_once '../src/PicoORM.php';

class Users implements PicoORM {









}

// Test Harness

// create a new user
$userTest1 = new Users();
$userTest1->email = 'example@example.com';
$userTest1->password_hash = password_hash('password', PASSWORD_DEFAULT);
$userTest1->save();

// get a user by email address
$userTest2 = new Users('example@example.com', 'email');

// compare the two user objects
if ($userTest1 == $userTest2) {
    echo 'The two users are the same';
} else {
    echo 'The two users are different';
}