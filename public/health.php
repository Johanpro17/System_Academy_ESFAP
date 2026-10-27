<?php
require_once __DIR__ . '/../core/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$estado=['status'=>'ok','app'=>APP_NOMBRE,'time'=>date('c')];
try{
  $db=db();
  $s=$db->query('SELECT 1 AS uno');
  $r=$s->fetch();
  $estado['db']=$r&&$r['uno']==1?'up':'down';
}catch(Throwable $e){
  $estado['db']='down'; http_response_code(503);
}
echo json_encode($estado, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);