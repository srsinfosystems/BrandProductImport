<?php
namespace BrandProductImport\Providers;

use Plenty\Plugin\ServiceProvider;
use Plenty\Modules\Cron\Services\CronContainer;
use BrandProductImport\Crons\BrandProductImportCron;

/**
 * Class HelloWorldServiceProvider
 * @package HelloWorld\Providers
 */
class BrandProductImportServiceProvider extends ServiceProvider
{

	public function boot(CronContainer $container) {
		$container->add(CronContainer::DAILY, BrandProductImportCron::class);
	}
	/**
	 * Register the service provider.
	 */
	public function register()
	{
		$this->getApplication()->register(BrandProductImportRouteServiceProvider::class);
	}
}
