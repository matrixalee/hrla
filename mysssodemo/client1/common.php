<?php

//输出错误响应json信息
function output_error( $error_msg, $error_code ) {
    header("Content-Type: application/json;charset=utf-8");
    echo json_encode(array(
        'status_code' => $error_code,
        'status_msg' => $error_msg
    ));
    exit;
}

//输出正确响应json信息
function output_success( $data ) {
    header("Content-Type: application/json;charset=utf-8");
    $param = array(
        'status_code' => 200,
        'status_msg' => 'success',
        'data' => $data
    );
    echo json_encode($param);
    exit;
}