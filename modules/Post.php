<?php
class NextApp_Post extends NextApp_Controller
{
	public function indexAction()
	{
		$this->listAction();
	}
	
	public function detailAction()
	{
		global $post, $wp_query;
		$wp_query->is_single = true;
		
		$postId = (int)$this->getParam('post', 0);
		$post = get_post($postId);
		if (empty($post) || $post->post_type != 'post') {
			return $this->renderError(0, 'This post is not exists.');
		}
		
		if (get_current_user_id() != $post->post_author) {
			if ($post->post_status == 'private') {
				return $this->renderError(0, 'This post is private.');
			}
			if ($post->post_status != 'publish') {
				return $this->renderError(0, 'This post is not publish.');
			}
			if (!empty($post->post_password)) {
				return $this->renderError(0, 'This post is password protected.');
			}
		}

		$node = $this->getXmlRoot()->post;
		$node->id = $post->ID;
		
		$category = get_the_category($post->ID);
		$node->catalog = empty($category) ? 0 : implode(',', $this->getAllByField($category, 'term_id'));
		$node->title = (object)$post->post_title;
		$node->url = (object)$post->guid;
		
		$body = str_replace(']]>', ']]&gt;', apply_filters('the_content', $post->post_content));
		
		# If have CSS file.
		$css = NEXTAPP_PATH . DIRECTORY_SEPARATOR . 'style.css';
		if (file_exists($css)) {
			$body = '<style>' . file_get_contents($css) . '</style>' . $body;
		}
		$node->body = (object)$body;
		unset($body);
		
		$tags = wp_get_post_tags($post->ID);
		$tags = empty($tags) ? '' : implode(',', $this->getAllByField($tags, 'name'));
		$node->tags = (object)$tags;
		
		$node->author = (object)get_author_name($post->post_author);
		$node->pubDate = $post->post_date;
		$node->commentCount = $post->comment_count;
		
		# previous and next
		if ($previons = get_previous_post()) {
			$node->previous = $previons->ID;
		}
		if ($next = get_next_post()) {
			$node->next = $next->ID;
		}
		$wp_query->is_single = false;
		
		if (!empty($tags)) {
			$relativeNode = $node->relativePosts;
			$relativePosts = query_posts(array('tag' => $tags, 'post__not_in' => array($post->ID), 'showposts' => (int)$this->getParam('fetchCount', 10)));
			foreach ($relativePosts as $key => $relative) {
				if (empty($relative->ID)) {continue ;}
				$relativeNode->relativePost[$key]->id = $relative->ID;
				$relativeNode->relativePost[$key]->title = (object)$relative->post_title;
				$relativeNode->relativePost[$key]->author = (object)get_author_name($relative->post_author);
				$relativeNode->relativePost[$key]->pubDate = $relative->post_date;
			}
		}
		$this->renderXml();
	}
	
