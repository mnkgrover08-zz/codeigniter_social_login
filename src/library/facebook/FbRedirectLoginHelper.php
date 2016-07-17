<?php
class FbRedirectLoginHelper extends \Facebook\FacebookRedirectLoginHelper {
	var $sessionHandler = null;

	protected function storeState($state) {
		$this->sessionHandler->set_userdata('state', $state);
	}
	protected function loadState() {
		return $this->state = $this->sessionHandler->userdata('state');
	}
}