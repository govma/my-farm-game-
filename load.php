<?php
$dataFile = "game_data.json";

if(file_exists($dataFile)){
    $data = json_decode(file_get_contents($dataFile),true);
    if($data){
        echo json_encode($data);
        exit;
    }
}
echo json_encode([]);