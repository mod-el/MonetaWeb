<?php namespace Model\MonetaWeb;

use Model\Core\Core;
use Model\Core\Module;

class MonetaWeb extends Module
{
	protected $url;
	protected $options = [
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

	/**
	 * Controller for payment verification
	 *
	 * @param array $request
	 * @param string $rule
	 * @return array
	 */
	public function getController(array $request, string $rule): ?array
	{
		return [
			'controller' => 'MonetaWeb',
		];
	}

	private function getBasicGetData(): array
	{
		return [
			'id' => $this->options['id'],
			'password' => $this->options['password'],
		];
	}

	private function sendRequest(array $data): \SimpleXMLElement
	{
		$data = array_merge($this->getBasicGetData(), $data);
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
			$this->model->error('Errore CURL: ' . curl_error($c));
		curl_close($c);

		$xml = new \SimpleXMLElement($response);
		if (isset($xml->errorcode) or isset($xml->errormessage))
			$this->model->error('Errore initialize: ' . ($xml->errorcode ?? '') . ' ' . ($xml->errormessage ?? ''));

		return $xml;
	}

	public function initialize(float $amount, int $order, array $options = []): array
	{
		$baseUrl = BASE_HOST . PATH;

		$data = array_merge([
			'operationType' => 'initialize',
			'amount' => $amount,
			'language' => 'ITA',
			'responseToMerchantUrl' => $baseUrl . 'monetaweb/response',
			'recoveryUrl' => $baseUrl . 'monetaweb/error',
			'merchantOrderId' => $order,
		], $options);

		$xml = $this->sendRequest($data);

		if (!isset($xml->paymentid, $xml->hostedpageurl, $xml->securitytoken))
			$this->model->error('Risposta initialize non valida');

		return [
			'paymentId' => (string)$xml->paymentid,
			'hostedPageUrl' => (string)$xml->hostedpageurl,
			'securityToken' => (string)$xml->securitytoken,
		];
	}

	public function checkResponse(array $response): bool
	{
		if (!is_dir(__DIR__ . DIRECTORY_SEPARATOR . 'data'))
			mkdir(__DIR__ . DIRECTORY_SEPARATOR . 'data');
		$handle = fopen(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'last-log.txt', 'w+');
		fwrite($handle, var_export($response, true) . "\n\n");

		if (!in_array($response['result'], ['CAPTURED', 'APPROVED'])) {
			fwrite($handle, "BAD STATUS (" . $response['result'] . ")\n\n");
			return false;
		}

		$securityToken = call_user_func_array($this->options['verifySecurityToken'], [
			$response['merchantorderid'],
			$response['securitytoken'],
		]);
		if (!$securityToken) {
			fwrite($handle, "INVALID SECURITY TOKEN (" . $response['securitytoken'] . ")\n\n");
			return false;
		}

		$this->model->on('error', function ($err) use ($handle) {
			fwrite($handle, var_export($err, true) . "\n\n");
		});

		try {
			call_user_func_array($this->options['markOrderAsPaid'], [
				$response['merchantorderid'],
			]);
		} catch (\Exception $e) {
			fwrite($handle, "EXCEPTION: (" . getErr($e) . ")\n\n");
			die();
		}

		fwrite($handle, "OK\n\n");
		fclose($handle);

		return true;
	}

	public function getFinalUrl(): string
	{
		return $this->options['finalUrl'];
	}
}
