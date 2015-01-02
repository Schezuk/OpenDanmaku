<?php
require('libMysqli.php');
header("Access-Control-Allow-Origin: *");//无限制

//硬直与禁言设定
$const_ScoreNewLink = 10;//加10分
$const_DelayNewLink = 60;//60秒硬直

$linkage=trim((string)$_REQUEST['linkage']);
//$_GET和$_REQUEST已经urldecode()了！

//如果没有Cookie
if(!isset($_COOKIE['uid'])) die(json_err('cookie_empty',-1,'Error: No Cookie Submitted'));//返回空
$uid=intval($_COOKIE['uid']);
//获取Cookie对应用户数据,如果key不符合,退出
$result=NULL;
$count=safe_query('SELECT * FROM `user` WHERE `uid` = ?;', &$result, array('i',$uid));
if($count!=1) die(json_err('cookie_invalid',-1,'Error: Invalid Cookie'));//返回空
//!= == >= 代表作为数字比较
if($result[0]['key']!=$_COOKIE['key']) die(json_err('cookie_wrongkey',-1,'Error: Cookie with Wrong Key'));//key不符合
if($result[0]['status']==0) die(json_err('cookie_deleted',-1,'Error: Deleted Cookie'));//status禁用
if($result[0]['time']>=0) die(json_err('cookie_inactive',-1,'Error: Not Yet Active'));//time还在硬直中

//读取参数btih,并字符串化,小写化
$btih=trim(strtolower(strval($_REQUEST['btih'])));//读取参数btih
//如果是完整磁链,截取btih,btih长度为40
$pos=strpos($btih,"btih:");//len('btih:')===5
$btih=($pos===FALSE)?substr($btih,$pos+5,40):substr($btih,0,40);//注意$pos会自动转换,而$pos=0和$pos=FALSE截取时有区别
//检验btih长度(应该<=40)与有效性,即使btih仅由0-9组成也没关系,参见http://www.cnblogs.com/mincyw/archive/2011/02/10/1950733.html
if(strlen($btih)!==40 or !ctype_xdigit($btih)) die(json_err('btih_incorrect',-1,'Error: Link Not Correct'));

//读取参数btih,并字符串化,小写化
$btih=trim(strtolower(strval($_REQUEST['btih'])));//读取参数btih
//如果是完整磁链,截取btih,btih长度为40
$pos=strpos($btih,"btih:");//len('btih:')===5
$btih=($pos===FALSE)?substr($btih,$pos+5,40):substr($btih,0,40);//注意$pos会自动转换,而$pos=0和$pos=FALSE截取时有区别
//检验btih长度(应该<=40)与有效性,即使btih仅由0-9组成也没关系,参见http://www.cnblogs.com/mincyw/archive/2011/02/10/1950733.html
if(strlen($btih)!==40 or !ctype_xdigit($btih)) die(json_err('btih_incorrect',-1,'Error: Link Not Correct'));


//读取参数: BTIH1,BTIH2,time
	//检验BTIH有效性并小写化,"magnet:?xt=urn:btih:"长度为20,btih长度为40
	//即使btih仅由0-9组成也没关系,因为代码中不存在hex与unhex
	//btih1
	$btih1=(string)$_REQUEST['btih1'];//字符串
	if(strlen($btih1)>=60 and strpos($btih1,"magnet:?xt=urn:btih:")===0)	//如果是完整磁链
		$btih1=substr($btih1,20,40);										//截取btih
	if(strlen($btih1)!==40 or !ctype_xdigit($btih1))//防注入
		die("Error: Link Not Valid.");
	$btih1= strtolower($btih1);
	//btih2
	$btih2=(string)$_REQUEST['btih2'];//字符串
	if(strlen($btih2)>=60 and strpos($btih2,"magnet:?xt=urn:btih:")===0)
		$btih2=substr($btih2,20,40);
	if(strlen($btih2)!==40 or !ctype_xdigit($btih2))//防注入
		die("Error: Link Not Valid.");
	$btih2= strtolower($btih2);
	//禁止自引用
	if($btih1==$btih2)die("You Should NOT Link It with Itself");
	//time
	$btih1_time1=$btih1 . "," . strval( (int)$_REQUEST['time']);//字符串,注意并不一定是正的,赋给btih1
	$btih2_time2=$btih2 . "," . strval(-(int)$_REQUEST['time']);//相反数,注意并不一定是负的,赋给btih2

