<?php
/**
 * Created by PhpStorm.
 * User: xcg
 * Date: 2019/2/27
 * Time: 16:02
 */
/**
 * 原生php调用
 */
$data = [
    'action' => 'call1',//行为名称
    'arg' => [
        'args1' => 'args1',
        'args2' => 'args2'
    ]
];

$raw = json_encode($data);

$fp = stream_socket_client('tcp://127.0.0.1:9526');
fwrite($fp, pack('N', strlen($raw)) . $raw);

$data = fread($fp, 65533);
//做长度头部校验
$len = unpack('N', $data);
$data = substr($data, '4');
if (strlen($data) != $len[1]) {
    echo 'data error';
} else {
    $json = json_decode($data, true);
//    //这就是服务端返回的结果，
    var_dump($json);
}
fclose($fp);