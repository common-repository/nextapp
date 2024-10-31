<?php
class NextApp_Index extends NextApp_Controller
{
	public function indexAction()
	{
		$root = $this->getXmlRoot();
		$root->version = NEXTAPP_VERSION;
		$root->name = (object)get_option('blogname', 'My Wordpress');
		
		$urls = array(
			'catalog-list'		=> 'nextapp=catalog',
			
			'post-list'			=> 'nextapp=post',
			'post-detail'		=> 'nextapp=post&action=detail',
			'post-pub'			=> 'nextapp=post&action=pub',
			'post-delete'		=> 'nextapp=post&action=delete',
			
			'comment-list'		=> 'nextapp=comment',
			'comment-pub'		=> 'nextapp=comment&action=pub',
			'comment-delete'	=> 'nextapp=comment&action=delete',
		
			'login-validate'	=> 'nextapp=auth&action=login'
		);
		
		$uri = site_url('/') . '?';
		foreach ($urls as $tag => $url) {
			$root->urls[0]->$tag = ($uri . $url);
		}
		
		$this->renderXml();
	}
}