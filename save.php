<?php
$dataFile = "game_data.json";

// قراءة بيانات الـ POST
$json = file_get_contents("php://input");
if($json){
    $data = json_decode($json,true);
    if($data){
        file_put_contents($dataFile,json_encode($data));
        echo json_encode(["status"=>"success"]);
        exit;
    }
}
echo json_encode(["status"=>"error"]);