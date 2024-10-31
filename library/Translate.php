<?php
$GLOBALS['nextapp_translate'] = array(
	'Unknown error.'									=> '未知错误.',
	'Invalid parameters.'								=> '无效的参数.',
	'Users not logged in.'								=> '请登录后继续.',
	'The user has no right to operate.'					=> '您无权进行该操作.',
	'Please enter your user name/password.'				=> '请输入用户名/密码.',
	'Bad login/pass combination.'						=> '错误的用户名和密码.',
	'Please specify the post ID.'						=> '请指定文章ID.',
	'Please input post the content.'					=> '请输入文章内容.',
	'This post is not exists.'							=> '错误:这篇文章不存在.',
	'This post is private.'								=> '抱歉,这篇文章是私人的.',
	'This post is not publish.'							=> '抱歉,这篇文章还没有发布.',
	'This post is password protected.'					=> '抱歉,这篇文章受密码保护.',
	'Sorry, comments are closed for this item.'			=> '抱歉,该篇文章的评论已关闭.',
	'Sorry, you must be logged in to post a comment.'	=> '抱歉,您必须登录以后才能发表评论.',
	'Your comment is awaiting moderation.' 				=> '您的评论正在等待审核.',
	'This comment is not exists.'						=> '错误:这篇评论不存在.',
	'Sorry, you didn\'t remove comments permissions.'	=> '抱歉,您没有删除评论的权限.',
	'Sorry, you have no published post permissions.'	=> '抱歉,您没有发表文章的权限.',
	'Please enter a title/content.'						=> '请输入一个标题或内容.',
	'Sorry, you must be logged in to publish the post.'	=> '抱歉,您必须登录以后才能发表文章.',
	'Sorry, you must be logged in to remove the post.'	=> '抱歉,您必须登录以后才能删除文章.',
	'Sorry, you didn\'t remove the post permissions.'	=> '抱歉,您没有删除文章的权限.',
);


function nextapp__($var) {
	global $nextapp_translate;
	
	if (isset($nextapp_translate[$var])) {
		return $nextapp_translate[$var];
	}
	return $var;
}