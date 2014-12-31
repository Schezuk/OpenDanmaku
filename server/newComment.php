<?php
require('libMysqli.php');
header("Access-Control-Allow-Origin: *");//无限制
//硬直与禁言设定
$const_ScoreNewComment = 1;//加1分
$const_DelayNewComment = 3;//3秒硬直

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

//设置插入时间
$the_time_now=time();
//读取参数comment,并字符串化
$new_comment=trim((string)$_REQUEST['comment']);
//读取参数btih,并字符串化,小写化
$btih=trim(strtolower(strval($_REQUEST['btih'])));//读取参数btih
//如果是完整磁链,截取btih,"magnet:?xt=urn:btih:"长度为20,btih长度为40
if(strlen($btih)>=60 and strpos($btih,"magnet:?xt=urn:btih:")===0) $btih=substr($btih,20,40);
//检验btih有效性,即使btih仅由0-9组成也没关系,因为代码中不存在hex与unhex
if(strlen($btih)!==40 or !ctype_xdigit($btih)) die(json_err('btih_incorrect',-1,'Error: Link Not Correct'));

//查询视频是否已经存在,如btih不存在,退出
$result=NULL;
$count=safe_query("SELECT `reply`, `c_index` LENGTH(`comment`) FROM `video` WHERE `btih` = x?;", &$result, array('s',$btih));
//???????作为string处理是否可行?待验证
if($count!=1) die(json_err('btih_unavailable',-1,'Error: Video Not Yet Exists, Do You Want to Create It?'));//返回空

//编辑弹幕{"c":"sec.000,color=FFFFFF,type(1),size(25),uid,timestamp","m":"text","cid":1},
$new_comment = json_decode($new_comment);		//json->array
	$array_comment = explode(",",$new_comment['c']);
		$array_comment[4]=strval($uid)			//strval是因为要合并字符串
		$array_comment[5]=strval($the_time_now);	//strval是因为要合并字符串
	$new_comment['c']=implode(",",$array_comment);
	$new_comment['cid']=intval($result['reply']);	//reply为弹幕总数,即最大下标+1
$new_comment = json_encode($new_comment);		//array->json
$new_comment.= ',';					//结尾添加逗号

//编辑索引[uid,time,size]
$c_index = json_decode($c_index);	//json->array
//检验错误
$c_count = count($c_index);
if($result['reply']!=$c_count)
	die(json_err('reply_countnotmatch',-1,'Error: Fatal Error! Counting Does not Match. Please Report to Admin!'));
if($result['LEN(`comment`)']!=$c_index[$c_count-1]['size']){
	die(json_err('reply_lengthnotmatch',-1,'Error: Fatal Error! Length Does not Match. Please Report to Admin!'));
//编辑索引[uid,time,size]
$c_index[]=array($uid,$the_time_now,$result['LENGTH(`comment`)']+strlen($new_comment));
$c_index = json_encode($c_index);	//array->json	
++$c_count;

//我没办法在这里检查update成功，但失败lib_Mysqli必然报错退出
//修改表`video`[vid,uid,btih,time,view,reply,comment,c_index,linkage,l_index,dislike,d_index]
$blackhole=NULL;
$count=safe_query("UPDATE `video` SET `reply` = ?, `comment` = CONCAT(`comment`, ?), `c_index` = ? WHERE `btih` = x?;",
		$blackhole, array('isss', $c_count, $new_comment, $c_index, $bith));
//提高积分并暂时硬直[uid,key,time,point,status]
$blackhole=NULL;
$count=safe_query("UPDATE `user` SET `score` = `score` + ?, `time` = `time` + ? WHERE `uid` = ?;", $blackhole, 
		array('iii', $const_ScoreNewComment, $const_DelayNewComment, $uid));
//返回成功页面
	exit("Video Created Successfully!");
?>
