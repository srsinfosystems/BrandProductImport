<?php
namespace BrandProductImport\Controllers;

use Plenty\Plugin\Controller;
use Plenty\Plugin\Templates\Twig;
use Plenty\Plugin\Log\Loggable;

/**
 * Class ContentController
 * @package BrandProductImport\Controllers
 */
class ContentController extends Controller
{
	use Loggable;

	public $access_token;
	public $plentyhost;
	public $drophost;
	public $variations;
	public $printme;
	/**
	 * @param Twig $twig
	 * @return string
	 */
	public function cgihome(Twig $twig):string
	{
		$message = $_GET['message'];
		$brands['brands'] = $this->getBrands();

		if (!empty($message)) {
			return $twig->render('BrandProductImport::content.mainView',array('data' => "success"));
		}else{
			return $twig->render('BrandProductImport::content.mainView', $brands);
		}


	}
	public function sayHello(Twig $twig):string
	{
		return $twig->render('BrandProductImport::content.mainView');
	}
	public function cliImport()
	{

		$brands = $this->getBrands();
		if(empty($brands)) exit;
		$host = "joiurjeuiklb.plentymarkets-cloud02.com";
		$login = $this->login($host);
		$login = json_decode($login, true);
		$this->access_token = $login['access_token'];
		$this->plentyhost = "https://".$host;
		$this->drophost = "https://www.brandsdistribution.com";

		foreach($brands as $brand) {
			if(empty($brand)) continue;
			$this->variations = array();
			$this->printme = "N";
			$flag = $this->getAllItems($brand);
			sleep(30);
		}
	}
	public function importProduct(Twig $twig):string
	{
		//echo $_REQUEST;
		$brand = $_GET['brand'];

		$host = $_SERVER['HTTP_HOST'];
		$login = $this->login($host);
		$login = json_decode($login, true);
		$this->access_token = $login['access_token'];
		$this->plentyhost = "https://".$host;
		$this->drophost = "https://www.brandsdistribution.com";
		$this->variations = array();
		$this->printme = "Y";
		$flag = $this->getAllItems($brand);
		exit;

		/*if ($flag == 1)
			$data = "Items created successfully.";
		else
			$data = "Somthing went wrong.";*/
		//return $twig->render('HelloWorld::content.importProduct');
	}

