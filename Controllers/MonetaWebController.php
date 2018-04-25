<?php namespace Model\MonetaWeb\Controllers;

use Model\Core\Controller;

class MonetaWebController extends Controller
{
	function index()
	{
		switch ($this->model->getRequest(1)) {
			case 'response':
				if ($this->model->_MonetaWeb->checkResponse($_POST))
					echo $this->model->_MonetaWeb->getFinalUrl();
				die();
				break;
			case 'error':
				$this->viewOptions['template'] = null;
				$this->viewOptions['errors'][] = 'Errore durante la ricezione della conferma del pagamento, contattare l\'amministratore per verificare.';
				break;
		}
	}
}
