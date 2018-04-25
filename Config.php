<?php namespace Model\MonetaWeb;

use Model\Core\Module_Config;

class Config extends Module_Config
{
	protected function assetsList()
	{
		$this->addAsset('config', 'config.php', function () {
			return '<?php
$config = [
	\'id\' => null,
	\'password\' => null,
	\'test\' => true,
	\'verifySecurityToken\' => function($orderId, $securityToken){
		return false;
	},
	\'markOrderAsPaid\' => function($orderId){},
	\'finalUrl\' => null,
];
';
		});
	}

	/**
	 * Rule for payment verification
	 *
	 * @return array
	 */
	public function getRules(): array
	{
		return [
			'rules' => [
				'monetaweb' => 'monetaweb',
			],
			'controllers' => [
				'MonetaWeb',
			],
		];
	}
}