	public function getAllItems($brand){

		$curl = curl_init();
		$checktime = strtotime("-90 mins");
		$checktime = date("c", $checktime);
		$url = "";
		if($this->printme == "Y") {
			$url = $this->drophost."/restful/export/api/products.xml?Accept=application%2Fxml&acceptedlocales=en_US&tag_1=".urlencode($brand);
		}
		else {
			$url = $this->drophost."/restful/export/api/products.xml?Accept=application%2Fxml&acceptedlocales=en_US&tag_1=".urlencode($brand)."&since=".urlencode($checktime);
		}
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $url,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 900000000,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "GET",
		  CURLOPT_HTTPHEADER => array(
		    "authorization: Basic MTg0Y2U4Y2YtMmM5ZC00ZGU4LWI0YjEtMmZkNjcxM2RmOGNkOlN1cmZlcjc2",
		    "cache-control: no-cache",
		    "content-type: application/xml"
		  )
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  return "cURL Error #:" . $err;
		} else {

			$xml = simplexml_load_string($response);
	        $json = json_encode($xml);
	        $array = json_decode($json,TRUE);
			if(empty($array)) return;
			if (!is_array($array['items']['item'])) {
			 return;
			}

	      	$i= 0;
	      	 $manufacturerId = $this->getManufacturerId($brand);
			  if(!empty($manufacturerId)) {
				$page = 1;
				//$this->getManufacturerVariations($manufacturerId,$page);
				$this->getManufacturerVariations($manufacturerId,1,3);
				$this->getManufacturerVariations($manufacturerId,1,1);
			  }
			  if($this->printme == "Y") {
				//echo json_encode($this->variations);
			  }
	      if (is_array($array['items']['item'])) {
	        foreach ($array['items']['item'] as $items) {

				$availability = $this->checkAvailability($items);
				if($availability == "1") {
				 continue;
				}

	            $arritem = $this->createItem($items);
	            if($this->printme == "Y") {
					 //echo json_encode($arritem);
				}
				if(empty($arritem['itemId'])) continue;

	            if(empty($arritem['variationId'])) {
					if(!empty($arritem['itemId'])) {
						$this->deleteItem($arritem['itemId']);
					}
					continue;
				}
	            // Activate item
	             $status = $this->ActiveItem($arritem['itemId'], $arritem['variationId'], $items );

	           // if($status == false) continue;
	            $salesPrice = $this->salesPrice($arritem['variationId'],$items);
	            $model = isset($items['models']['model']['availability'])?$items['models']['model']:$items['models']['model'][0];
	            $barCode = rand(10,1000000);
	            if(!empty($model['barcode'])) {
					$barCode = $model['barcode'];
				}
	            //$barcode = $this->linkingBarcode($arritem['itemId'], $arritem['variationId'], $barCode);
				$txtDes = isset($items['description'])?html_entity_decode($items['description']):'';
				$txtDes = trim(strip_tags($txtDes));
	            $discription = $this->ItemDiscription($arritem['itemId'], $arritem['variationId'], $items['name'], $txtDes);
	            $this->uploadImages($items, $arritem);
	           // echo "Create Sub version";
	            $this->createSubVariation($arritem['itemId'], $arritem['variationId'], $items);
	            $this->ActivateShippingProf($arritem['itemId']);

	            $countVariation = $this->countVariation($arritem['itemId']);
	            if(isset($countVariation['entries'][1])) {}
	            else {
				$this->deleteItem($arritem['itemId']);
				if($this->printme == "Y") {
					//echo "DELETED";
				}
				}

	            $i++;
	        }

	      }

	       echo "Total item inserted: ".$i;
	       echo "<br>Please use browser's back button to go back on brand selection page";
		}
	}

	public function linkingBarcode($ItemId, $variationId, $code){


	    $curl = curl_init();

	    curl_setopt_array($curl, array(
	      CURLOPT_URL => $this->plentyhost."/rest/items/".$ItemId."/variations/".$variationId."/variation_barcodes",
	      CURLOPT_RETURNTRANSFER => true,
	      CURLOPT_ENCODING => "",
	      CURLOPT_MAXREDIRS => 10,
	      CURLOPT_TIMEOUT=> 90000000,
	      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	      CURLOPT_CUSTOMREQUEST => "POST",
	      CURLOPT_POSTFIELDS => "{\n    \"barcodeId\": 1,\n    \"code\": \"$code\"\n}",
	      CURLOPT_HTTPHEADER => array(
	        "authorization: Bearer ".$this->access_token,
	        "cache-control: no-cache",
	        "content-type: application/json"
	      )

	    ));

	    $response = curl_exec($curl);
	    $err = curl_error($curl);

	    curl_close($curl);

	    if ($err) {
	      return "cURL Error #:" . $err;
	    } else {
	      return $response;
	    }
	}
	public function login($host){

	    $curl = curl_init();
	    curl_setopt_array($curl, array(
	      CURLOPT_URL => "https://".$host."/rest/login",
	      CURLOPT_RETURNTRANSFER => true,
	      CURLOPT_ENCODING => "",
	      CURLOPT_MAXREDIRS => 10,
	      CURLOPT_TIMEOUT => 90000000,
	      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	      CURLOPT_CUSTOMREQUEST => "POST",
	      CURLOPT_POSTFIELDS => "username=API-USER&password=%5BnWu%3Bx%3E8Eny%3BbSs%40",
	      CURLOPT_HTTPHEADER => array(
	        "cache-control: no-cache",
	        "content-type: application/x-www-form-urlencoded"
	      )
	    ));

	    $response = curl_exec($curl);
	    $err = curl_error($curl);

	    curl_close($curl);

	    if ($err) {
	      return "cURL Error #:" . $err;
	    } else {
	      return $response;
	    }
	}
	public function createItem($items){

	    $curl = curl_init();
	    if(empty($items)) return "";
	    $title = $items['name'];
	    //$itemId = $items['id'];
	    $manufacturerId = $this->getManufacturer($items);
	    $catId = $this->getCategory($items);
	    curl_setopt_array($curl, array(
	      CURLOPT_URL => $this->plentyhost."/rest/items",
	      CURLOPT_RETURNTRANSFER => true,
	      CURLOPT_ENCODING => "",
	      CURLOPT_MAXREDIRS => 10,
	      CURLOPT_TIMEOUT => 90000000,
	      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	      CURLOPT_CUSTOMREQUEST => "POST",
	      CURLOPT_POSTFIELDS => "{\n\t\"title\": \"$title\",\n\t\"stockType\": 0,\n\t\"variations\": [{\n\t\t\"variationCategories\": [{\n\t\t\t\"categoryId\": $catId\n\t\t}],\n\t\t\"unit\": {\n\t\t\t\"unitId\": 1,\n\t\t\t\"content\": 1\n\t\t}\n\t}],\n\t\"manufacturerId\": $manufacturerId,\n \n\"flagTwo\":3 \n}",
	      CURLOPT_HTTPHEADER => array(
	        "accept: application/json",
	        "authorization: Bearer ".$this->access_token,
	        "cache-control: no-cache",
	        "content-type: application/json"
	      )
	    ));

	    $response = curl_exec($curl);
	    $err = curl_error($curl);
	    curl_close($curl);

	    if ($err) {
	      echo "cURL Error #:" . $err;
	    } else {
	      //echo $response;
	      $response =(json_decode($response,true));
	      $variationId = $response['mainVariationId'];
	      if(empty($variationId)) return;
	      // Activate the item and return to main function

	      return array('itemId' => $response['id'], 'variationId' => $variationId);
	    }
	}

	public function uploadImages($items, $arritem){
	    $itemId = $arritem['itemId'];
	    $images = array();
	    if(isset($items['pictures']['image']['id'])) {
	      $images[] = $items['pictures']['image'];
	    }
	    else {
	      for($i=0; $i<count($items['pictures']['image']); $i++) {

	      $images[] = $items['pictures']['image'][$i];
	    }

	    }

	    foreach($images as $image) {
	        $img = $this->uploadImage($itemId, $image['url'], $image['id']);

	    }
	}

	public function uploadImage($ItemId, $image, $imagevalue){

	    $img = $image;
	    $imgName = explode("/",$img);

	    $name[0] = array("lang" => "en","name" => "Stock product image");
	    $availabilities[0] = array("type" => "mandant","value" => "$imagevalue");
	    $requestdata = Array(
	    "itemId" => "$ItemId",
	    "uploadFileName" => "$imgName[2]",
	    "uploadUrl" => "https://www.brandsdistribution.com".$image,
	    $name,
	    $availabilities
	);
	  $requestdata = json_encode($requestdata);
	  $curl = curl_init();
	    curl_setopt_array($curl, array(
	      CURLOPT_URL => $this->plentyhost."/rest/items/".$ItemId."/images/upload",
	      CURLOPT_RETURNTRANSFER => true,
	      CURLOPT_ENCODING => "",
	      CURLOPT_MAXREDIRS => 10,
	      CURLOPT_TIMEOUT => 90000000,
	      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	      CURLOPT_CUSTOMREQUEST => "POST",
	      CURLOPT_POSTFIELDS => $requestdata,
	      CURLOPT_HTTPHEADER => array(
	        "authorization: Bearer ".$this->access_token,
	        "cache-control: no-cache",
	        "content-type: application/json"
	      )
	    ));

	    $response = curl_exec($curl);
	    $err = curl_error($curl);

	    curl_close($curl);

	    if ($err) {
	      return "cURL Error #:" . $err;
	    } else {
	      return $response;
	    }
	}

	public function ActiveItem($itemId, $variationId, $items ){

	    $curl = curl_init();
	    $model = isset($items['models']['model']['availability'])?$items['models']['model']:$items['models']['model'][0];

	    //print_r($model);

	    $suggestedPrice = $items['suggestedPrice'];
	    $id = #$model['id'];
	    $code = $model['code'];
	    $availability = $items['availability'];
	    $streetPrice = $items['streetPrice'];
	    $model = $model['model'];
	    # get id of color
	    $purchasePrice = 0;
	    $avgPrice = 0;
	    $salePriceRRP = $streetPrice;
	    $salePrice = $suggestedPrice;
		$saleP = "";
		if($suggestedPrice < $streetPrice) {
			$saleP = $suggestedPrice;
		}
		else {
			$saleP = $streetPrice;
		}
	    $weight = 0;
	    if (!empty($items['weight'])) {
	        $weight = $items['weight'] * 1000;
	    }
	    $name_id = $this->searchAttributeName('Colour');
	    $colorValue = $this->searchAttributeValue($name_id,$model['color'],1);
	    $size_id = $this->searchAttributeName('Size');
	    $sizeValue = $this->searchAttributeValue($size_id,$model['size'],1);
	    curl_setopt_array($curl, array(
	      CURLOPT_URL => $this->plentyhost."/rest/items/".$itemId."/variations/".$variationId."",
	      CURLOPT_RETURNTRANSFER => true,
	      CURLOPT_ENCODING => "",
	      CURLOPT_MAXREDIRS => 10,
	      CURLOPT_TIMEOUT => 90000000,
	      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	      CURLOPT_CUSTOMREQUEST => "PUT",
	      CURLOPT_POSTFIELDS => "{\n    \"isActive\": true,\n    \"itemId\":\"$itemId\",\n \"mainWarehouseId\": 104,\n    \"model\": \"$model\",\n    \"name\": \"$code\",\n    \"weightG\": $weight, \n    \"weightNetG\": $weight,\n  \"variationSalesPrices\":[{\n  \"salesPriceId\": 1,\n  \"price\": $saleP\n  }]\n }",
	      CURLOPT_HTTPHEADER => array(
	        "authorization: Bearer ".$this->access_token,
	        "cache-control: no-cache",
	        "content-type: application/json"
	      ),
	    ));

	    $response = curl_exec($curl);
	    $err = curl_error($curl);

	    curl_close($curl);

	    if ($err) {
	      echo $err;
	      //return "cURL Error #:" . $err;
	    } else {
	      if($this->printme == "Y") {
			//echo $response;
			}
	      $response = json_decode($response, TRUE);
	      $isActive = $response['isActive'];
	      return $isActive;

	      // return "true";
	    }

	  }

	public function searchAttributeName($name) {

	    $curl = curl_init();

	    curl_setopt_array($curl, array(
	      CURLOPT_URL => $this->plentyhost."/rest/items/attributes",
	      CURLOPT_RETURNTRANSFER => true,
	      CURLOPT_ENCODING => "",
	      CURLOPT_MAXREDIRS => 10,
	      CURLOPT_TIMEOUT => 90000000,
	      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	      CURLOPT_CUSTOMREQUEST => "GET",
	      CURLOPT_HTTPHEADER => array(
	        "authorization: Bearer ".$this->access_token,
	        "cache-control: no-cache",
	      ),
	    ));

	    $response = curl_exec($curl);
	    $err = curl_error($curl);

	    curl_close($curl);

	    if ($err) {
	      return "cURL Error #:" . $err;
	    } else {
	      $response = json_decode($response, TRUE);
	      $entries = $response['entries'];
	      foreach ($entries as $entry) {
	        if($entry['backendName'] == $name) {
	            return $entry['id'];
	            break;
	        }
	        }
	        return '';
	    }
	}

	public function searchAttributeValue($id,$value,$page) {

	    $curl = curl_init();

	    curl_setopt_array($curl, array(
	      CURLOPT_URL => $this->plentyhost."/rest/items/attributes/".$id."/values?page=".$page,
	      CURLOPT_RETURNTRANSFER => true,
	      CURLOPT_ENCODING => "",
	      CURLOPT_MAXREDIRS => 10,
	      CURLOPT_TIMEOUT => 90000000,
	      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	      CURLOPT_CUSTOMREQUEST => "GET",
	      CURLOPT_HTTPHEADER => array(
	        "authorization: Bearer ".$this->access_token,
	        "cache-control: no-cache",
	      ),
	    ));

	    $response = curl_exec($curl);
	    $err = curl_error($curl);

	    curl_close($curl);

	    if ($err) {
	      return "cURL Error #:" . $err;
	    } else {
	      $response = json_decode($response, TRUE);
	      $entries = $response['entries'];
	      //print_r($entries); exit;
	      $matched = "";
	      foreach ($entries as $entry) {
	        if(strtolower($entry['backendName']) == strtolower("$value")) {
	            $matched = $entry['id'];
	            break;
	        }
	       }
	      if(!empty($matched)) {
			return $matched;
		  }
		  $last_page = $response['lastPageNumber'];
	      if($page != $last_page) {
			$page++;
			return $this->searchAttributeValue($id,$value, $page);
		  }
	        // No match create attribute value
	        $valId = $this->createAttributeValue($id, $value);
	        return $valId;
	    }
	}

	public function createAttributeValue($id,$value) {

	    $curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->plentyhost."/rest/items/attributes/".$id."/values",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 90000000,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => "{\n    \"attributeId\": $id,\n    \"backendName\": \"$value\"\n}",
		  CURLOPT_HTTPHEADER => array(
		    "authorization: Bearer ".$this->access_token,
		    "cache-control: no-cache",
		    "content-type: application/json",
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  echo "cURL Error #:" . $err;
		} else {
		  $response = json_decode($response, TRUE);
		  $value_id =  $response['id'];
		  // set name of value
		  $this->setValueName($value_id, 'en', $value);
		  $this->setValueName($value_id, 'de', $value);
		  return $value_id;
		}
	}

	public function setValueName($valueId, $lang, $name) {

	    $curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->plentyhost."/rest/items/attribute_values/".$valueId."/names",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 90000000,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => "{\n    \"valueId\": $valueId,\n    \"lang\": \"$lang\",\n    \"name\": \"$name\"\n}",
		  CURLOPT_HTTPHEADER => array(
		    "authorization: Bearer ".$this->access_token,
		    "cache-control: no-cache",
		    "content-type: application/json",
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  echo $err;
		} else {
		  $response = json_decode($response, TRUE);
		  return $value_id =  $response['valueId'];
		  // set name of value
		}
	}

	public function createSubVariation($itemId, $variationId, $items){

	    $models = array();
	    if(isset($items['models']['model']['availability'])) {
	      $models[] = $items['models']['model'];
	    }
	    else {
	      for($i=0; $i<count($items['models']['model']); $i++) {

	      $models[] = $items['models']['model'][$i];
	      }

	    }

	    if(empty($models)) return;
	    foreach($models as $model) {
	    $suggestedPrice = $model['suggestedPrice'];
	    $id = $model['id'];
	    $code = $model['code'];
	    $availability = $model['availability'];
	    $streetPrice = $model['streetPrice'];
	    $modelValue = $model['model'];
	    $barcode = $model['barcode'];
	    # get id of color
	    $purchasePrice = 0;
	    $avgPrice = 0;
	    $salePriceRRP = $streetPrice;
	    $salePrice = 0;
	    if(!empty($suggestedPrice))
	      $salePrice = $suggestedPrice;

	    $name_id = $this->searchAttributeName('Colour');
	    $colorValue = $this->searchAttributeValue($name_id,$model['color'],1);
	    $size_id = $this->searchAttributeName('Size');
	    $sizeValue = $this->searchAttributeValue($size_id,$model['size'],1);

	    $curl = curl_init();

	    curl_setopt_array($curl, array(
	      CURLOPT_URL => $this->plentyhost."/rest/items/".$itemId."/variations",
	      CURLOPT_RETURNTRANSFER => true,
	      CURLOPT_ENCODING => "",
	      CURLOPT_MAXREDIRS => 10,
	      CURLOPT_TIMEOUT => 900000000,
	      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	      CURLOPT_CUSTOMREQUEST => "POST",
	      //CURLOPT_POSTFIELDS => "{\n    \"itemId\": $itemId,\n    \"isActive\": true,\n    \"purchasePrice\": $purchasePrice,\n    \"name\": \"$code\",\n    \"model\": \"$modelValue\",\n    \"number\": \"$id\",\n    \"availability\": $availability,\n    \"movingAveragePrice\": $avgPrice,\n    \"mainWarehouseId\": 104,\n    \"unit\": {\n        \"unitId\": 1,\n        \"content\": 1\n    },\n \"variationAttributeValues\": [\n        {\n            \"valueId\": $colorValue\n        },\n        {\n            \"valueId\": $sizeValue\n        }\n        ],\n   \"variationClients\": [\n        {\n            \"plentyId\": 42296\n        }\n  ],\n  \"variationBarcodes\": [{\n  \t\t\"barcodeId\":1,\n  \t\t\"code\": \"$barcode\"\n  \t}],\n  \"variationSalesPrices\":[{\n  \"salesPriceId\": 1,\n  \"price\": $salePrice\n  },{\n  \"salesPriceId\": 2,\n  \"price\": $salePriceRRP\n  }]\n}",
	      CURLOPT_POSTFIELDS => "{\n    \"itemId\": $itemId,\n    \"isActive\": true,\n    \"purchasePrice\": $purchasePrice,\n    \"name\": \"$code\",\n    \"model\": \"$modelValue\",\n    \"number\": \"$id\",\n    \"availability\": $availability,\n    \"movingAveragePrice\": $avgPrice,\n    \"mainWarehouseId\": 104,\n    \"unit\": {\n        \"unitId\": 1,\n        \"content\": 1\n    },\n \"variationAttributeValues\": [\n        {\n            \"valueId\": $colorValue\n        },\n        {\n            \"valueId\": $sizeValue\n        }\n        ],\n   \"variationClients\": [\n        {\n            \"plentyId\": 42296\n        }\n  ],\n  \"variationBarcodes\": [{\n  \t\t\"barcodeId\":1,\n  \t\t\"code\": \"$barcode\"\n  \t}]\n}",
	      CURLOPT_HTTPHEADER => array(
	        "authorization: Bearer ".$this->access_token,
	        "cache-control: no-cache",
	        "content-type: application/json"
	      ),
	    ));

	    $response = curl_exec($curl);
	    $err = curl_error($curl);

	    curl_close($curl);


	    if ($err) {
	      echo "cURL Error #: $id " . $err;
	    }
	    else {
			 if($this->printme == "Y") {
				//echo $response;
			}
	      $response = json_decode($response, TRUE);
		  $vid =  @$response['id'];
		  if(!empty($vid)) {
		  	$this->activateSubVariation($itemId, $vid);
			$this->bookIncomingStock($itemId, $vid, $items, $model);
		  }
	    }

	  }
	}

	public function ItemDiscription($itemId, $variationId, $ItemName, $discription){

	    $curl = curl_init();
		$postdata = "itemId=".$itemId."&lang=en&name=".htmlentities($ItemName)."&description=".htmlentities($discription);

	    curl_setopt_array($curl, array(
	      CURLOPT_URL => $this->plentyhost."/rest/items/".$itemId."/variations/".$variationId."/descriptions",
	      CURLOPT_RETURNTRANSFER => true,
	      CURLOPT_ENCODING => "",
	      CURLOPT_MAXREDIRS => 10,
	      CURLOPT_TIMEOUT => 900000000,
	      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	      CURLOPT_CUSTOMREQUEST => "POST",
	      CURLOPT_POSTFIELDS => $postdata,
	      CURLOPT_HTTPHEADER => array(
	        "authorization: Bearer ".$this->access_token,
	        "cache-control: no-cache",
	         "content-type: application/x-www-form-urlencoded"
	      ),
	    ));
		//"{\"itemId\": $itemId,\"lang\": \"en\",\"name\": \"$ItemName\",\"description\": \"$discription\"}",
	    $response = curl_exec($curl);
	    $err = curl_error($curl);

	    curl_close($curl);

	    if ($err) {
	      return "cURL Error #:" . $err;
	    } else {
	      return $response;
	    }
	}

	public function salesPrice($variationId, $items){

	    $curl = curl_init();
		$salePriceRRP = $items['streetPrice'];
		$salePrice = $items['suggestedPrice'];

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->plentyhost."/rest/items/variations/variation_sales_prices",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 90000000,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => "[{\n\t\"variationId\": $variationId,\n\t\"salesPriceId\": 1,\n\t\"price\": $salePrice\n},{\n  \"salesPriceId\": 2,\n  \"price\": $salePriceRRP\n  }]",
		  CURLOPT_HTTPHEADER => array(
		    "authorization: Bearer ".$this->access_token,
		    "cache-control: no-cache",
		    "content-type: application/json"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  echo "cURL Error #:" . $err;
		} else {
		  //echo $response;
		}
	}

	public function getManufacturer($items){

		$curl = curl_init();
		$brand = $items['brand'];
		if(empty($brand))return;

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->plentyhost."/rest/items/manufacturers?name=".urlencode($brand),
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 90000000,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "GET",
		  CURLOPT_HTTPHEADER => array(
		    "authorization: Bearer ".$this->access_token,
		    "cache-control: no-cache"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  echo "cURL Error #:" . $err;
		} else {
		  $result = json_decode($response, TRUE);
		  if(isset($result['entries'][0]['id'])){
		    return $result['entries'][0]['id'];
		  }else{
		    return $this->creatManufacturer($brand);
		  }
		}
	}

	public function creatManufacturer($brand){

		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->plentyhost."/rest/items/manufacturers",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 90000000,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => "{\n\t\"name\": \"$brand\"\n}",
		  CURLOPT_HTTPHEADER => array(
		    "authorization: Bearer ".$this->access_token,
		    "cache-control: no-cache",
		    "content-type: application/json"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  echo "cURL Error #:" . $err;
		} else {
		  $response = json_decode($response,TRUE);
		  if(!empty($response['id'])){
		    return $response['id'];
		  }
		}
	}

	public function getCategory($items){
	    $tags = array();
	    if(isset($items['tags']['tag'][0]['id']))
	      $tags = $items['tags']['tag'];
	    else
	      $tags[0] = $items['tags']['tag'];
	    if(empty($tags) || empty($tags[0]))return;
	    $catName = "";
	    foreach ($tags as $tag) {
	      if($tag['name'] == "category"){
	        $catName = $tag['value']['value'];
	        break;
	      }
	    }
	    if (empty($catName)) return;
	    $catId =  $this->searchCategory($catName);
	    if(empty($catId)) {
			// Create category
			$catId = $this->createCategory($catName);
		}
		return $catId;
	}

	public function searchCategory($catName){

		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->plentyhost."/rest/categories/?name=".$catName,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 900000,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "GET",
		  CURLOPT_HTTPHEADER => array(
		    "authorization: Bearer ".$this->access_token,
		    "cache-control: no-cache",
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  echo "cURL Error #:" . $err;
		} else {
		  $response = json_decode($response,TRUE);
		  if(empty($response['entries'])) return "";
		  return $response['entries'][0]['id'];
		}
	}

	public function createCategory($name) {

		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->plentyhost."/rest/categories",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 9000000,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => "[\n        {\n            \"parentCategoryId\": null,\n            \"type\": \"item\",\n            \"right\": \"all\",\n            \"details\": [\n                {\n                    \"plentyId\": 42296,\n                    \"lang\": \"en\",\n                    \"name\": \"$name\"\n                }\n            ],\n            \"clients\": [\n                {\n                    \"plentyId\": 42296\n                }\n            ]\n        }\n    ]",
		  CURLOPT_HTTPHEADER => array(
		    "authorization: Bearer ".$this->access_token,
		    "cache-control: no-cache",
		    "content-type: application/json",
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  echo "cURL Error #:" . $err;
		} else {
		  $response = json_decode($response,TRUE);
		   if(isset($response[0]['id']))
			return $response[0]['id'];
			else
			return "";
		}
	}

	public function bookIncomingStock($itemsId, $variationId, $items, $model) {

			$curl = curl_init();
			$dt = date('c', time());
			$currency = $items['currency'];
			$purchasePrice = "0.00";
			$qty = $model['availability'];

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->plentyhost."/rest/items/".$itemsId."/variations/".$variationId."/stock/bookIncomingItems",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 900000000,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "PUT",
		  CURLOPT_POSTFIELDS => "{\n    \"warehouseId\": 104,\n    \"deliveredAt\": \"$dt\",\n    \"currency\": \"$currency\",\n    \"quantity\": $qty,\n    \"purchasePrice\":$purchasePrice,\n    \"reasonId\": 101\n\n}",
		  CURLOPT_HTTPHEADER => array(
		    "authorization: Bearer ".$this->access_token,
		    "cache-control: no-cache",
		    "content-type: application/json",
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  echo "cURL Error #:" . $err;
		} else {
		 //echo $response;
		}
  	}

	public function activateSubVariation($itemId, $variationId){

		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->plentyhost."/rest/items/".$itemId."/variations/".$variationId,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 900000000,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "PUT",
		  CURLOPT_POSTFIELDS => "{\n    \"isActive\": true\n   \n    \n}",
		  CURLOPT_HTTPHEADER => array(
		    "authorization: Bearer ".$this->access_token,
		    "cache-control: no-cache",
		    "content-type: application/json"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  echo "cURL Error #:" . $err;
		} else {
		  // echo $response;
		}
	}

	public function ActivateShippingProf($ItemId){

		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->plentyhost."/rest/items/".$ItemId."/item_shipping_profiles",
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 9000000,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  CURLOPT_POSTFIELDS => "{\n    \"profileId\": 6\n}",
		  CURLOPT_HTTPHEADER => array(
		    "authorization: Bearer ".$this->access_token,
		    "cache-control: no-cache",
		    "content-type: application/json"
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  echo "cURL Error #:" . $err;
		} else {
		  //echo $response;
		}
	}
	public function getManufacturerId($brand) {
		$curl = curl_init();

		curl_setopt_array($curl, array(
	  CURLOPT_URL => $this->plentyhost."/rest/items/manufacturers?name=".urlencode($brand),
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => "",
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 9000000,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => "GET",
	  CURLOPT_HTTPHEADER => array(
		"authorization: Bearer ".$this->access_token,
		"cache-control: no-cache",
		"content-type: application/json",
	  ),
	));

	$response = curl_exec($curl);
	$err = curl_error($curl);

	curl_close($curl);

	if ($err) {
	  echo "cURL Error #:" . $err;
	} else {

	  $response =json_decode($response,true);
	  if(!empty($response) && isset($response['entries'][0]['id']))
		return $response['entries'][0]['id'];
	  else
		return "";
	}
	}

	public function getManufacturerVariations($manufacturerId, $page, $flag) {

		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->plentyhost."/rest/items/variations?manufacturerId=".$manufacturerId."&isActive=true&plentyId=42296&flagTwo=".$flag."&page=".$page,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 100,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "GET",
		  CURLOPT_HTTPHEADER => array(
		    "authorization: Bearer ".$this->access_token,
		    "cache-control: no-cache",
		    "content-type: application/json",
		  ),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
		  echo "cURL Error #:" . $err;
		} else {
			//echo $response;
		  $response =json_decode($response,true);
		  if(isset($response['entries']) && !empty($response['entries'])) {
			  foreach($response['entries'] as $entries) {
				//if($entries['isMain'] == true) continue;
				$number = $entries['number'];
				$this->variations[$number] = $entries['id'];
			  }
		  }

		}
		 $last_page = $response['lastPageNumber'];
		if($page != $last_page && $last_page != 0) {
			$page++;
			$this->getManufacturerVariations($manufacturerId, $page, $flag);
		}
	}

