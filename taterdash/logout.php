<?php
session_start();
session_destroy();
header('Location: /taterdash-app/taterdash/login.php');
exit;
