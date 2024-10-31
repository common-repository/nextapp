<?php
class NextApp_Auth extends NextApp_Controller
{
	public function indexAction()
	{
		$this->loginAction();
	}
	
	public function loginAction()
	{
		$username = $this->getPost('username');
		$password = $this->getPost('pwd');
		
		if (empty($username) || empty($password)) {
			$this->renderError(self::ERROR_INVALID_PARAMETERS, 'Please enter your user name/password.');;
		}else {
			$user = wp_authenticate($username, $password);
			if (is_wp_error($user)) {
				$this->renderError(0, 'Bad login/pass combination.');
			}else {
				$user = get_userdatabylogin($username);
				if (empty($user) || !is_object($user) || empty($user->ID)) {
					$this->renderError();
				}
				wp_set_auth_cookie($user->ID);
				$this->renderSuccess();
			}
		}
	}
	
	public function logoutAction()
	{
		if (function_exists('wp_logout')) {
			wp_logout();
		}else {
			wp_clearcookie();
		}
		$this->renderSuccess();
	}
}