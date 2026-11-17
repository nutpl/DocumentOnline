<?php

if(session_status()==PHP_SESSION_NONE){
    session_start();
}

function success($message){

    $_SESSION['success']=$message;

}

function error($message){

    $_SESSION['error']=$message;

}

function showFlash(){

    if(isset($_SESSION['success'])){
        $message = htmlspecialchars((string)$_SESSION['success'], ENT_QUOTES, 'UTF-8');
        echo "<div hidden data-success-alert data-success-title='ดำเนินการสำเร็จ' data-success-text='{$message}'></div>";

        unset($_SESSION['success']);

    }

    if(isset($_SESSION['error'])){
        $message = htmlspecialchars((string)$_SESSION['error'], ENT_QUOTES, 'UTF-8');
        echo "<div hidden data-success-alert data-success-icon='error' data-success-title='ดำเนินการไม่สำเร็จ' data-success-text='{$message}' data-success-timer='0'></div>";

        unset($_SESSION['error']);

    }

}
