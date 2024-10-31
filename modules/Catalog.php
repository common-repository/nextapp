<?php
class NextApp_Catalog extends NextApp_Controller
{
	public function indexAction()
	{
		$this->listAction();
	}
	
	public function listAction()
	{
		$node = $this->getXmlRoot()->catalogs;
		
		$categories = get_categories(array('hide_empty' => false));
		foreach ($categories as $key => $category) {
			if (empty($category->term_id)) {continue ;}
			$node->catalog[$key]['id'] = $category->term_id;
			$node->catalog[$key]['name'] = $category->name; 
			$node->catalog[$key]['description'] = $category->description; 
			$node->catalog[$key]['postCount'] = $category->category_count;
		}
		$this->renderXml();
	}
}