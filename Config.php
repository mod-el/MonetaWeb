<?php namespace Model\MonetaWeb;

use Model\Core\Module_Config;

class Config extends Module_Config
{
	protected function assetsList(): void
	{
		$this->addAsset('config', 'config.php', function () {
			return '<?php
$config = [
	\'id\' => null,
	\'password\' => null,
	\'test\' => true,
	\'finalUrl\' => null,
];
';
		});
	}
}