public function checkAvailability($items) {
	$models = array();
	$found = "2";
    if(isset($items['models']['model']['availability'])) {
      if(array_key_exists($items['models']['model']['id'], $this->variations)) {
		return "1";
	  }
    }
    else {
      for($i=0; $i<count($items['models']['model']); $i++) {
		if(array_key_exists($items['models']['model'][$i]['id'], $this->variations)) {
			$found = "1"; break;
		}

      }
    }
     return $found;
}
public function getBrands() {

	  $curl = curl_init();

	  curl_setopt_array($curl, array(
	  CURLOPT_URL => "https://raw.githubusercontent.com/srsinfosystems/nunobrands/master/brands.txt?".time(),
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => "",
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 9000000,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => "GET",
	  CURLOPT_HTTPHEADER => array(
		"cache-control: no-cache",
		"content-type: application/json",
	  ),
	));

	$response = curl_exec($curl);
	$err = curl_error($curl);

	curl_close($curl);

	if ($err) {
	  echo "cURL Error #:" . $err;
	} else {

	 $response = explode("\n", $response);
	 return $response;
	}

		/*
		$brands = array('Adidas','Bikkembergs','Coach','Converse','Desigual','Diadora','Diadora Heritage','Diesel','Emporio Armani','Gant','Geographical Norway','Geox','Guess','Hugo Boss','Lacoste','Love Moschino','Michael Kors','Napapijri','New Balance','Nike','Ocean Sunglasses','Puma','Ralph Lauren','Ray-Ban','Saucony','Superga','TOMS','The North Face','Timberland','Tommy Hilfiger','U.S. Polo','Vans','Versace Jeans');
		$brands = array('Adidas');
		return $brands;
		*/

	}

	public function countVariation($item) {
	$curl = curl_init();

	curl_setopt_array($curl, array(
  CURLOPT_URL => $this->plentyhost."/rest/items/".$item."/variations",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 9000000,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "GET",
  CURLOPT_HTTPHEADER => array(
    "authorization: Bearer ".$this->access_token,
    "cache-control: no-cache",
    "content-type: application/json",
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {

  $response =json_decode($response,true);
  return $response;
}
}

public function deleteItem($number){
$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => $this->plentyhost."/rest/items/".$number,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 9000000,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "DELETE",
  CURLOPT_HTTPHEADER => array(
    "authorization: Bearer ".$this->access_token,
    "cache-control: no-cache"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  echo $response;
}
}

}
