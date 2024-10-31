<?php
class NextApp_Comment extends NextApp_Controller
{
	public function indexAction()
	{
		$this->listAction();
	}
	
	public function listAction()
	{
		$postId = (int)$this->getParam('post') < 0 ? 0 : (int)$this->getParam('post', 0);
		$fromComment = (int)$this->getParam('fromComment') < 0 ? 0 : (int)$this->getParam('fromComment', 0);
		$fetchCount = (int)$this->getParam('fetchCount', 10);
		$orderBy = 'comment_ID';
		$sortMethod = $this->getParam('sortMethod');
		if ($sortMethod == 'ascend') {
			$order = 'ASC';
		}elseif ($sortMethod == 'descend') {
			$order = 'DESC';
		}else {
			$order = empty($postId) ? 'DESC' : 'ASC';
		}
		$orderWhere = $order == 'ASC' ? '>' : '<';
		$userId = get_current_user_id();
		
		$commentCount = 0;
		$commentResult = array();
		
		global $wp_version;
		switch (substr($wp_version, 0, 3)) {
			case '2.7':
			case '2.8':
			case '2.9':
			case '3.0':
				global $wpdb;
				$where = '(comment_approved = \'1\'';
				$where .= $userId > 0 ? ' OR (comment_approved = \'0\' AND user_id = \'' . $userId . '\'))' : ')';
				$where .= empty($postId) ? '' : ' AND comment_post_ID = ' . $postId;
				
				$result = $wpdb->get_results("SELECT COUNT(*) AS fetchCount FROM {$wpdb->comments} WHERE $where");
				if (is_array($result)) {
					$result = current($result);
					if (is_object($result)) {
						$commentCount = $result->fetchCount;
					}
				}
				if ($commentCount > 0) {
					$where .= empty($fromComment) ? '' : ' AND comment_ID ' . $orderWhere . ' ' . $fromComment;
					$commentResult = $wpdb->get_results("SELECT * FROM {$wpdb->comments} WHERE $where ORDER BY $orderBy $order LIMIT $fetchCount");
				}
				break;
			case '3.1':
			default:
				$GLOBALS['nextapp_userId'] = $userId;
				$filterWhere = create_function('$clauses', 
					'global $wpdb;	global $nextapp_userId;
					if($nextapp_userId>0) {
						$clauses[\'where\'] = "{$clauses[\'where\']} AND ($wpdb->comments.comment_approved = \'1\' OR ($wpdb->comments.comment_approved = \'0\' AND $wpdb->comments.user_id = \'$nextapp_userId\')) ";
					}else {
						$clauses[\'where\'] = "{$clauses[\'where\']} AND $wpdb->comments.comment_approved = \'1\' ";
					}
					return $clauses;
				');
				add_filter('comments_clauses', $filterWhere);
				
				$commentCount = get_comments(array('post_id' => $postId, 'count' => true));
				if ((int)$commentCount > 0) {
					$GLOBALS['nextapp_args'] = array(
						'orderWhere' 	=> $orderWhere,
						'fromComment'	=> $fromComment
					);
					$filterWhere = create_function('$clauses', 
						'global $wpdb; global $nextapp_args;
						if(!empty($nextapp_args[\'fromComment\'])) {
							$clauses[\'where\']="{$clauses[\'where\']} AND $wpdb->comments.comment_ID {$nextapp_args[\'orderWhere\']} {$nextapp_args[\'fromComment\']} ";
						}
						return $clauses;');
					add_filter('comments_clauses', $filterWhere);
					
					$commentResult = get_comments(array('post_id' => $postId, 'orderby' => $orderBy, 'order' => $order, 'number' => $fetchCount));
				}
				break;
		}
		
		$this->getXmlRoot()->commentCount = $commentCount;
		if (is_array($commentResult) && count($commentResult) > 0) {
			$node = $this->getXmlRoot()->comments;
			foreach ($commentResult as $key => $comment) {
				if (empty($comment->comment_ID)) {continue ;}
				$node->comment[$key]->id = $comment->comment_ID;
				$node->comment[$key]->post = $comment->comment_post_ID; 
				$node->comment[$key]->name = (object)$comment->comment_author;
				$node->comment[$key]->title = (object)get_post_field('post_title', $comment->comment_post_ID);
				$node->comment[$key]->email = (object)$comment->comment_author_email;
				$node->comment[$key]->url = (object)$comment->comment_author_url;
				$node->comment[$key]->body = (object)$comment->comment_content;
				$node->comment[$key]->pubDate = $comment->comment_date;
			}
		}
		$this->renderXml();
	}
	
	public function pubAction()
	{
		$comments = array(
			'comment_post_ID' 		=> (int)$this->getPost('post', 0),
			'comment_content'		=> (string)$this->getPost('body'),
			'comment_author'		=> (string)$this->getPost('name'),
			'comment_author_email'	=> (string)$this->getPost('email'),
			'comment_author_url'	=> (string)$this->getPost('url')
		);
		
		if (empty($comments['comment_post_ID'])) {
			$this->renderError(self::ERROR_INVALID_PARAMETERS, 'Please specify the post ID.');
		}
		
		if (empty($comments['comment_content'])) {
			$this->renderError(self::ERROR_INVALID_PARAMETERS, 'Please input post the content.');
		}
		
		$post = get_post($comments['comment_post_ID']);
		if (empty($post)) {
			$this->renderError(0, 'This post is not exists.');
		}
		
		if (!comments_open($comments['comment_post_ID'])) {
			$this->renderError(0, __('Sorry, comments are closed for this item.'));
		}
		
		$userId = get_current_user_id();
		if (empty($userId) && (get_option('comment_registration') || 'private' === get_post_status($post))) {
			$this->renderError(0, __('Sorry, you must be logged in to post a comment.'));
		}
		
		if ($userId > 0) {
			$comments['user_id'] = $userId;
			$currentUser = wp_get_current_user();
			empty($comments['comment_author']) && $comments['comment_author'] = $currentUser->display_name ? $currentUser->display_name : $currentUser->user_login;
			empty($comments['comment_author_email']) && $comments['comment_author_email'] = $currentUser->user_email;
			empty($comments['comment_author_url']) && $comments['comment_author_url'] = $currentUser->user_url;
		}
		
		# Repeated testing whether published.
		global $wpdb;
		$dupe = "SELECT comment_ID FROM $wpdb->comments WHERE comment_post_ID = '{$comments['comment_post_ID']}' AND comment_approved != 'trash' AND(comment_author = '{$comments['comment_author']}' ";
		if ($comments['comment_author_email']) {
			$dupe .= "OR comment_author_email = '{$comments['comment_author_email']}' ";
		}
		$dupe .= ") AND comment_content = '{$comments['comment_content']}' LIMIT 1";
		if ($wpdb->get_var($dupe)) {
			$this->renderError(0, __('Duplicate comment detected; it looks as though you&#8217;ve already said that!'));
		}
		
		$result = wp_new_comment($comments);
		if (empty($result)) {
			$this->renderError();
		}else {
			$message = '';
			if (wp_get_comment_status($result) != 'approved') {
				$message = __('Your comment is awaiting moderation.');
			}
			$this->renderSuccess(null, $message);
		}
	}
	
	public function deleteAction()
	{
		$commentId = (int)$this->getParam('comment', 0);
		if (empty($commentId)) {
			$this->renderError(self::ERROR_INVALID_PARAMETERS);
		}
		
		if (!get_current_user_id()) {
			$this->renderError(self::ERROR_NO_LOGINED);
		}
		
		$comment = get_comment($commentId);
		if (empty($comment)) {
			$this->renderError(0, 'This comment is not exists.');
		}
		
		global $wp_version;
		switch (substr($wp_version, 0, 3)) {
			case '2.7':
			case '2.8':
			case '2.9':
			case '3.0':
				$can = current_user_can('edit_post', $comment->comment_ID);
				break;
			default:
				$can = current_user_can('edit_comment', $comment->comment_ID);
				break;
		}
		
		if (empty($can)) {
			$this->renderError(self::ERROR_PERMISSION_DENIED, 'Sorry, you didn\'t remove comments permissions.');
		}
		
		$result = wp_delete_comment($commentId);
		if (empty($result)) {
			$this->renderError();
		}else {
			$this->renderSuccess();
		}
	}
}