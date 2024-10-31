<?php
class NextApp_Page extends NextApp_Controller
{
	public function indexAction()
	{
		$this->listAction();
	}
	
	public function detailAction()
	{
		$pageId = (int)$this->getParam('page', 0);
		$page = get_page($pageId);
		if (empty($page) || $page->post_type != 'page') {
			return $this->renderError(0, 'This post is not exists.');
		}
		
		if (get_current_user_id() != $page->post_author) {
			if ($page->post_status == 'private') {
				return $this->renderError(0, 'This post is private.');
			}
			if ($page->post_status != 'publish') {
				return $this->renderError(0, 'This post is not publish.');
			}
			if (!empty($page->post_password)) {
				return $this->renderError(0, 'This post is password protected.');
			}
		}

		$node = $this->getXmlRoot()->page;
		$node->id = $page->ID;
		
		$node->parent = (int)$page->post_parent;
		$node->title = (object)$page->post_title;
		$node->url = (object)$page->guid;
		
		$body = str_replace(']]>', ']]&gt;', apply_filters('the_content', $page->post_content));
		
		# If have CSS file.
		#$css = NEXTAPP_PATH . DIRECTORY_SEPARATOR . 'style.css';
		#if (file_exists($css)) {
		#	$body = '<style>' . file_get_contents($css) . '</style>' . $body;
		#}
		$node->body = (object)$body;
		unset($body);
		
		$node->author = (object)get_author_name($page->post_author);
		$node->pubDate = $page->post_date;
		$node->commentCount = $page->comment_count;
		
		$this->renderXml();
	}
	
	public function listAction()
	{
		$args = array(
			'post_type'			=> 'page',
			'suppress_filters' 	=> false,
			'caller_get_posts'	=> true
		);
		
		$filterFields = create_function('$fields', 'return \'COUNT(*) AS fetchCount\';');
		add_filter('posts_fields', $filterFields);
		
		$filterGroupby = create_function('$groupby', 'return \'\';');
		add_filter('posts_groupby', $filterGroupby);
		
		$filterOrderby = create_function('$orderby', 'return \'\';');
		add_filter('posts_orderby', $filterOrderby);
		
		$pageCount = 0;
		$wpQuery = new WP_Query;
		
		$result = $wpQuery->query($args);
		if (is_array($result)) {
			$result = current($result);
			if (is_object($result)) {
				$pageCount = $result->fetchCount;
			}
		}
		
		$this->getXmlRoot()->pageCount = $pageCount;
		
		if ($pageCount > 0) {
			remove_filter('posts_fields', $filterFields);
			remove_filter('posts_groupby', $filterGroupby);
			remove_filter('posts_orderby', $filterOrderby);
			
			$GLOBALS['nextapp_page_id'] = (int)$this->getParam('fromPage') < 0 ? 0 : (int)$this->getParam('fromPage', 0);
			$filterWhere = create_function('$where', 
				'global $wpdb;	global $nextapp_page_id;
				if(empty($nextapp_page_id)) {
					return $where;
				}else {
					$page = get_page($nextapp_page_id);
					if(!is_object($page) || empty($page->post_date)) {
						$current_time = current_time(\'mysql\');
						$w = "$wpdb->posts.post_date < \'$current_time\'";
					}else {
						$w = "$wpdb->posts.post_date < \'$page->post_date\'";
					}
					return "$where AND $w ";
				}'
			);
			add_filter('posts_where', $filterWhere);
			
			$node = $this->getXmlRoot()->pages;
			
			$pages = $wpQuery->query(array_merge($args, array(
				'numberposts'		=> (int)$this->getParam('fetchCount', 10),
				'posts_per_page'	=> (int)$this->getParam('fetchCount', 10),
				#'orderby'			=> 'ID',
			)));
			foreach ($pages as $key => $page) {
				if (empty($page->ID)) {continue ;}
				$node->page[$key]->id = $page->ID;
				$node->page[$key]->title = (object)$page->post_title;
				$node->page[$key]->url = $page->guid;
				# page first image
				preg_match('/<\s*?img.+?src\s*?=\s*?[\"\']?(.+?)(\'|\"|\s|>|\/\s*?>).*?>?/is', $page->post_content, $m);
				$node->page[$key]->img = (empty($m) || empty($m[1])) ? '' : (false === stripos($m[1], 'http') ? site_url('/') . ltrim($m[1], '/') : $m[1]);
				$node->page[$key]->outline = empty($page->post_excerpt) ? (object)wp_html_excerpt(apply_filters('the_content', $page->post_content), 50) : (object)apply_filters('get_the_excerpt', $page->post_excerpt);
				$node->page[$key]->commentCount = $page->comment_count;
				$node->page[$key]->authorId = $page->post_author;
				$node->page[$key]->author = (object)get_author_name($page->post_author);
				$node->page[$key]->parent = (int)$page->post_parent;
				$node->page[$key]->pubDate = $page->post_date;
			}
		}
		$this->renderXml();
	}
}
