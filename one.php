<?php
function Judgment(string $body,?string $contentType=null):?string{
    //判断响应包content-type的类型是否为json或xml
    if($contentType!=null){
        $ct=strtolower(trim($contentType));
        $semicolonPos = strpos($ct,";");
        if($semicolonPos!==false){
            $ct=substr($ct,0,$semicolonPos);
        }
        if($ct==='application/json' || $ct === 'text/json' || str_ends_with($ct, '+json')){
            return '！！！是json格式！！！';
        }
        if($ct === 'application/xml' || $ct === 'text/xml' || str_ends_with($ct, '+xml')){
            return '！！！是xml格式！！！';
        }
    }
//    else{
//        return "响应包没有content-type字段";
//    }

    //通过响应体内容进行判断
    $tr=ltrim($body,"\xEF\xBB\xBF \t\r\n");
    //判断是否为json
    if($tr!=='' && ($tr[0]==='{' || $tr[0]==='[')){
        if (json_validate($tr)) {
            return '是json格式'.PHP_EOL;
        }
    }
    //判断是否为xml
    if(stripos($tr,'<?xml')===0 /*|| $tr[0]==='<'*/){
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($tr);
        if($xml!==null){
            return '是xml格式'.PHP_EOL;
        }
    }
    return "!!!不是json或xml，是其他格式!!!";
}

$opts=getopt("hc",["help","code"]);
$help = <<<EOT
所有参数如下：
  -h, --help     显示帮助
  -c, --code     仅显示响应码
EOT;

$urls = file('./url.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$timeout=10;
$mh=curl_multi_init();
$handles=[]; //句柄与url的映射
foreach($urls as $url){ //为每个url创建句柄并加入Multi
    $ch=curl_init();
    curl_setopt_array($ch,
        [
            CURLOPT_URL => $url, //连接的url
            CURLOPT_RETURNTRANSFER => true, //响应的内容返回为字符串，而不是直接输出到浏览器
            CURLOPT_FOLLOWLOCATION => true, //遇到3xx重定向时自动跳转
            CURLOPT_TIMEOUT => $timeout, //整体超时时间
            CURLOPT_HEADER => false,
            //关闭ssl校验
            CURLOPT_SSL_VERIFYPEER=>false,
            CURLOPT_SSL_VERIFYHOST=>false,
        ]
    );
    curl_multi_add_handle($mh,$ch);
    $handles[(int)$ch]=$ch; //用id作为索引，方便回收
}
do{
    $status=curl_multi_exec($mh,$active);
    curl_multi_select($mh,0.5); //阻塞等待0.5秒，降低cpu
}while ($active && $status === CURLM_OK);

$results=[]; //最终结果
foreach($handles as $ch){
    $url=curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);
    $curlErrNo = curl_errno($ch);
    $curlErr   = curl_error($ch);
    //print_r($curlErrNo).PHP_EOL;
    //print_r($curlErr).PHP_EOL;
    if($curlErrNo!==CURLE_OK){
        $results[$url]=[
            'error'=>$curlErrNo===CURLE_OPERATION_TIMEDOUT?"请求超时":$curlErr
        ];
    }else{
        $results[$url] = [
            'code'  => curl_getinfo($ch,CURLINFO_HTTP_CODE),
            'ctype' => curl_getinfo($ch,CURLINFO_CONTENT_TYPE),
            'body'  => curl_multi_getcontent($ch),
        ];
    }
    curl_multi_remove_handle($mh,$ch);  //从Multi解绑
    curl_close($ch);
}
curl_multi_close($mh);
//echo $results[$url];

foreach ($results as $url => $info) {
    if (isset($info['error'])) {
        echo $info['error'] . PHP_EOL . PHP_EOL;
        continue;
    }
    if(isset($opts['h']) || isset($opts['help'])){
        echo $help.PHP_EOL;
        exit(0);
    }
    echo "=== {$url} ???".PHP_EOL;
    echo "响应码:".$info['code'].PHP_EOL;
    echo "格式判断: " . judgment($info['body'], $info['ctype']) . PHP_EOL;
    if (isset($opts['c']) || isset($opts['code'])) {
        echo PHP_EOL;
        continue;
    }

    echo "响应内容:" . PHP_EOL . $info['body'] . PHP_EOL . PHP_EOL;
}

/*
//单url版
$url='';
$timeout=10;
$ch=curl_init();
curl_setopt_array($ch,
    [
        CURLOPT_URL => $url, //连接的url
        CURLOPT_RETURNTRANSFER => true, //响应的内容返回为字符串，而不是直接输出到浏览器
        CURLOPT_FOLLOWLOCATION => true, //遇到3xx重定向时自动跳转
        CURLOPT_TIMEOUT => $timeout, //整体超时时间
        CURLOPT_HEADER => false
    ]
);
$response=curl_exec($ch);
if($response===false){
    $errno=curl_errno($ch);
    $err=curl_error($ch);
    if($errno===CURLE_OPERATION_TIMEOUTED){
        echo "连接超时";
    }else{
        echo "curl函数报错：".curl_error($ch);
    }
} else{
    $httpCode=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $httpType=curl_getinfo($ch,CURLINFO_CONTENT_TYPE);
    echo "响应码:".$httpCode.PHP_EOL;
    echo "响应体格式:".$httpType.PHP_EOL;
    //echo "响应内容:".$response.PHP_EOL;
    echo "响应内容:".$response.PHP_EOL.Judgment($response,$httpType);
}
curl_close($ch);*/