//查询btih是否还不存在
	//查询视频是否已经存在,如BTIH1不存在,退出
	$sql = "SELECT * FROM `video` WHERE `btih` = x'" . $btih1 . "';";
	$check1 = $mysql->getLine($sql);
	if($mysql->errno()!= 0)
		die("Error:" . $mysql->errmsg());	//出错
	if($mysql->affectedRows()!=1)			
		die("Error: Not Exists " . $btih1); //为空(或太多)
	//查询视频是否已经存在,如BTIH2不存在,退出
	$sql = "SELECT * FROM `video` WHERE `btih` = x'" . $btih2 . "';";
	$check2 = $mysql->getLine($sql);
	if($mysql->errno()!= 0)
		die("Error:" . $mysql->errmsg());	//出错
	if($mysql->affectedRows()!=1)			
		die("Error: Not Exists " . $btih2); //为空(或太多)
//KV读取
	if(!$link_1   = $kv->get($btih1 . ",l" )) die("Error:" . $kv->errno());//array,赋值运算表达式的值也就是所赋的值
	if(!$l_1_index= $kv->get($btih1 . ",li")) die("Error:" . $kv->errno());//json, 赋值运算表达式的值也就是所赋的值
	if(!$link_2   = $kv->get($btih2 . ",l" )) die("Error:" . $kv->errno());//array,赋值运算表达式的值也就是所赋的值
	if(!$l_2_index= $kv->get($btih2 . ",li")) die("Error:" . $kv->errno());//json, 赋值运算表达式的值也就是所赋的值

//编辑键值
	$l_1_index = json_decode($l_1_index);//json->array
	$l_2_index = json_decode($l_2_index);//json->array
	if(!isset($link_1[$btih2_time2])) //当然,对应的另一个link也不存在
		$link_1[$btih2_time2]=array();//强制储存为一个数组,防止作为一个值储存
	if(!isset($link_2[$btih1_time1])) //但是我还是要独立处理
		$link_2[$btih1_time1]=array();//强制储存为一个数组,防止作为一个值储存
	
	$this_link1=$link_1[$btih2_time2];
	$this_link1[]=$userC['uid'];
	$this_link2=$link_2[$btih1_time1];
	$this_link2[]=$userC['uid'];
	
	if(in_array($uid,$this_link1)) die("Error: You Have Already Submitted the Cross Link!");
	if(in_array($uid,$this_link2)) die("Error: You Have Already Submitted the Cross Link!");

	$link_1[$btih2_time2]=$this_link1;
	$link_2[$btih1_time1]=$this_link2;
	if(count($this_link1)!=count($this_link2)) die("Fatal Error.");//但愿不会出现,也许这句话反而会制造麻烦
	$l_1_index[$btih2_time2]=count($this_link1);//这个自然是一个值,所以无所谓
	$l_2_index[$btih1_time1]=count($this_link2);//这个自然是一个值,所以无所谓
	$l_1_index = json_encode($l_1_index);//array->json
	$l_2_index = json_encode($l_2_index);//array->json

//KV赋值
	if(!$kv->set($btih1 . ",l", $link_1)) die("Error:" . $kv->errno());
	if(!$kv->set($btih1 . ",li",$l_1_index)) die("Error:" . $kv->errno());
	if(!$kv->set($btih2 . ",l", $link_2)) die("Error:" . $kv->errno());
	if(!$kv->set($btih2 . ",li",$l_2_index)) die("Error:" . $kv->errno());
	
	
	


//提高积分并暂时硬直
	$userC['score'] = (int)$userC['score']+$const_ScoreNewLink;
	$userC['time']  = time() +$const_DelayNewLink;
	$sql = "UPDATE `user` SET `score` = " . (int)$userC['score']; 
	$sql.= ", `time` = " . $userC['time'];
	$sql.= " WHERE `uid` = " . $_COOKIE['uid'] . ";";
	$mysql->runSql( $sql );
	if($mysql->errno() != 0) 
		die("Error:" . $mysql->errmsg());	//出错

//返回成功页面
	echo "Video Created Successfully!";

// 关闭数据库
	$mysql->closeDb();

//关闭kvdb无语句
	exit;
?>