	public function listAction()
	{
		$args = array(
			'cat'				=> (int)$this->getParam('catalog') < 0 ? 0 : (int)$this->getParam('catalog', 0),
			'category'			=> (int)$this->getParam('catalog') < 0 ? 0 : (int)$this->getParam('catalog', 0),
			'suppress_filters' 	=> false,
			'caller_get_posts'	=> true
		);
		
		$filterFields = create_function('$fields', 'return \'COUNT(*) AS fetchCount\';');
		add_filter('posts_fields', $filterFields);
		
		$filterGroupby = create_function('$groupby', 'return \'\';');
		add_filter('posts_groupby', $filterGroupby);
		
		$filterOrderby = create_function('$orderby', 'return \'\';');
		add_filter('posts_orderby', $filterOrderby);
		
		$this->getXmlRoot()->catalog = $args['category'];
		
		$postCount = 0;
		$wpQuery = new WP_Query;
		
		$result = $wpQuery->query($args);
		if (is_array($result)) {
			$result = current($result);
			if (is_object($result)) {
				$postCount = $result->fetchCount;
			}
		}
		
		$this->getXmlRoot()->postCount = $postCount;
		
		if ($postCount > 0) {
			remove_filter('posts_fields', $filterFields);
			remove_filter('posts_groupby', $filterGroupby);
			remove_filter('posts_orderby', $filterOrderby);
			
			$GLOBALS['nextapp_post_id'] = (int)$this->getParam('fromPost') < 0 ? 0 : (int)$this->getParam('fromPost', 0);
			$filterWhere = create_function('$where', 
				'global $wpdb;	global $nextapp_post_id;
				if(empty($nextapp_post_id)) {
					return $where;
				}else {
					$post = get_post($nextapp_post_id);
					if(!is_object($post) || empty($post->post_date)) {
						$current_time = current_time(\'mysql\');
						$w = "$wpdb->posts.post_date < \'$current_time\'";
					}else {
						$w = "$wpdb->posts.post_date < \'$post->post_date\'";
					}
					return "$where AND $w ";
				}'
			);
			add_filter('posts_where', $filterWhere);
			
			$node = $this->getXmlRoot()->posts;
			
			$posts = $wpQuery->query(array_merge($args, array(
				'numberposts'		=> (int)$this->getParam('fetchCount', 10),
				'posts_per_page'	=> (int)$this->getParam('fetchCount', 10),
				#'orderby'			=> 'ID',
			)));
			foreach ($posts as $key => $post) {
				if (empty($post->ID)) {continue ;}
				$node->post[$key]->id = $post->ID;
				$node->post[$key]->title = (object)$post->post_title;
				$node->post[$key]->url = $post->guid;
				# post first image
				preg_match('/<\s*?img.+?src\s*?=\s*?[\"\']?(.+?)(\'|\"|\s|>|\/\s*?>).*?>?/is', $post->post_content, $m);
				$node->post[$key]->img = (empty($m) || empty($m[1])) ? '' : (false === stripos($m[1], 'http') ? site_url('/') . ltrim($m[1], '/') : $m[1]);
				$node->post[$key]->outline = empty($post->post_excerpt) ? (object)wp_html_excerpt(apply_filters('the_content', $post->post_content), 50) : (object)apply_filters('get_the_excerpt', $post->post_excerpt);
				$node->post[$key]->commentCount = $post->comment_count;
				$node->post[$key]->authorId = $post->post_author;
				$node->post[$key]->author = (object)get_author_name($post->post_author);
				# get category
				$category = get_the_category($post->ID);
				$node->post[$key]->catalog = empty($category) ? 0 : implode(',', $this->getAllByField($category, 'term_id'));
				#$node->post[$key]->catalogName = (object)(empty($category) ? '' : implode(',', $this->getAllByField($category, 'name')));
				$node->post[$key]->pubDate = $post->post_date;
			}
		}
		$this->renderXml();
	}
	
	public function pubAction()
	{
		$userId = get_current_user_id();
		if (empty($userId)) {
			$this->renderError(self::ERROR_NO_LOGINED, 'Sorry, you must be logged in to publish the post.');
		}
		
		#if (!current_user_can('edit_posts')) {
		#	$this->renderError(self::ERROR_PERMISSION_DENIED);
		#}
		#if (!current_user_can('publish_posts')) {
		#	$this->renderError(self::ERROR_PERMISSION_DENIED);
		#}
		if (!user_can_create_post($userId)) {
			$this->renderError(self::ERROR_PERMISSION_DENIED, 'Sorry, you have no published post permissions.');
		}
		
		$post = array(
			'post_category' => array(),
			'post_title' 	=> $this->getPost('title'),
			'post_content'	=> $this->getPost('body'),
			'post_status'	=> 'publish'
		);
		
		if (empty($post['post_title']) || empty($post['post_content'])) {
			$this->renderError(self::ERROR_INVALID_PARAMETERS, 'Please enter a title/content.');
		}
		
		$catalog = $this->getPost('catalog');
		if (!empty($catalog)) {
    		if (false !== strpos($catalog, ' ')) {
				$catalog = str_replace(' ', ',', $catalog);
			}
			$categories = explode(',', $catalog);
			foreach ($categories as $slug) {
				$slug = trim($slug);
				if (empty($slug)) {continue ;}
				if (is_numeric($slug)) {
					$categoryObj = get_category($slug);
					if (is_object($categoryObj) && $categoryObj->term_id > 0) {
						$post['post_category'][] = $slug;
					}
				}else {
					$category = get_category_by_slug($slug);
					if (is_object($category) && $category->term_id > 0) {
						$post['post_category'][] = $category->term_id;
					}
				}
			}
    	}
    	if (empty($post['post_category'])) {
    		$post['post_category'] = array(get_option('default_category', 0));
    	}
		
    	$tag = $this->getPost('tag');
		if (!empty($tag)) {
			if (false !== strpos($tag, ' ')) {
				$tag = str_replace(' ', ',', $tag);
			}
			$post['tags_input'] = trim($tag, ',');
		}
		
		if (count($_FILES) > 0) {
			$imgHtml = '';
			
			@include_once ABSPATH . '/wp-admin/includes/file.php';
			@include_once ABSPATH . '/wp-admin/includes/image.php';
			foreach ($_FILES as $key => $file) {
				if (!is_array($file) || empty($file['size']) || empty($file['tmp_name'])) {
					continue ;
				}
				$file = wp_handle_upload($file, array('test_form' => false));
				if (isset($file['error']) || empty($file['file'])) {
					continue ;
				}
				$imgUrl = isset($file['url']) ? $file['url'] : '';
				$imgOrignUrl = $imgUrl;
				
				$width = get_option('thumbnail_size_w', 100);
				$height = get_option('thumbnail_size_h', 100);
				
				$thumbpath = image_resize($file['file'], $width, $height);
				if (is_string($thumbpath)) {
					$uploader = wp_upload_dir();
					if (is_array($uploader) && !empty($uploader['url'])) {
						$imgUrl = rtrim($uploader['url'], '/') . '/' . urldecode(basename(str_replace('%2F', '/', urlencode($thumbpath))));
					}
				}
				if ($imgUrl) {
					$html = '<img src="' . $imgUrl . '" alt="img" width="' . $width . '" height="' . $height . '"/>';
					if ($imgOrignUrl) {
						$html = '<a href="' . $imgOrignUrl . '">' . $html . '</a>';
					}
					$imgHtml .= '<p>' . $html . '</p>';
				}
			}
			if ($imgHtml) {
				$post['post_content'] .= $imgHtml;
			}
		}
    	
		$postId = wp_insert_post($post);
		if (is_numeric($postId) && $postId > 0) {
			$this->renderSuccess($postId);
		}else {
			if ($postId instanceof WP_Error) {
				$this->renderError(0, implode(', ', $postId->get_error_messages()));
			}else {
				$this->renderError();
			}
		}
	}
	
	public function deleteAction()
	{
		$postId = (int)$this->getParam('post', 0);
		if (empty($postId)) {
			$this->renderError(self::ERROR_INVALID_PARAMETERS, 'Please specify the post ID.');
		}
		
		$userId = get_current_user_id();
		if (empty($userId)) {
			$this->renderError(self::ERROR_NO_LOGINED, 'Sorry, you must be logged in to remove the post.');
		}
		
		$post = get_post($postId);
		if (empty($post)) {
			$this->renderError(0, 'This post is not exists.');
		}
		
		#if (!current_user_can('delete_post', $post->ID)) {
		#	$this->renderError(self::ERROR_PERMISSION_DENIED);
		#}
		if (!user_can_delete_post($userId, $post->ID)) {
			$this->renderError(self::ERROR_PERMISSION_DENIED, 'Sorry, you didn\'t remove the post permissions.');
		}
		
		$result = wp_delete_post($postId);
		if (empty($result)) {
			$this->renderError();
		}else {
			$this->renderSuccess();
		}
	}
}
