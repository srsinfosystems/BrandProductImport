<?php
namespace BrandProductImport\Crons;
use Plenty\Modules\Cron\Contracts\CronHandler as Cron;

use BrandProductImport\Controllers\ContentController;
use Plenty\Plugin\Log\Loggable;

class BrandProductImportCron extends Cron {
	use Loggable;
	public function handle(ContentController $contentController) {
		$contentController->cliImport();

	}
}
