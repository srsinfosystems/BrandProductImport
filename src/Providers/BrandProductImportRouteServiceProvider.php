<?php
namespace BrandProductImport\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;

/**
 * Class HelloWorldRouteServiceProvider
 * @package HelloWorld\Providers
 */
class BrandProductImportRouteServiceProvider extends RouteServiceProvider
{
	/**
	 * @param Router $router
	 */
	public function map(Router $router)
	{
		$router->get('cgihome', 'BrandProductImport\Controllers\ContentController@cgihome');
		$router->get('importProduct', 'BrandProductImport\Controllers\ContentController@importProduct');
	}

}
