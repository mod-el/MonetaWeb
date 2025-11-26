<?php namespace Model\MonetaWeb;

use Model\Cache\Cache;
use Model\Core\Module;
use Model\Payments\PaymentInterface;
use Model\Payments\PaymentsOrderInterface;

class MonetaWeb extends Module implements PaymentInterface
{
	protected string $url;
	protected array $options = [
		'test' => false,
		'id' => null,
		'password' => null,
	];

	public function init(array $options)
	{
		$this->options = $this->retrieveConfig();

		if ($this->options['test'])
			$this->url = 'https://test.monetaonline.it/monetaweb/payment/2/xml';
		else
			$this->url = 'https://www.monetaonline.it/monetaweb/payment/2/xml';
	}

	public function beginPayment(PaymentsOrderInterface $order, string $type, array $options = [])
	{
		$baseUrl = BASE_HOST . PATH;

		$data = array_merge([
			'operationType' => 'initialize',
			'amount' => $order->getPrice(),
			'language' => 'ITA',
			'responseToMerchantUrl' => $baseUrl . 'payments/notify/MonetaWeb/response',
			'recoveryUrl' => $baseUrl . 'payments/notify/MonetaWeb/error',
			'merchantOrderId' => $order['id'],
			'description' => $order->getOrderDescription(),
		], $options);

		$xml = $this->sendRequest($data);

		if (!isset($xml->paymentid, $xml->hostedpageurl, $xml->securitytoken))
			throw new \Exception('Risposta initialize non valida');

		$cache = Cache::getCacheAdapter();
		$cacheItem = $cache->getItem('monetaweb-securitytoken-' . $order['id']);
		$cacheItem->set((string)$xml->securitytoken);
		$cacheItem->expiresAfter(3600);
		$cache->save($cacheItem);

		$this->model->redirect(((string)$xml->hostedpageurl) . '?paymentid=' . ((string)$xml->paymentid));
	}

	public function handleRequest(): array
	{
		switch ($this->model->getRequest(3)) {
			case 'response':
				try {
					if (!is_dir(__DIR__ . DIRECTORY_SEPARATOR . 'data'))
						mkdir(__DIR__ . DIRECTORY_SEPARATOR . 'data');

					$handle = fopen(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'monetaweb-last-log.txt', 'w+');
					fwrite($handle, var_export($_POST, true) . "\n\n");

					set_error_handler(function (int $errno, string $errstr, ?string $errfile = null, ?int $errline = null) use ($handle) {
						fwrite($handle, $errstr . " on " . $errfile . ":" . $errline . "\n\n");
					});

					if (!in_array($_POST['result'], ['CAPTURED', 'APPROVED']))
						throw new \Exception("BAD STATUS (" . $_POST['result'] . ")");

					$cache = Cache::getCacheAdapter();
					$cacheItem = $cache->getItem('monetaweb-securitytoken-' . $_POST['merchantorderid']);
					if (!$cacheItem->isHit())
						throw new \Exception("SECURITY TOKEN CACHE NOT FOUND");

					$securityToken = $cacheItem->get();
					if ($securityToken !== $_POST['securitytoken'])
						throw new \Exception("INVALID SECURITY TOKEN (" . $_POST['securitytoken'] . ")");

					fwrite($handle, "OK\n\n");
					fclose($handle);

					return [
						'id' => $_POST['merchantorderid'],
						'price' => $this->model->one('Prenotazione', $_POST['merchantorderid'])->getPrice(),
						'response' => [
							'type' => 'text',
							'text' => BASE_HOST . PATH . 'payments/notify/MonetaWeb/success',
						],
					];
				} catch (\Exception $e) {
					fwrite($handle, getErr($e) . "\n\n");
					fclose($handle);
				}
				die();

			case 'success':
				return [
					'dummy' => true,
				];

			default:
				throw new \Exception('Errore durante la ricezione della conferma del pagamento, contattare l\'amministratore per verificare.');
		}
	}

	private function sendRequest(array $data): \SimpleXMLElement
	{
		$data = array_merge([
			'id' => $this->options['id'],
			'password' => $this->options['password'],
		], $data);
		$get = http_build_query($data);

		$c = curl_init($this->url);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_POST, 1);
		curl_setopt($c, CURLOPT_POSTFIELDS, $get);
		curl_setopt($c, CURLOPT_HTTPHEADER, [
			'Content-Type: application/x-www-form-urlencoded',
		]);
		$response = curl_exec($c);
		if (curl_errno($c))
			throw new \Exception('Errore CURL: ' . curl_error($c));

		$xml = new \SimpleXMLElement($response);
		if (isset($xml->errorcode) or isset($xml->errormessage))
			throw new \Exception('Errore initialize: ' . ($xml->errorcode ?? '') . ' ' . ($xml->errormessage ?? ''));

		return $xml;
	}
}
