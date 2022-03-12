<?php 

// POWERED BY: MiUnlockCode

header_remove();
ini_set('default_charset', '');
if (isset($_POST['ActivationInfoXML']) || isset($_POST['activation-info'])) { }
else { die("Error: 473"); }
require('./var/www/crypt/RSA.php');

if ( isset($_GET['generateAppTicket']) ) { $file = './var/www/requests/'.$_GET['generateAppTicket'];
	if ( is_file($file) ) { header("Content-Type: text/xml");
		header("ARS: ".file_get_contents('./var/www/requests/'.$_GET['saveRequestTicket'].'-ARS.txt'));
		$activationinfo = file_get_contents($file);
		if (strpos($activationinfo, 'Apple ID') !== false) exit('Please disable "Find My iPhone" and retry.');
		else exit($activationinfo);
	}
} elseif ( isset($_POST['saveRequestTicket']) ) { actAlbert($_POST['ActivationInfoXML']);
} elseif ( isset($_POST['saveRequestTicketWindows']) ) { actAlbert($_POST['ActivationInfoXML']);
} elseif ( isset($_POST['getHandShake']) ) { getHandShake();
} elseif ( isset($_POST) && !empty($_POST)) { actAlbert($_POST);
} else { die;
}

function actAlbert($activationinfo) {
	$ipaddress = get_client_ip();
	if (isset($_POST['ActivationInfoXML'])) { $rawactpost = $_POST['ActivationInfoXML']; }
	elseif (isset($_POST['activation-info'])) { $rawactpost = $_POST['activation-info']; }
	/*if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'iDevice') !== false || strpos($_SERVER['HTTP_USER_AGENT'], 'FirstApp') !== false) {
		if (strpos($activationinfo, 'activation-info') == false) { $activationinfo = http_build_query(array('activation-info' => $activationinfo )); }
	}*/
	$activationinfo = http_build_query(array('activation-info' => $activationinfo ));
	if (isset($_GET['saveRequestTicket']) && !isset($_GET['update'])) {
		if (is_file('./var/www/requests/'.$_GET['saveRequestTicket'].'.html')) {
			if (is_file('./var/www/requests/'.$_GET['saveRequestTicket'].'-ARS.txt')) { header("ARS: ".file_get_contents('./var/www/requests/'.$_GET['saveRequestTicket'].'-ARS.txt')); }
			exit(file_get_contents('./var/www/requests/'.$_GET['saveRequestTicket'].'.html'));
		}
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: text/xml"));
	curl_setopt($ch, CURLOPT_URL, "https://albert.apple.com/deviceservices/deviceActivation");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $activationinfo);
	curl_setopt($ch, CURLOPT_VERBOSE, 1);
	curl_setopt($ch, CURLOPT_HEADER, 1);
	$albert = curl_exec($ch);
	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
	$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
	$header = substr($albert, 0, $header_size);
	$body = substr($albert, $header_size);
	curl_close ($ch);

	$albertHeaderSize = $header_size;
	$albertBodySize = strlen($body);	
	http_response_code($httpcode);
	if (strpos($header, 'ARS') !== false) {
		$ARS = explode("\r\nARS: ", $header);
		$ARS = explode("\r\n", $ARS[1]);
		$ARS = $ARS[0];
		header("ARS: " . $ARS);
	} else { $ARS = ''; }
	foreach (getallheaders() as $name => $value) { $headers[$name] = $value; }
	$logfile = "logfilename.log";
	$rawpost = json_encode($_POST, true);
	$rawget = json_encode($_GET, true);
	$rawdata = file_get_contents('php://input');
	$boundary = boundary();

	if(strpos($body, 'clientInfo ack-received="true') !== false) {
			header("Content-Type: application/x-buddyml");
			echo $body;
			exit;
	} elseif(strpos($body, 'AccountToken---Certificate') !== false) {
		$data = ActivationRecordXML($body);
		$dir = './var/www/requests';
		$requestfile = '';
		if(isset($_GET['saveRequestTicket'])) { $requestfile .= $_GET['saveRequestTicket']; }
		file_put_contents('./var/www/requests/'.$requestfile.'-AccTokenCert.txt' ,$data['AccountTokenCertificate']."\n".urldecode($activationinfo)."\n".$body);
//		openssl_sign(base64_decode($data['AccountToken']), $signature, openssl_pkey_get_private(PrivateKeyRaptor()), OPENSSL_ALGO_SHA1);
//		$body = str_replace($data['AccountTokenSignature'], '', $body);
//		$body = str_replace($data['AccountTokenCertificate'], AccountTokenCertificateTest(), $body);
//		$body = str_replace($data['UniqueDeviceCertificate'], '', $body);
//		file_put_contents('./var/www/requests/'.$_GET['saveRequestTicket'].'-Body.txt', "\nOur sign:\n".$signature."\nAlb sign:\n".$data['AccountTokenSignature']."\nCert:\n".AccountTokenCertificateTest());
//		if (!is_dir('./var/www/requests')) mkdir('./var/www/requests');
		$data = call_user_func_array('array_merge', [ActivationRecordXML($body), ActivationRequestInfoXML($rawactpost, 1)]);
		$FairPlayKeyData = $data['FairPlayKeyData'];
		if(isset($data['SerialNumber'])) {
			$data['ARS'] = $ARS;
			$data['albertHeaderSize'] = $albertHeaderSize;
			$data['albertBodySize'] = $albertBodySize;
			$data['outputBodySize'] = strlen($body);
			$data['outputHeaders'] = headers_list();
			$fileDeviceCertRequest = $dir.'/'.$data['SerialNumber'].'-DeviceCert.pem';
			file_put_contents($fileDeviceCertRequest, base64_decode($data['DeviceCertRequest']));
			$CSRout = base64_encode(shell_exec("(openssl x509 -req -days 1096 -CA $dir/CAcrt.pem -CAkey $dir/privkey.pem -set_serial 0x$(openssl rand -hex 9) -outform PEM -sha1 -extensions v3_ca -extfile $dir/CSR.cnf -in $fileDeviceCertRequest)"));
//			// Replace DeviceCertificate //
//			$body = str_replace($data['DeviceCertificate'], $CSRout, $body);
//			$body = str_replace($data['ActivationInfoXML'], str_repeat("1", strlen($data['ActivationInfoXML'])), $body);
			file_put_contents($fileDeviceCertRequest.".csr", base64_decode($data['DeviceCertRequest'])."\n\n".base64_decode($CSRout));
			$file = './var/www/requests/'.$data['SerialNumber'].'-'.$data['DeviceClass'].'-'.$data['RegionCode'].'-'.$data['ProductVersion'].'-'.$data['RegulatoryModelNumber'];
			if (is_file($file.'-all.txt')) { file_put_contents($file.'-all.txt', json_encode($data)."\r\n".file_get_contents($file.'-all.txt')); }
			else { file_put_contents($file.'-all.txt', json_encode($data)); }
			file_put_contents($file.'-raw.txt', $data);
		}
	} else {
		$serverKP = substr(chunk_split(base64_encode(current(explode('</data>', next(explode('<key>serverKP</key><data>', drmHandshake()))))), 68, "\n\t"), 0, -2);
		$data = ActivationRequestInfoXML($rawactpost, 1);
		$ActivationInfoXML = base64_decode($data['ActivationInfoXML']);
		$dataSet = ActivationRequestInfoXMLNew(reqTicket($ActivationInfoXML, $serverKP));

		$reqTicketOut = reqTicket(makeTickets($ActivationInfoXML, $dataSet['InternationalMobileEquipmentIdentity'], $dataSet['SerialNumber'], $dataSet['UniqueDeviceID']), $serverKP);
		$final = actAlbertTicket($reqTicketOut);

		$dataGet = ActivationRecordXML($final);

		if ($AccountToken = base64_decode($dataGet['AccountToken'])) {
			file_put_contents('./var/www/requests/'.$data['SerialNumber'].'-'.$data['DeviceClass'].'-'.$data['RegionCode'].'-'.$data['ProductVersion'].'-Token.txt', "Before: ".$AccountToken."\n");
			if(strpos($AccountToken, 'MobileEquipmentIdentifier') !== false) {
				$AccountToken = str_replace($dataGet['InternationalMobileEquipmentIdentity'] , $dataSet['InternationalMobileEquipmentIdentity'], $AccountToken);
				$AccountToken = str_replace($dataGet['MobileEquipmentIdentifier'] , $dataSet['MobileEquipmentIdentifier'], $AccountToken);
				$AccountToken = str_replace($dataGet['SerialNumber'] , $dataSet['SerialNumber'], $AccountToken);
				$AccountToken = str_replace($dataGet['UniqueDeviceID'] , $dataSet['UniqueDeviceID'], $AccountToken);
				if (isset($dataGet['ActivationTicket'])) {
					$AccountToken = str_replace($dataGet['ActivationTicket'], base64_encode(hex2bin(str_replace(array('35567207227802', '35495707243538'), array(substr($dataSet['InternationalMobileEquipmentIdentity'], 0, 14), substr($dataSet['InternationalMobileEquipmentIdentity'], 0, 14)), bin2hex(base64_decode($dataGet['ActivationTicket']))))), $AccountToken);
				} elseif (isset($dataGet['WildcardTicket'])) {
					$AccountToken = str_replace($dataGet['WildcardTicket'], base64_encode(hex2bin(str_replace(array('35567207227802', '35495707243538'), array(substr($dataSet['InternationalMobileEquipmentIdentity'], 0, 14), substr($dataSet['InternationalMobileEquipmentIdentity'], 0, 14)), bin2hex(base64_decode($dataGet['WildcardTicket']))))), $AccountToken);
				}
			}
			elseif(strpos($AccountToken, 'InternationalMobileEquipmentIdentity') !== false) {
				$AccountToken = str_replace($dataGet['InternationalMobileEquipmentIdentity'] , $dataSet['InternationalMobileEquipmentIdentity'], $AccountToken);
				$AccountToken = str_replace($dataGet['SerialNumber'] , $dataSet['SerialNumber'], $AccountToken);
				if (isset($dataGet['ActivationTicket'])) {
					$AccountToken = str_replace($dataGet['ActivationTicket'], base64_encode(hex2bin(str_replace(array('35567207227802', '35495707243538'), array(substr($dataSet['InternationalMobileEquipmentIdentity'], 0, 14), substr($dataSet['InternationalMobileEquipmentIdentity'], 0, 14)), bin2hex(base64_decode($dataGet['ActivationTicket']))))), $AccountToken);
				} elseif (isset($dataGet['WildcardTicket'])) {
					$AccountToken = str_replace($dataGet['WildcardTicket'], base64_encode(hex2bin(str_replace(array('35567207227802', '35495707243538'), array(substr($dataSet['InternationalMobileEquipmentIdentity'], 0, 14), substr($dataSet['InternationalMobileEquipmentIdentity'], 0, 14)), bin2hex(base64_decode($dataGet['WildcardTicket']))))), $AccountToken);
				}
			}
			elseif(strpos($AccountToken, 'SerialNumber') !== false) {
				$AccountToken = str_replace($dataGet['SerialNumber'] , $dataSet['SerialNumber'], $AccountToken);
			} else {
				die('Error: 772');
			}
//			$AccountToken = str_replace('WildcardTicket', 'ActivationTicket', $AccountToken);
			$AccountToken = str_replace('ActivationTicket', 'WildcardTicket', $AccountToken);

			openssl_sign($AccountToken, $AccountTokenSignature, openssl_pkey_get_private(PrivateKeyRaptor()), OPENSSL_ALGO_SHA1);
			$body = str_replace($dataGet['AccountTokenSignature'], base64_encode($AccountTokenSignature), str_replace($dataGet['AccountToken'], base64_encode($AccountToken), $final));
			$body = str_replace('<key>DeviceCertificate</key>', '<key>DeviceConfigurationFlags</key><string>0</string><key>DeviceCertificate</key>', $body);
/*
			$body = str_replace('<key>FairPlayKeyData</key>', '', $body);
			$body = str_replace($dataGet['FairPlayKeyData'], '', $body);
			$body = str_replace('<data></data>', '', $body);
*/
		}
		else {
			$ActivationInfoXML = $data['ActivationInfoXML'];
			if(isset($data['MobileEquipmentIdentifier']) && isset($data['ActivationRandomness'])) {
				$AccountToken = '{
	"InternationalMobileEquipmentIdentity" = "'.$data['InternationalMobileEquipmentIdentity'].'";
	"MobileEquipmentIdentifier" = "'.$data['MobileEquipmentIdentifier'].'";
	"SerialNumber" = "'.$data['SerialNumber'].'";
	"ProductType" = "'.$data['ProductType'].'";
	"UniqueDeviceID" = "'.$data['UniqueDeviceID'].'";
	"ActivationRandomness" = "'.$data['ActivationRandomness'].'";
	"ActivityURL" = "https://albert.apple.com/deviceservices/activity";
	"PhoneNumberNotificationURL" = "https://albert.apple.com/deviceservices/phoneHome";
	"ActivationTicket" = "MIIBlQIBATALBgkqhkiG9w0BAQUxYJ8/BC56C7CfQAThIG8An0sUiTgt/7kRqltcW9/wE3cVASX7moifh20HATiRACURN5+XPQwAAAAA7u7u7u7u7u+flz4EAAAAAJ+XPwQBAAAAn5dABAEAAACfl0wEAAAAAASBgKGhiSVSKmUcLcWBY4/i4hwTL8N9b6d7lEmyZ8fYnFL2+kmrSAFGAS6GILW97/2OEUFD3sMyXKEvQsjHXdMosBIUH33CaMuLi2rgEEvLGFSEYnGo5Bx9E1mtScikaAgPlxtToLyuLdz89+M9UN5xBSqTqF9RG55J5WQUd0nZhlRjo4GdMAsGCSqGSIb3DQEBAQOBjQAwgYkCgYEA7To/ZNHoIJzBUgY0734vsgl+ACxDQ+f4quvmSrPAtgDENSZwaVrHXpF+cRKBABqkDa00YcENx2dtS1tuHLKDNn1zMZLaZRpiK9UeiMPNZL6mlg12BWLwVjlFOGED8U6pfXwOw6D/FCDRgvyGBn7wsw8sEa7AdlYmMHGmkvwgOP0CAwEAAQ==";
}';			}
			elseif(isset($data['MobileEquipmentIdentifier'])) {
				$AccountToken = '{
	"InternationalMobileEquipmentIdentity" = "'.$data['InternationalMobileEquipmentIdentity'].'";
	"MobileEquipmentIdentifier" = "'.$data['MobileEquipmentIdentifier'].'";
	"SerialNumber" = "'.$data['SerialNumber'].'";
	"ProductType" = "'.$data['ProductType'].'";
	"UniqueDeviceID" = "'.$data['UniqueDeviceID'].'";
	"ActivityURL" = "https://albert.apple.com/deviceservices/activity";
	"PhoneNumberNotificationURL" = "https://albert.apple.com/deviceservices/phoneHome";
	"ActivationTicket" = "MIIBlQIBATALBgkqhkiG9w0BAQUxYJ8/BC56C7CfQAThIG8An0sUiTgt/7kRqltcW9/wE3cVASX7moifh20HATiRACURN5+XPQwAAAAA7u7u7u7u7u+flz4EAAAAAJ+XPwQBAAAAn5dABAEAAACfl0wEAAAAAASBgKGhiSVSKmUcLcWBY4/i4hwTL8N9b6d7lEmyZ8fYnFL2+kmrSAFGAS6GILW97/2OEUFD3sMyXKEvQsjHXdMosBIUH33CaMuLi2rgEEvLGFSEYnGo5Bx9E1mtScikaAgPlxtToLyuLdz89+M9UN5xBSqTqF9RG55J5WQUd0nZhlRjo4GdMAsGCSqGSIb3DQEBAQOBjQAwgYkCgYEA7To/ZNHoIJzBUgY0734vsgl+ACxDQ+f4quvmSrPAtgDENSZwaVrHXpF+cRKBABqkDa00YcENx2dtS1tuHLKDNn1zMZLaZRpiK9UeiMPNZL6mlg12BWLwVjlFOGED8U6pfXwOw6D/FCDRgvyGBn7wsw8sEa7AdlYmMHGmkvwgOP0CAwEAAQ==";
}';			}
			elseif(isset($data['ActivationRandomness'])) {
				$AccountToken = '{
	"InternationalMobileEquipmentIdentity" = "'.$data['InternationalMobileEquipmentIdentity'].'";
	"SerialNumber" = "'.$data['SerialNumber'].'";
	"ProductType" = "'.$data['ProductType'].'";
	"UniqueDeviceID" = "'.$data['UniqueDeviceID'].'";
	"ActivationRandomness" = "'.$data['ActivationRandomness'].'";
	"ActivityURL" = "https://albert.apple.com/deviceservices/activity";
	"PhoneNumberNotificationURL" = "https://albert.apple.com/deviceservices/phoneHome";
	"ActivationTicket" = "MIIBlQIBATALBgkqhkiG9w0BAQUxYJ8/BC56C7CfQAThIG8An0sUiTgt/7kRqltcW9/wE3cVASX7moifh20HATiRACURN5+XPQwAAAAA7u7u7u7u7u+flz4EAAAAAJ+XPwQBAAAAn5dABAEAAACfl0wEAAAAAASBgKGhiSVSKmUcLcWBY4/i4hwTL8N9b6d7lEmyZ8fYnFL2+kmrSAFGAS6GILW97/2OEUFD3sMyXKEvQsjHXdMosBIUH33CaMuLi2rgEEvLGFSEYnGo5Bx9E1mtScikaAgPlxtToLyuLdz89+M9UN5xBSqTqF9RG55J5WQUd0nZhlRjo4GdMAsGCSqGSIb3DQEBAQOBjQAwgYkCgYEA7To/ZNHoIJzBUgY0734vsgl+ACxDQ+f4quvmSrPAtgDENSZwaVrHXpF+cRKBABqkDa00YcENx2dtS1tuHLKDNn1zMZLaZRpiK9UeiMPNZL6mlg12BWLwVjlFOGED8U6pfXwOw6D/FCDRgvyGBn7wsw8sEa7AdlYmMHGmkvwgOP0CAwEAAQ==";
}';			}
			else { $AccountToken = '{
	"InternationalMobileEquipmentIdentity" = "'.$data['InternationalMobileEquipmentIdentity'].'";
	"SerialNumber" = "'.$data['SerialNumber'].'";
	"ProductType" = "'.$data['ProductType'].'";
	"UniqueDeviceID" = "'.$data['UniqueDeviceID'].'";
	"ActivityURL" = "https://albert.apple.com/deviceservices/activity";
	"PhoneNumberNotificationURL" = "https://albert.apple.com/deviceservices/phoneHome";
	"ActivationTicket" = "MIIBlQIBATALBgkqhkiG9w0BAQUxYJ8/BC56C7CfQAThIG8An0sUiTgt/7kRqltcW9/wE3cVASX7moifh20HATiRACURN5+XPQwAAAAA7u7u7u7u7u+flz4EAAAAAJ+XPwQBAAAAn5dABAEAAACfl0wEAAAAAASBgKGhiSVSKmUcLcWBY4/i4hwTL8N9b6d7lEmyZ8fYnFL2+kmrSAFGAS6GILW97/2OEUFD3sMyXKEvQsjHXdMosBIUH33CaMuLi2rgEEvLGFSEYnGo5Bx9E1mtScikaAgPlxtToLyuLdz89+M9UN5xBSqTqF9RG55J5WQUd0nZhlRjo4GdMAsGCSqGSIb3DQEBAQOBjQAwgYkCgYEA7To/ZNHoIJzBUgY0734vsgl+ACxDQ+f4quvmSrPAtgDENSZwaVrHXpF+cRKBABqkDa00YcENx2dtS1tuHLKDNn1zMZLaZRpiK9UeiMPNZL6mlg12BWLwVjlFOGED8U6pfXwOw6D/FCDRgvyGBn7wsw8sEa7AdlYmMHGmkvwgOP0CAwEAAQ==";
}'; 		}

			$AccountToken = base64_encode($AccountToken);
//			iPhone 5S - FPDATA
			if (empty($FairPlayKeyData)) {
				$FairPlayKeyData = 'LS0tLS1CRUdJTiBDT05UQUlORVItLS0tLQpBQUVBQWNvQkpGVDdsbGVLL01aMUpZZDY0RmhqN2tPV1ZyMDcycXdIYURqZFFsdCtNZUNDUVNKeWVwWktyZGhGCmdXNWJMUStvUjZXamk4VUc5c1JvZUZTOHJsRGRLeVNzOVZkc1BYRUlQdHVLOWJQenZuWEtCTXBLSC9LNXFNSXYKVFN6WEJweTRGY2R6dDVUMGNKRzQ3d0liNlZSYjNLT2dEQmpRR0VGM3luMDdvUmVQc2pGN0twNzh0c0wrcjRsRwo0eTBHSkVBSlY5cGh1R1g1WGpkYml3dmZ5WUcyc1o1Z3FTMlpPYlZIVnEydlo0czdzcExtREE5MDVuRis2ZXFOCkN2RlUrUmhaQlBqb1prVkNLSG9qUE44L3hyaVMwQnpDOUJiakRHcnN3dkJJcE9ndWhNT28xeThsQWpqelZJTEcKaWdMVi9oMUlyaXYzYXBmN2hVVVRURUtPeUxiVkVlYjFIM0s0b0d2WXN1ZHRBei8yTTc4dnVmTHFINlh1ODl1eApwZGgrdmtWejk2NjkxaFJJTjlCdnBiQklYT3Q5enMyckk1TjJiMENoQzkwZ2d2dDJMRlFUOXRVQm13dGhLakdGCitNSHBCaFBFSVFlOGJ0bGxUcEFnNTBraWo0alBwdW5KN0pKTmVQWE1HcnZMOVI3SlEyZEgvTDBpR3gxdG96U2IKVTJmTGdJcFJQeDBnZEtKRmVlaFQwMy8zSWh5aXRIZXcvWExxSTBxRnU1bDBwaEZ5L2ZqWEVLQkQvaG5kUGgvZgozMjZ6bWg1WVVqL2o1MnFZMWpiM3cxOUw3ZzNYMjdHTGNBNGx5U0tPTmFQUnBwbVVMbVpzV1IzN1ovb0dnL20vCmNmMGlEb3AxR0VHYjRnUVNEUXRLR3pLL2FaZmZSTFd0b0ZMWlp3RngvOWdYNnc1eFRwL1Y4YUtvK2RQTDh1a3EKOWRHanJJOTVOM1VGbG1qOUw5VEo1TFZOUWZQR0dhaUtVNi9MWEN0SktyNjJNdkhISm0vTTFpV01HVzdCalAyWAo2eTU5RFpnMUxCM05hY2tFNHJsQi9icmVneURrcXgxZjhUUURPaVBZTE9ldjcvaXdoaEJkYjdBZjA2VGhhTm50CnRuZFYydEdSTnJ4a1ZZd2NkTngwL0dSN1ZjMmUrdytRTHRsZHJmdzA0bW1QejRjRTROek5SNkdVV0VuL1dja0cKUjc1U1RYVE9LOUJaVmM3TytBTTdNUXIwTytDQnFyVVNkNnVUdzVBVTJ3c1duL3V1N2JPUUE3L0NZaXN1N1p3SAppS0RoNVVSSFU2Tmc0WlY2YWR1cjEzSDR5RUxWU2hkcUR5emplNjhrSkdWTzZBbWoxckV6alNTRTJEVjVnTWcrCmJCWTVSWVpxNHU1NG11TjdrUTZLU2sxS04yN3F0bHAvNHVsYlg5VVFzVHhtN21WU0RqellvVmlGTG1COGU5L1QKN3N1RXVjM3RySWJsTTNjdzFkMFBhS1RlYUlVcGRkTFhoZkpXVnhxTDhXU2hsTi9yVW1ibFQ5ZXlTeC9uYmYzUgppZWE5cVIxYyttV0c1ZzFrd3A5N0p5aTBWZHJUdi9QQ2p6Z01xUkNEd1RPc051elArZWk3dGxpNEVmSHJtdWx0CnRUeDZkb1FOelBnbkVWL284TUxPVTBwc0pKeU83VU9QM2RvUXh5czJQNG53V0JaS0Joc3dmbjBXanU1T2pWSU4KRyt2U2hUTy9jYktpWm1ManVGOGFRMVpyNC80WHIzZTAwNFF1Y1F3NnVaRHRhUDRNZlVSREJSMnVaOWdTZy9mTgpNVVFpcGdUeGZCUEt4K0o2eVFHcDVmQ1VEc3RCMDlSNUtDK1Z0cGxucm5pYTY1TVd2NHRQM1UrNDh0NENCSE0wClNnSkR3NWRtTHBsVjJHVFUyQ1psazJBTGlobXhONUdleE1nVVNrcjQ2dFNkWmVzbENaNUVGa3JXOTJlV3NZbmsKK1NmWkxPMEpiYnJic3FQSFVubUZuQVhHM1o3VmhvYi9WTHNkZGRCei9EQjhySmFLCi0tLS0tRU5EIENPTlRBSU5FUi0tLS0tCg==';
			}
//			iPhone 7 - FPDATA
			$FairPlayKeyData = 'LS0tLS1CRUdJTiBDT05UQUlORVItLS0tLQpBQUVBQVNyTkhHMW5jc1N5NU5HVGpNcUpYZndTcnRSeklqZklQa1E4NnY4TldJTTZ3L1J6L1RhblFEKzNtOERwCnFkNmhVZy9BUHVOK2orYmJybGlDSEt5SWtMYTZKL0ZtNFVaYlZZZjhVQ2dGWFltMDJEZ1JqQ1FGUHBoUjJ6eVMKd2V4a0dKaVFNNE1pQUdPY2ZvZjBETG5ldXpUODlqRnM3eExUWjEzbXRFT2txd3BVMU81elp0UGtYK2VSUUJjdgp0RjgrYXNDZGQrVDlYQ29OSXhadGE4NnlVeFRLSWdGMk5nL3R4VnNyUkcwV29MdmcxL2d4amlhaElMODBFTjJwCjRJUnlvTUJjV3AyK00rd2FiY3dVZWNEVmVyK1dNSURIeDlkRGdGaWxLQm5Vd1RJVUZoaktwSGQwTHRXRHpzVWUKM1FyKzVvRnkwN21jM1FUc1BFdkx1dzhBbThWMHNCSTVUVXVxaTEycllmQVJmVk9vZ1l4M2FNN0lsMEphZzV2WAo5dWJEUDRrVEVYc1QrQXNjU1UwOHpRVVFDeXpyMUlGZFBJOTBqbFkvMVB0ekJLZHpaL1JZMUtSY2NLQ1VJZW8zCmNsdnRxUVNKWnNhelJmRmg1cmdkNnQ4NWZXeTIzMXZ5bGsyWVlmYjdIeTVhdzBBSzk3OHFKQWg0WDAyZUsyWG8KeXAreVZlVlE2akhGdnZ6am9oUWtMaGQrT2QzZlQ4TVZlNFJiakdTSC9GejZTWTNVSHJ3RTJWcmRGVms3QTJ5awpBdHhhU1dFNHBFQWVlQWRoVEVaTWdaM3ZLRFJDVTBOay85OFlXSThZMlJwVDd2QXRubVZXVC92TU1ZVDdFekxFCnVzNHJiMWczNGdSQnZ1dXR0K3R2ZlZZakcxRHc5WXNwWW9BY1BhZ2Zucmt4V0h2eTAvT1ZaNjdtbkE0UG9hWmgKeEFjQzF0M3BmdElMazFmM3ZTSUZTTHNqSGlYTGd6aTRXL09VNUNsaWJ4amd6Zkt6QWdwNEtZZkRzSHRydHMvagpVOU5zWmhpaFNCSFJPS3RJSVZKZWRJdnBwdW1SSmMrTUFlK09iajNWMFJjUXAyVXQzM2JwNnhBMlZ2VUhEWm9TClM4cUhvZTZmUW85MW1YVEYwSk1NUUZVWlBOYnJLOGlFblNwaDhta3dYVWtzTXJxSHEvSnBpL2RmbmE2WTBXL1oKR1lLelR3WmNJSUpNNWxJRDY5UnZCUURTRmhqVVBPTHJkYUNxdmdhUEVYSEZHakg1MWJ0ZWFCbC9YSGRId1gwYwp0K3NPUmdiT3hrRSs1WFYybjE2aWt1TkpMK0xLMUNDUGgrYVpUUmFmNzFQSWwrVXpaNzgvdUF3UUFTR1gzT25pCnUrU045bnNpNGhLUDMrT3BwcVJWM1NtekxEalhqV0czb3dlNXJNSXgzM3ZJQmhzNDYvdHE2a05WSlNHWWFNUTQKTEllS1lLS2dJbThqRldkVFM1WFRQdFYwZjl4R0V6aDNPT05XRjQ1eS93L1d2V0VwaGJJWCtra1QrT0Z5bDFRdgpYbnZuYjkrT2FTUTJSa3VGTGdjczl1MHBENXdxSnZDVHFia0hOeXJHTGdNSXlBcWszeWsxOHgxcGVYbHE3bjY2CkdubkNPRUJUbE5WNlZCMnlvRzBOQWJXMUFiZzhnYUw4TXVhV1NHeU05b3cvVW9KSkoyRTFIR0ptNGF5UEc4aUYKZ1NxZndQLy9KY09SVks2WEgwNFlnaTJ3SzAvQjR3emdtVDM0RHh5bFpwYlBoLzY2R3BxOFVOWVBJWk5RUWVrWgpXUWlOZXhFMytjSXUwblVjU2xBU2hTdmx6bXJ3enRUZndaL1lHUEZkbkhhaG5yUjRkNEI2Mlg0bWR0Tm1lQWg4ClF4Uk5sdVNqN2p4UzZoMWpBelVnRTlFUXFaTGJ3NHpLMWRGaVhqaml5MEJLV29jTVNxRHBaa2R5ZForUE1zakgKSE92SEZnOS9ZMXVSOFVqMjdmNmlWZUZlcC9OU0N5M1FRZVFRbkdxMWovdTJhVHVuCi0tLS0tRU5EIENPTlRBSU5FUi0tLS0tCg==';
			openssl_sign(base64_decode($AccountToken), $AccountTokenSignature, openssl_pkey_get_private(PrivateKeyRaptor()), OPENSSL_ALGO_SHA1);
			$body = '<plist version="1.0">
<dict>
	<key>iphone-activation</key>
	<dict>
	<key>activation-record</key>
	<dict>
	<key>AccountTokenCertificate</key>
	<data>
	LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSURaekNDQWsrZ0F3SUJBZ0lCQWpB
	TkJna3Foa2lHOXcwQkFRVUZBREI1TVFzd0NRWURWUVFHRXdKVlV6RVQKTUJFR0ExVUVD
	aE1LUVhCd2JHVWdTVzVqTGpFbU1DUUdBMVVFQ3hNZFFYQndiR1VnUTJWeWRHbG1hV05o
	ZEdsdgpiaUJCZFhSb2IzSnBkSGt4TFRBckJnTlZCQU1USkVGd2NHeGxJR2xRYUc5dVpT
	QkRaWEowYVdacFkyRjBhVzl1CklFRjFkR2h2Y21sMGVUQWVGdzB3TnpBME1UWXlNalUx
	TURKYUZ3MHhOREEwTVRZeU1qVTFNREphTUZzeEN6QUoKQmdOVkJBWVRBbFZUTVJNd0VR
	WURWUVFLRXdwQmNIQnNaU0JKYm1NdU1SVXdFd1lEVlFRTEV3eEJjSEJzWlNCcApVR2h2
	Ym1VeElEQWVCZ05WQkFNVEYwRndjR3hsSUdsUWFHOXVaU0JCWTNScGRtRjBhVzl1TUlH
	Zk1BMEdDU3FHClNJYjNEUUVCQVFVQUE0R05BRENCaVFLQmdRREZBWHpSSW1Bcm1vaUhm
	YlMyb1BjcUFmYkV2MGQxams3R2JuWDcKKzRZVWx5SWZwcnpCVmRsbXoySkhZdjErMDRJ
	ekp0TDdjTDk3VUk3ZmswaTBPTVkwYWw4YStKUFFhNFVnNjExVApicUV0K25qQW1Ba2dl
	M0hYV0RCZEFYRDlNaGtDN1QvOW83N3pPUTFvbGk0Y1VkemxuWVdmem1XMFBkdU94dXZl
	CkFlWVk0d0lEQVFBQm80R2JNSUdZTUE0R0ExVWREd0VCL3dRRUF3SUhnREFNQmdOVkhS
	TUJBZjhFQWpBQU1CMEcKQTFVZERnUVdCQlNob05MK3Q3UnovcHNVYXEvTlBYTlBIKy9X
	bERBZkJnTlZIU01FR0RBV2dCVG5OQ291SXQ0NQpZR3UwbE01M2cyRXZNYUI4TlRBNEJn
	TlZIUjhFTVRBdk1DMmdLNkFwaGlkb2RIUndPaTh2ZDNkM0xtRndjR3hsCkxtTnZiUzlo
	Y0hCc1pXTmhMMmx3YUc5dVpTNWpjbXd3RFFZSktvWklodmNOQVFFRkJRQURnZ0VCQUY5
	cW1yVU4KZEErRlJPWUdQN3BXY1lUQUsrcEx5T2Y5ek9hRTdhZVZJODg1VjhZL0JLSGhs
	d0FvK3pFa2lPVTNGYkVQQ1M5Vgp0UzE4WkJjd0QvK2Q1WlFUTUZrbmhjVUp3ZFBxcWpu
	bTlMcVRmSC94NHB3OE9OSFJEenhIZHA5NmdPVjNBNCs4CmFia29BU2ZjWXF2SVJ5cFhu
	YnVyM2JSUmhUekFzNFZJTFM2alR5Rll5bVplU2V3dEJ1Ym1taWdvMWtDUWlaR2MKNzZj
	NWZlREF5SGIyYnpFcXR2eDNXcHJsanRTNDZRVDVDUjZZZWxpblpuaW8zMmpBelJZVHh0
	UzZyM0pzdlpEaQpKMDcrRUhjbWZHZHB4d2dPKzdidFcxcEZhcjBaakY5L2pZS0tuT1lO
	eXZDcndzemhhZmJTWXd6QUc1RUpvWEZCCjRkK3BpV0hVRGNQeHRjYz0KLS0tLS1FTkQg
	Q0VSVElGSUNBVEUtLS0tLQo=
	</data>
	<key>DeviceConfigurationFlags</key>
	<string>0</string>
	<key>DeviceCertificate</key>
	<data>
	LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSUM4ekNDQWx5Z0F3SUJBZ0lLQXZr
	dCt0SWNwUFJTUXpBTkJna3Foa2lHOXcwQkFRVUZBREJhTVFzd0NRWUQKVlFRR0V3SlZV
	ekVUTUJFR0ExVUVDaE1LUVhCd2JHVWdTVzVqTGpFVk1CTUdBMVVFQ3hNTVFYQndiR1Vn
	YVZCbwpiMjVsTVI4d0hRWURWUVFERXhaQmNIQnNaU0JwVUdodmJtVWdSR1YyYVdObElF
	TkJNQjRYRFRJd01EVXhNakl5Ck1UTXhNMW9YRFRJek1EVXhNakl5TVRNeE0xb3dnWU14
	TFRBckJnTlZCQU1XSkRsQk9Ua3dSVU14TFRRNU5ETXQKTkRRM09TMDVSalJETFRBMVFU
	QXhPVVEwT0RnNU5URUxNQWtHQTFVRUJoTUNWVk14Q3pBSkJnTlZCQWdUQWtOQgpNUkl3
	RUFZRFZRUUhFd2xEZFhCbGNuUnBibTh4RXpBUkJnTlZCQW9UQ2tGd2NHeGxJRWx1WXk0
	eER6QU5CZ05WCkJBc1RCbWxRYUc5dVpUQ0JuekFOQmdrcWhraUc5dzBCQVFFRkFBT0Jq
	UUF3Z1lrQ2dZRUF0dDhXRldUWm96SlEKYXRQcDNoRmZnT09GSi9NMTVqSjJkWGdyYkp5
	ZUwwbGRYa2VmT2NSVTZoVlE3KzhpR0tUVDY4aGQ2dUdwalJVaApxd3hxWUhuREZ0WHp6
	cjlFYTRXSEdlQ0hJME1jV2V5WGRWNHVJd1IxS3R4TTViblptKzZqTkdGMjgwdW8weHky
	ClRuT3pwMFNTM1YzWmU5dlVxTXcyN0l0MDhaZVE1MDBDQXdFQUFhT0JsVENCa2pBZkJn
	TlZIU01FR0RBV2dCU3kKL2lFalJJYVZhbm5WZ1NhT2N4RFlwMHlPZERBZEJnTlZIUTRF
	RmdRVWpSUkxnd2ppU0FUVS9ZSS9vUkFWRWs2TgpVWWt3REFZRFZSMFRBUUgvQkFJd0FE
	QU9CZ05WSFE4QkFmOEVCQU1DQmFBd0lBWURWUjBsQVFIL0JCWXdGQVlJCkt3WUJCUVVI
	QXdFR0NDc0dBUVVGQndNQ01CQUdDaXFHU0liM1kyUUdDZ0lFQWdVQU1BMEdDU3FHU0li
	M0RRRUIKQlFVQUE0R0JBT2FKVVN2WVhRb0JaeXFnSE9EeE5zSnlFWXNpc3h0QngzS2Jr
	d2FydDRPTjV5cTBuUERIdk5naAo0WDlCc3d6M2ZmMURBS3diYU9EdGlWaDErVlJyUi9u
	amJKdVNaMFFSN2xDdmVkS214MHlqWFV2aXIvdlIzQ1doCmhHUXVaMkZqb0xJQXhYV05T
	Q1N2RWZzS0VYVlRKUm4vZUNYelpIaFJmZUpqaDRMdkZNSVEKLS0tLS1FTkQgQ0VSVElG
	SUNBVEUtLS0tLQo=
	</data>
	<key>AccountToken</key>
	<data>
	'.substr(chunk_split($AccountToken, 68, "\n\t"), 0, -2).'
	</data>
	<key>AccountTokenSignature</key>
	<data>
	'.substr(chunk_split(base64_encode($AccountTokenSignature), 68, "\n\t"), 0, -2).'
	</data>
	</dict>
	<key>unbrick</key>
	<true/>
	<key>show-settings</key>
	<true/>
	</dict>
</dict>
</plist>
';
			$body1 = '<plist version="1.0">
<dict>
<key>iphone-activation</key>
<dict>
<key>unbrick</key>
<true/>
<key>LDActivationVersion</key>
<integer>2</integer>
<key>activation-record</key>
<dict>
<key>AccountTokenCertificate</key>
<data>LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSURaekNDQWsrZ0F3SUJBZ0lCQWpBTkJna3Foa2lHOXcwQkFRVUZBREI1TVFzd0NRWURWUVFHRXdKVlV6RVQKTUJFR0ExVUVDaE1LUVhCd2JHVWdTVzVqTGpFbU1DUUdBMVVFQ3hNZFFYQndiR1VnUTJWeWRHbG1hV05oZEdsdgpiaUJCZFhSb2IzSnBkSGt4TFRBckJnTlZCQU1USkVGd2NHeGxJR2xRYUc5dVpTQkRaWEowYVdacFkyRjBhVzl1CklFRjFkR2h2Y21sMGVUQWVGdzB3TnpBME1UWXlNalUxTURKYUZ3MHhOREEwTVRZeU1qVTFNREphTUZzeEN6QUoKQmdOVkJBWVRBbFZUTVJNd0VRWURWUVFLRXdwQmNIQnNaU0JKYm1NdU1SVXdFd1lEVlFRTEV3eEJjSEJzWlNCcApVR2h2Ym1VeElEQWVCZ05WQkFNVEYwRndjR3hsSUdsUWFHOXVaU0JCWTNScGRtRjBhVzl1TUlHZk1BMEdDU3FHClNJYjNEUUVCQVFVQUE0R05BRENCaVFLQmdRREZBWHpSSW1Bcm1vaUhmYlMyb1BjcUFmYkV2MGQxams3R2JuWDcKKzRZVWx5SWZwcnpCVmRsbXoySkhZdjErMDRJekp0TDdjTDk3VUk3ZmswaTBPTVkwYWw4YStKUFFhNFVnNjExVApicUV0K25qQW1Ba2dlM0hYV0RCZEFYRDlNaGtDN1QvOW83N3pPUTFvbGk0Y1VkemxuWVdmem1XMFBkdU94dXZlCkFlWVk0d0lEQVFBQm80R2JNSUdZTUE0R0ExVWREd0VCL3dRRUF3SUhnREFNQmdOVkhSTUJBZjhFQWpBQU1CMEcKQTFVZERnUVdCQlNob05MK3Q3UnovcHNVYXEvTlBYTlBIKy9XbERBZkJnTlZIU01FR0RBV2dCVG5OQ291SXQ0NQpZR3UwbE01M2cyRXZNYUI4TlRBNEJnTlZIUjhFTVRBdk1DMmdLNkFwaGlkb2RIUndPaTh2ZDNkM0xtRndjR3hsCkxtTnZiUzloY0hCc1pXTmhMMmx3YUc5dVpTNWpjbXd3RFFZSktvWklodmNOQVFFRkJRQURnZ0VCQUY5cW1yVU4KZEErRlJPWUdQN3BXY1lUQUsrcEx5T2Y5ek9hRTdhZVZJODg1VjhZL0JLSGhsd0FvK3pFa2lPVTNGYkVQQ1M5Vgp0UzE4WkJjd0QvK2Q1WlFUTUZrbmhjVUp3ZFBxcWpubTlMcVRmSC94NHB3OE9OSFJEenhIZHA5NmdPVjNBNCs4CmFia29BU2ZjWXF2SVJ5cFhuYnVyM2JSUmhUekFzNFZJTFM2alR5Rll5bVplU2V3dEJ1Ym1taWdvMWtDUWlaR2MKNzZjNWZlREF5SGIyYnpFcXR2eDNXcHJsanRTNDZRVDVDUjZZZWxpblpuaW8zMmpBelJZVHh0UzZyM0pzdlpEaQpKMDcrRUhjbWZHZHB4d2dPKzdidFcxcEZhcjBaakY5L2pZS0tuT1lOeXZDcndzemhhZmJTWXd6QUc1RUpvWEZCCjRkK3BpV0hVRGNQeHRjYz0KLS0tLS1FTkQgQ0VSVElGSUNBVEUtLS0tLQo=</data>
<key>DeviceCertificate</key>
<data>LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSUM4ekNDQWx5Z0F3SUJBZ0lLQkFNaFRlL1lscmdIekRBTkJna3Foa2lHOXcwQkFRVUZBREJhTVFzd0NRWUQKVlFRR0V3SlZVekVUTUJFR0ExVUVDaE1LUVhCd2JHVWdTVzVqTGpFVk1CTUdBMVVFQ3hNTVFYQndiR1VnYVZCbwpiMjVsTVI4d0hRWURWUVFERXhaQmNIQnNaU0JwVUdodmJtVWdSR1YyYVdObElFTkJNQjRYRFRJd01EVXhOekEwCk1EYzBPVm9YRFRJek1EVXhOekEwTURjME9Wb3dnWU14TFRBckJnTlZCQU1XSkRFMU1UVXlRVFl4TFVFek4wSXQKTkRsRFFTMDRNekF5TFRrek1UWkRSVFUwTURsQ016RUxNQWtHQTFVRUJoTUNWVk14Q3pBSkJnTlZCQWdUQWtOQgpNUkl3RUFZRFZRUUhFd2xEZFhCbGNuUnBibTh4RXpBUkJnTlZCQW9UQ2tGd2NHeGxJRWx1WXk0eER6QU5CZ05WCkJBc1RCbWxRYUc5dVpUQ0JuekFOQmdrcWhraUc5dzBCQVFFRkFBT0JqUUF3Z1lrQ2dZRUF1RXFIOFR4OEJjMGkKQzhKaVl4cWZ3Z0w0RnZUL3VkSXljMmMxTmpYOXQ3Rm43QUYvVktoVkVMd1o3NU9QTWZDbG9TYkxzZGJoWWo1bAp6Z1o0eWY3KytMa3cwb2tMUG91dFpXVjlyV3NZekFKS3R6bUlNbmpiVEt1RlZNQ01PZlU1THFKUXZveGZLTjgzCldWREhSencxeVZPZk5TSDVCb0NWbkNFWXRwMFV2MkVDQXdFQUFhT0JsVENCa2pBZkJnTlZIU01FR0RBV2dCU3kKL2lFalJJYVZhbm5WZ1NhT2N4RFlwMHlPZERBZEJnTlZIUTRFRmdRVWZYKzhnRmZDUXV0R0ZWWkpYUXk5dmZtSgpsd0V3REFZRFZSMFRBUUgvQkFJd0FEQU9CZ05WSFE4QkFmOEVCQU1DQmFBd0lBWURWUjBsQVFIL0JCWXdGQVlJCkt3WUJCUVVIQXdFR0NDc0dBUVVGQndNQ01CQUdDaXFHU0liM1kyUUdDZ0lFQWdVQU1BMEdDU3FHU0liM0RRRUIKQlFVQUE0R0JBRnNyTGxIQ00rK2p2OE0xQm9EVk13ZEVRdGZjaklLV0NmQXpiTzd5em9SSFNwL3duNGZrWmRaVgpsT2VIcWZ3aEZ2ZE5FK2FFNm1nS1dFaEhQa2R5MW0yWGY4WUplTjJGSUhhSDlYVTdjQ0lxVUY2K3k2VTFJcG9zCnpvYmVLY1poZzJVQlBpd1RENENMWHlGZ2NGZFZjbDhNdkV0bEdqb05iV3VVbEJFRzBnRkIKLS0tLS1FTkQgQ0VSVElGSUNBVEUtLS0tLQo=</data>
<key>FairPlayKeyData</key>
<data>LS0tLS1CRUdJTiBDT05UQUlORVItLS0tLQpBQUVBQVFzb3l5THJnUFNiUGprdGVkSWMxQ0JTNnRiN1ZFN2lCaHdsWHVCVEtCamNpKzM3TmMzZXdWUmt4aGJZCmUySTBWSjZoZEJpZWNmUHNNRHA3K0FzMTB5dXV0ZnBoOHlTbjZvWldoVDF6ZEVjRDFvQy93SC95cHVON2FIdzIKaFJ4QWNjbDVaN2Z0QXBtOFdUUktwR3JNeldIRzVZcGhpZGh2MUNYSE4wVkM1SE5JQzBVUE1aMytadFBRaHNpTApFUjF4NThpYW10YnpmT3AxcExISTNBQXBaMURKaHdyVTYzWmh5d0xHQmYrSTdCN0VhUVJWNjBVWm0vamlUL0pwCmRJT00rY1RLSzdoTFhiNWNrU2RFelRLOW5CaW5UVHlnMVBMcGdUa3paZXAzODZRRzN4NWhUTk1PcmJMOGhQOS8KZk5Ddk1qYW5SUmRVcjBiRlNYb2djNHk1Z3R0UXYzS2U1aFU0bnNUTUFLL0oyZWFFZ21WUUJjZmdLMm01em1QNQo2TCtjRmxRRU8rTm9yKzZmRWt2c3YvckxnTThWM2c5czRqMVRjK2FSMkVYdldPK3I5bW1neUplanBuZFVhNGppCkNCK3N6VEZaWDhuU29mWFVpbDhCK3Q3bklheU8rUHRYWklGQlVkWDR1ZnpyN1gzelZyRFlVMDJuT0Q3WFBGQnUKR0tTYmhBUmZyV2pzVDNKUEFFUUJGODFBYTNMVlI3Zlp0cjBWMWRjelp2aUtMSUV1Zy9RQXhKOUdzZ2s0Q1VRTQpmbDMrNExHTWM3TVZCdVlGd3lRWS83VytLVVJZUmxSL2tJS00xNGllamhQSmcxWlpZYW1qMG5iQ29EV3V6ZlVjCnV5WlFnRTNkUGdBT2hxQTROM3FTeDNTYzdrN3Zwam9PUFg2ekFka2dxaXVicWJGVWZCQlN6aHVZSmp5aFFyalIKWllZUHRYWmRtbjl4dlRseVF0a1ZqWVRwMjVGa3M4RWdZOW13Smdrem9HdnhndTNUWC9xVS9pUWRwU1NONms2dAo0L2cyV2hiZTFkRkdkVi9yWU9sTHRSaEIyYjlDZ1dvZThkT2VOb0FCcmRxcWloa1JZT2pEQlg3RnhCazF1dmZSCnRDbnVHeTFUeXljRllOWW0xZlVmWTV2SXAxa2J1YTF3M29md0l1OUxXNm5HRjVTRzZPQmVDVktaUGN6eGdPbVEKMGJYNmZoaU90ZmpRbzdhQkdhWmlxL1ZmbzFpNzlOWmRKNDBqdGRlQVB5S0tCemZ3OTNUczFvTVN4Qkh1clN3dgpiQnZnTkpuc2JEOG1DRGxWWmxKelVwczYxblY3SWI0U0pna29TcSswaUV0UWZvT3B4TnR5TlY2MlJDcHVNYU9nCldRQk1RWDlNY2NzK2RQWENvZUZSNVJ5SUJBa3EvT2lleUczUW9xT2NZYWY3ZnZHcy9pTjVaaVlMWmpWbmR0b2MKY1F3d29xaEZTVGREbUxPd1B5ZDNFYUkwUWw3V3NlM3BwemJsNXk1dmt3czJ4RnlHVEtkdzBmMHN0WW5RSzJxRgpFUW9ucUZzeFJkbC9HRjBoWHZYQVk4OGZ2enBwcGRyWERLU3NLN3VuUTJOWXZ2a0JNYmZncWh5TXNocThtQi8yCkVwSnVEcitwSU5VcG9rSjdlWFpVcjJIclE5SUVmZmlvalVHMVQrbS93UFpIbjNBN1p0UVpURjYzVkRqOFRzdVMKTG43YWw4ajhUeEk3K05TRzA0NzZUam02RzhCSys3b2N3SGJiTmsxSTI5RmxEcHljRmVqZ2ZneTR3VlNYUXdKZgo4cGtiSnpSa2tTRjZBSm9qSjZkSTd0RzhOd0lTNlQ5dVFTTkdQZzZ6OSt4VnY4RjhWRHNkVUxBMkRyc3h5cmsyClFBYjRTaU9sbDJHcENBWXgrOWpod1Rzd0RxV2lxbDE2OVZXYjllYVRRcEJnK2l4QVVKSlFkUU0vOTVsRUloNVkKQnl0TXk0VVV3Mjdic2FiYVZVNWxQdU40dGNzTHZRa21EMVBDREhYTDdzM3JlQjdPCi0tLS0tRU5EIENPTlRBSU5FUi0tLS0tCg==</data>
<key>AccountToken</key>
<data>'.$AccountToken.'</data>
<key>AccountTokenSignature</key>
<data>'.base64_encode($AccountTokenSignature).'</data>
</dict>
</dict>
</dict>
</plist>
';


$body2 = '<plist version="1.0">
<dict>
<key>ActivationRecord</key>
<dict>
<key>unbrick</key>
<true></true>
<key>AccountTokenCertificate</key>
<data>LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSURaekNDQWsrZ0F3SUJBZ0lCQWpBTkJna3Foa2lHOXcwQkFRVUZBREI1TVFzd0NRWURWUVFHRXdKVlV6RVQKTUJFR0ExVUVDaE1LUVhCd2JHVWdTVzVqTGpFbU1DUUdBMVVFQ3hNZFFYQndiR1VnUTJWeWRHbG1hV05oZEdsdgpiaUJCZFhSb2IzSnBkSGt4TFRBckJnTlZCQU1USkVGd2NHeGxJR2xRYUc5dVpTQkRaWEowYVdacFkyRjBhVzl1CklFRjFkR2h2Y21sMGVUQWVGdzB3TnpBME1UWXlNalUxTURKYUZ3MHhOREEwTVRZeU1qVTFNREphTUZzeEN6QUoKQmdOVkJBWVRBbFZUTVJNd0VRWURWUVFLRXdwQmNIQnNaU0JKYm1NdU1SVXdFd1lEVlFRTEV3eEJjSEJzWlNCcApVR2h2Ym1VeElEQWVCZ05WQkFNVEYwRndjR3hsSUdsUWFHOXVaU0JCWTNScGRtRjBhVzl1TUlHZk1BMEdDU3FHClNJYjNEUUVCQVFVQUE0R05BRENCaVFLQmdRREZBWHpSSW1Bcm1vaUhmYlMyb1BjcUFmYkV2MGQxams3R2JuWDcKKzRZVWx5SWZwcnpCVmRsbXoySkhZdjErMDRJekp0TDdjTDk3VUk3ZmswaTBPTVkwYWw4YStKUFFhNFVnNjExVApicUV0K25qQW1Ba2dlM0hYV0RCZEFYRDlNaGtDN1QvOW83N3pPUTFvbGk0Y1VkemxuWVdmem1XMFBkdU94dXZlCkFlWVk0d0lEQVFBQm80R2JNSUdZTUE0R0ExVWREd0VCL3dRRUF3SUhnREFNQmdOVkhSTUJBZjhFQWpBQU1CMEcKQTFVZERnUVdCQlNob05MK3Q3UnovcHNVYXEvTlBYTlBIKy9XbERBZkJnTlZIU01FR0RBV2dCVG5OQ291SXQ0NQpZR3UwbE01M2cyRXZNYUI4TlRBNEJnTlZIUjhFTVRBdk1DMmdLNkFwaGlkb2RIUndPaTh2ZDNkM0xtRndjR3hsCkxtTnZiUzloY0hCc1pXTmhMMmx3YUc5dVpTNWpjbXd3RFFZSktvWklodmNOQVFFRkJRQURnZ0VCQUY5cW1yVU4KZEErRlJPWUdQN3BXY1lUQUsrcEx5T2Y5ek9hRTdhZVZJODg1VjhZL0JLSGhsd0FvK3pFa2lPVTNGYkVQQ1M5Vgp0UzE4WkJjd0QvK2Q1WlFUTUZrbmhjVUp3ZFBxcWpubTlMcVRmSC94NHB3OE9OSFJEenhIZHA5NmdPVjNBNCs4CmFia29BU2ZjWXF2SVJ5cFhuYnVyM2JSUmhUekFzNFZJTFM2alR5Rll5bVplU2V3dEJ1Ym1taWdvMWtDUWlaR2MKNzZjNWZlREF5SGIyYnpFcXR2eDNXcHJsanRTNDZRVDVDUjZZZWxpblpuaW8zMmpBelJZVHh0UzZyM0pzdlpEaQpKMDcrRUhjbWZHZHB4d2dPKzdidFcxcEZhcjBaakY5L2pZS0tuT1lOeXZDcndzemhhZmJTWXd6QUc1RUpvWEZCCjRkK3BpV0hVRGNQeHRjYz0KLS0tLS1FTkQgQ0VSVElGSUNBVEUtLS0tLQo=</data>
<key>DeviceCertificate</key>
<data>LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSUM4ekNDQWx5Z0F3SUJBZ0lLQTgvblZRazE1YkJLSFRBTkJna3Foa2lHOXcwQkFRVUZBREJhTVFzd0NRWUQKVlFRR0V3SlZVekVUTUJFR0ExVUVDaE1LUVhCd2JHVWdTVzVqTGpFVk1CTUdBMVVFQ3hNTVFYQndiR1VnYVZCbwpiMjVsTVI4d0hRWURWUVFERXhaQmNIQnNaU0JwVUdodmJtVWdSR1YyYVdObElFTkJNQjRYRFRJd01EVXhOekF6Ck1qY3hOMW9YRFRJek1EVXhOekF6TWpjeE4xb3dnWU14TFRBckJnTlZCQU1XSkVRd016UXlOVEExTFRReE5ETXQKTkRWRlJpMDVOamRGTFRFd05EUkRSREl6T0RBelJERUxNQWtHQTFVRUJoTUNWVk14Q3pBSkJnTlZCQWdUQWtOQgpNUkl3RUFZRFZRUUhFd2xEZFhCbGNuUnBibTh4RXpBUkJnTlZCQW9UQ2tGd2NHeGxJRWx1WXk0eER6QU5CZ05WCkJBc1RCbWxRYUc5dVpUQ0JuekFOQmdrcWhraUc5dzBCQVFFRkFBT0JqUUF3Z1lrQ2dZRUF5bSt5Mnd2MmtLaTMKc1RmMjRJbnloMDVYQ1pRNytwT0tycUNja3ZMSUlzNEozdy9ZN2liM2FIeUg3VTJhMnJzN0QySFhGa0NnMlhuVwp2c0JqdGpzN3AzQTJUK0tCZklwN3k2QlpRaEFQY0lYd1A3ZUtxcjlhcEtsajZIL1MxRW50ck5TTjZmL293QlpHCkZiMmRFTE8yc0Z5UzZPN2k4UFZvKzk3SmtlV2hYMGtDQXdFQUFhT0JsVENCa2pBZkJnTlZIU01FR0RBV2dCU3kKL2lFalJJYVZhbm5WZ1NhT2N4RFlwMHlPZERBZEJnTlZIUTRFRmdRVW5DWE1wSjhXNGRhdDZhakFnVitRL2N1awpVaTB3REFZRFZSMFRBUUgvQkFJd0FEQU9CZ05WSFE4QkFmOEVCQU1DQmFBd0lBWURWUjBsQVFIL0JCWXdGQVlJCkt3WUJCUVVIQXdFR0NDc0dBUVVGQndNQ01CQUdDaXFHU0liM1kyUUdDZ0lFQWdVQU1BMEdDU3FHU0liM0RRRUIKQlFVQUE0R0JBTE0yYS8yQU1MUmdLVDd3VWlsVEpMdEJYOGk5aDZhVGN2NzJaZC95Ulp2SUlOOWEzVm5mVjc0bApDRXh4S1Btdi9SU3g3bkppYkYwYjFKTU1uc3ppN05Memp5SGtIeDQ0VWNyYVJabUZYVjJHWjBVWXorVzRMQ1hQCndDODlCK242N25ad291b2N5WHNlY3o3VmduelhaRjVFVTBhTHZRamM0Yyt1UEMyTERUZnkKLS0tLS1FTkQgQ0VSVElGSUNBVEUtLS0tLQo=</data>
<key>AccountToken</key>
<data>'.$AccountToken.'</data>
<key>AccountTokenSignature</key>
<data>'.base64_encode($AccountTokenSignature).'</data>
<key>UniqueDeviceCertificate</key>
<data>LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSURBRENDQXFpZ0F3SUJBZ0lHQVc0Vk1VQjBNQW9HQ0NxR1NNNDlCQU1DTUVVeEV6QVJCZ05WQkFnTUNrTmhiR2xtYjNKdWFXRXhFekFSQmdOVkJBb01Da0Z3Y0d4bElFbHVZeTR4R1RBWEJnTlZCQU1NRUVaRVVrUkRMVlZEVWxRdFUxVkNRMEV3SGhjTk1qQXdOVEUzTURNeU56RTNXaGNOTWpFd05URTNNRE15TnpFM1dqQnZNUk13RVFZRFZRUUlEQXBEWVd4cFptOXlibWxoTVJNd0VRWURWUVFLREFwQmNIQnNaU0JKYm1NdU1SNHdIQVlEVlFRTERCVjFZM0owSUV4bFlXWWdRMlZ5ZEdsbWFXTmhkR1V4SXpBaEJnTlZCQU1NR2pBd01EQTRNREV3TFRBd01ERkZNVEV5UXpFNE5EWTRNekkyTUZrd0V3WUhLb1pJemowQ0FRWUlLb1pJemowREFRY0RRZ0FFR2Q0aEF3M2x1NHZPWUFkcy9NMjhnWjEwdTR6MWI5MVZPenJBT05hNGxreFEyVUlDOXZsUUV4azF4YWQ1NTZONjNkT2FCRmZrazRJVXFBcTRUci9mcmFPQ0FWZ3dnZ0ZVTUF3R0ExVWRFd0VCL3dRQ01BQXdEZ1lEVlIwUEFRSC9CQVFEQWdUd01JSDNCZ2txaGtpRzkyTmtDZ0VFZ2VreGdlYi9oSnFoa2xBTk1Bc1dCRU5JU1ZBQ0F3Q0FFUCtFcW8yU1JCRXdEeFlFUlVOSlJBSUhIaEVzR0VhREp2K0drN1hDWXhzd0dSWUVZbTFoWXdRUk9UZzZNVEE2WlRnNllUQTZOVE02T0dYL2hzdTF5bWtaTUJjV0JHbHRaV2tFRHpNMU5UZ3lOakE0TWpBNE9EVXlOditIbThuY2JSWXdGQllFYzNKdWJRUU1SamN4VkRrMlVsRklSelpYLzRlcmtkSmtNakF3RmdSMVpHbGtCQ2cxWVdReE5ERmxaREZoWVdGaFpEZzBZV0l3TVRBME56YzVZelV6WVdNMU9EYzNZVFJtWkdJMC80ZTd0Y0pqR3pBWkZnUjNiV0ZqQkJFNU9Eb3hNRHBsT0RwaE1EbzFNem8xTmpBU0Jna3Foa2lHOTJOa0NnSUVCVEFEQWdFQU1DWUdDU3FHU0liM1kyUUlCd1FaTUJlL2luZ0lCQVl4TXk0ekxqRy9pbnNIQkFVeE4wUTFNREFLQmdncWhrak9QUVFEQWdOR0FEQkZBaUVBbEpYRnJ2NnFrNWFWbGtvRENhS1BEK0Q4RTNRR3pFUURuUThQdld1Mm1VQUNJQXR2emZLZERZT29oRUFsZkpkbW1KQ0Jzd21td3YyRWRkeTc1ZEVrVXc9PQotLS0tLUVORCBDRVJUSUZJQ0FURS0tLS0tdWtNdEg5UmRTUXZIekJ4N0ZpQkdyNy9LY21seFgvWHdvV2VXbldiNklSTT0KLS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSUNGekNDQVp5Z0F3SUJBZ0lJT2NVcVE4SUMvaHN3Q2dZSUtvWkl6ajBFQXdJd1FERVVNQklHQTFVRUF3d0wKVTBWUUlGSnZiM1FnUTBFeEV6QVJCZ05WQkFvTUNrRndjR3hsSUVsdVl5NHhFekFSQmdOVkJBZ01Da05oYkdsbQpiM0p1YVdFd0hoY05NVFl3TkRJMU1qTTBOVFEzV2hjTk1qa3dOakkwTWpFME16STBXakJGTVJNd0VRWURWUVFJCkRBcERZV3hwWm05eWJtbGhNUk13RVFZRFZRUUtEQXBCY0hCc1pTQkpibU11TVJrd0Z3WURWUVFEREJCR1JGSkUKUXkxVlExSlVMVk5WUWtOQk1Ga3dFd1lIS29aSXpqMENBUVlJS29aSXpqMERBUWNEUWdBRWFEYzJPL01ydVl2UApWUGFVYktSN1JSem42NkIxNC84S29VTXNFRGI3bkhrR0VNWDZlQyswZ1N0R0hlNEhZTXJMeVdjYXAxdERGWW1FCkR5a0dRM3VNMmFON01Ia3dIUVlEVlIwT0JCWUVGTFNxT2tPdEcrVit6Z29NT0JxMTBobkxsVFd6TUE4R0ExVWQKRXdFQi93UUZNQU1CQWY4d0h3WURWUjBqQkJnd0ZvQVVXTy9XdnNXQ3NGVE5HS2FFcmFMMmUzczZmODh3RGdZRApWUjBQQVFIL0JBUURBZ0VHTUJZR0NTcUdTSWIzWTJRR0xBRUIvd1FHRmdSMVkzSjBNQW9HQ0NxR1NNNDlCQU1DCkEya0FNR1lDTVFEZjV6TmlpS04vSnFtczF3KzNDRFlrRVNPUGllSk1wRWtMZTlhMFVqV1hFQkRMMFZFc3EvQ2QKRTNhS1hrYzZSMTBDTVFEUzRNaVdpeW1ZK1J4a3Z5L2hpY0REUXFJL0JMK04zTEhxekpaVXV3MlN4MGFmRFg3Qgo2THlLaytzTHE0dXJrTVk9Ci0tLS0tRU5EIENFUlRJRklDQVRFLS0tLS0K</data>
<key>show-settings</key>
<true></true>
</dict>
</dict>
</plist>
';

		}
		file_put_contents('./var/www/requests/'.$data['SerialNumber'].'-'.$data['DeviceClass'].'-'.$data['RegionCode'].'-'.$data['ProductVersion'].'-Token.txt', "After: ".$AccountToken."\n", FILE_APPEND);
//		$final = str_replace("<key>FairPlayKeyData</key><data>".$dataGet['FairPlayKeyData']."</data>", '', $final);
//		var_dump(shell_exec("curl --data 'activation-info-base64=".urlencode(base64_encode($newReq))."' -kv https://albert.apple.com/deviceservices/deviceActivation"));
//		<key>DeviceConfigurationFlags</key>
//		<string>0</string>
	}
	$data = ActivationRequestInfoXML($rawactpost, 1);
	file_put_contents('./var/www/requests/'.$data['SerialNumber'].'-'.$data['DeviceClass'].'-'.$data['RegionCode'].'-'.$data['ProductVersion'].'-'.$data['RegulatoryModelNumber'].'.txt', json_encode($data));

	if (isset($_POST['saveRequestTicket'])) {
		file_put_contents('./var/www/requests/'.$_POST['saveRequestTicket'].'.html', $body);
		echo "Device Request Ticket Saved on server";
	} elseif (isset($_POST['saveRequestTicketWindows'])) {
		file_put_contents('./var/www/requests/'.$_POST['saveRequestTicketWindows'].'.html', $body);
		echo "Device Request Ticket Saved on server";
	} elseif (isset($_GET['saveRequestTicket'])) {
		file_put_contents('./var/www/requests/'.$_GET['saveRequestTicket'].'.html', $body);
		file_put_contents('./var/www/requests/'.$_GET['saveRequestTicket'].'-ARS.txt', $ARS);
		file_put_contents('./var/www/requests/'.$_GET['saveRequestTicket'].'-CSR.txt', $ARS."\n\nINN:\n".$data['AccountTokenCertificate']."\n\n".$data['AccountTokenSignature']."\n\nOUT:\n".AccountTokenCertificateTest()."\n\n".$AccountTokenSignature."\n\n".json_encode(getallheaders()));		
	}
	if(strpos($_SERVER['HTTP_USER_AGENT'], 'iOS Device Activator') !== false) {
		if(strpos($body, 'Activation Lock') !== false) {
//			header("Content-Type: application/x-buddyml");
			header("Content-Type: text/xml");

//		$body = $rawactpost; // $_POST['activation-info'];
		} else {
			header("Content-Type: application/xml");
//			header("Content-Type: text/xml");
		}
	}

//	$body = str_replace(array("\n","\t"), array("",""), $body);
//	$body = '<plist version="1.0"><dict><key>iphone-activation</key><dict><key>activation-record</key><dict><key>FairPlayKeyData</key><data>LS0tLS1CRUdJTiBDT05UQUlORVItLS0tLQpBQUVBQWNvQkpGVDdsbGVLL01aMUpZZDY0RmhqN2tPV1ZyMDcycXdIYURqZFFsdCtNZUNDUVNKeWVwWktyZGhGCmdXNWJMUStvUjZXamk4VUc5c1JvZUZTOHJsRGRLeVNzOVZkc1BYRUlQdHVLOWJQenZuWEtCTXBLSC9LNXFNSXYKVFN6WEJweTRGY2R6dDVUMGNKRzQ3d0liNlZSYjNLT2dEQmpRR0VGM3luMDdvUmVQc2pGN0twNzh0c0wrcjRsRwo0eTBHSkVBSlY5cGh1R1g1WGpkYml3dmZ5WUcyc1o1Z3FTMlpPYlZIVnEydlo0czdzcExtREE5MDVuRis2ZXFOCkN2RlUrUmhaQlBqb1prVkNLSG9qUE44L3hyaVMwQnpDOUJiakRHcnN3dkJJcE9ndWhNT28xeThsQWpqelZJTEcKaWdMVi9oMUlyaXYzYXBmN2hVVVRURUtPeUxiVkVlYjFIM0s0b0d2WXN1ZHRBei8yTTc4dnVmTHFINlh1ODl1eApwZGgrdmtWejk2NjkxaFJJTjlCdnBiQklYT3Q5enMyckk1TjJiMENoQzkwZ2d2dDJMRlFUOXRVQm13dGhLakdGCitNSHBCaFBFSVFlOGJ0bGxUcEFnNTBraWo0alBwdW5KN0pKTmVQWE1HcnZMOVI3SlEyZEgvTDBpR3gxdG96U2IKVTJmTGdJcFJQeDBnZEtKRmVlaFQwMy8zSWh5aXRIZXcvWExxSTBxRnU1bDBwaEZ5L2ZqWEVLQkQvaG5kUGgvZgozMjZ6bWg1WVVqL2o1MnFZMWpiM3cxOUw3ZzNYMjdHTGNBNGx5U0tPTmFQUnBwbVVMbVpzV1IzN1ovb0dnL20vCmNmMGlEb3AxR0VHYjRnUVNEUXRLR3pLL2FaZmZSTFd0b0ZMWlp3RngvOWdYNnc1eFRwL1Y4YUtvK2RQTDh1a3EKOWRHanJJOTVOM1VGbG1qOUw5VEo1TFZOUWZQR0dhaUtVNi9MWEN0SktyNjJNdkhISm0vTTFpV01HVzdCalAyWAo2eTU5RFpnMUxCM05hY2tFNHJsQi9icmVneURrcXgxZjhUUURPaVBZTE9ldjcvaXdoaEJkYjdBZjA2VGhhTm50CnRuZFYydEdSTnJ4a1ZZd2NkTngwL0dSN1ZjMmUrdytRTHRsZHJmdzA0bW1QejRjRTROek5SNkdVV0VuL1dja0cKUjc1U1RYVE9LOUJaVmM3TytBTTdNUXIwTytDQnFyVVNkNnVUdzVBVTJ3c1duL3V1N2JPUUE3L0NZaXN1N1p3SAppS0RoNVVSSFU2Tmc0WlY2YWR1cjEzSDR5RUxWU2hkcUR5emplNjhrSkdWTzZBbWoxckV6alNTRTJEVjVnTWcrCmJCWTVSWVpxNHU1NG11TjdrUTZLU2sxS04yN3F0bHAvNHVsYlg5VVFzVHhtN21WU0RqellvVmlGTG1COGU5L1QKN3N1RXVjM3RySWJsTTNjdzFkMFBhS1RlYUlVcGRkTFhoZkpXVnhxTDhXU2hsTi9yVW1ibFQ5ZXlTeC9uYmYzUgppZWE5cVIxYyttV0c1ZzFrd3A5N0p5aTBWZHJUdi9QQ2p6Z01xUkNEd1RPc051elArZWk3dGxpNEVmSHJtdWx0CnRUeDZkb1FOelBnbkVWL284TUxPVTBwc0pKeU83VU9QM2RvUXh5czJQNG53V0JaS0Joc3dmbjBXanU1T2pWSU4KRyt2U2hUTy9jYktpWm1ManVGOGFRMVpyNC80WHIzZTAwNFF1Y1F3NnVaRHRhUDRNZlVSREJSMnVaOWdTZy9mTgpNVVFpcGdUeGZCUEt4K0o2eVFHcDVmQ1VEc3RCMDlSNUtDK1Z0cGxucm5pYTY1TVd2NHRQM1UrNDh0NENCSE0wClNnSkR3NWRtTHBsVjJHVFUyQ1psazJBTGlobXhONUdleE1nVVNrcjQ2dFNkWmVzbENaNUVGa3JXOTJlV3NZbmsKK1NmWkxPMEpiYnJic3FQSFVubUZuQVhHM1o3VmhvYi9WTHNkZGRCei9EQjhySmFLCi0tLS0tRU5EIENPTlRBSU5FUi0tLS0tCg==</data><key>AccountTokenCertificate</key><data>LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSURaekNDQWsrZ0F3SUJBZ0lCQWpBTkJna3Foa2lHOXcwQkFRVUZBREI1TVFzd0NRWURWUVFHRXdKVlV6RVQKTUJFR0ExVUVDaE1LUVhCd2JHVWdTVzVqTGpFbU1DUUdBMVVFQ3hNZFFYQndiR1VnUTJWeWRHbG1hV05oZEdsdgpiaUJCZFhSb2IzSnBkSGt4TFRBckJnTlZCQU1USkVGd2NHeGxJR2xRYUc5dVpTQkRaWEowYVdacFkyRjBhVzl1CklFRjFkR2h2Y21sMGVUQWVGdzB3TnpBME1UWXlNalUxTURKYUZ3MHhOREEwTVRZeU1qVTFNREphTUZzeEN6QUoKQmdOVkJBWVRBbFZUTVJNd0VRWURWUVFLRXdwQmNIQnNaU0JKYm1NdU1SVXdFd1lEVlFRTEV3eEJjSEJzWlNCcApVR2h2Ym1VeElEQWVCZ05WQkFNVEYwRndjR3hsSUdsUWFHOXVaU0JCWTNScGRtRjBhVzl1TUlHZk1BMEdDU3FHClNJYjNEUUVCQVFVQUE0R05BRENCaVFLQmdRREZBWHpSSW1Bcm1vaUhmYlMyb1BjcUFmYkV2MGQxams3R2JuWDcKKzRZVWx5SWZwcnpCVmRsbXoySkhZdjErMDRJekp0TDdjTDk3VUk3ZmswaTBPTVkwYWw4YStKUFFhNFVnNjExVApicUV0K25qQW1Ba2dlM0hYV0RCZEFYRDlNaGtDN1QvOW83N3pPUTFvbGk0Y1VkemxuWVdmem1XMFBkdU94dXZlCkFlWVk0d0lEQVFBQm80R2JNSUdZTUE0R0ExVWREd0VCL3dRRUF3SUhnREFNQmdOVkhSTUJBZjhFQWpBQU1CMEcKQTFVZERnUVdCQlNob05MK3Q3UnovcHNVYXEvTlBYTlBIKy9XbERBZkJnTlZIU01FR0RBV2dCVG5OQ291SXQ0NQpZR3UwbE01M2cyRXZNYUI4TlRBNEJnTlZIUjhFTVRBdk1DMmdLNkFwaGlkb2RIUndPaTh2ZDNkM0xtRndjR3hsCkxtTnZiUzloY0hCc1pXTmhMMmx3YUc5dVpTNWpjbXd3RFFZSktvWklodmNOQVFFRkJRQURnZ0VCQUY5cW1yVU4KZEErRlJPWUdQN3BXY1lUQUsrcEx5T2Y5ek9hRTdhZVZJODg1VjhZL0JLSGhsd0FvK3pFa2lPVTNGYkVQQ1M5Vgp0UzE4WkJjd0QvK2Q1WlFUTUZrbmhjVUp3ZFBxcWpubTlMcVRmSC94NHB3OE9OSFJEenhIZHA5NmdPVjNBNCs4CmFia29BU2ZjWXF2SVJ5cFhuYnVyM2JSUmhUekFzNFZJTFM2alR5Rll5bVplU2V3dEJ1Ym1taWdvMWtDUWlaR2MKNzZjNWZlREF5SGIyYnpFcXR2eDNXcHJsanRTNDZRVDVDUjZZZWxpblpuaW8zMmpBelJZVHh0UzZyM0pzdlpEaQpKMDcrRUhjbWZHZHB4d2dPKzdidFcxcEZhcjBaakY5L2pZS0tuT1lOeXZDcndzemhhZmJTWXd6QUc1RUpvWEZCCjRkK3BpV0hVRGNQeHRjYz0KLS0tLS1FTkQgQ0VSVElGSUNBVEUtLS0tLQo=</data><key>DeviceConfigurationFlags</key><string>0</string><key>DeviceCertificate</key><data>LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSUM4ekNDQWx5Z0F3SUJBZ0lLQXZrdCt0SWNwUFJTUXpBTkJna3Foa2lHOXcwQkFRVUZBREJhTVFzd0NRWUQKVlFRR0V3SlZVekVUTUJFR0ExVUVDaE1LUVhCd2JHVWdTVzVqTGpFVk1CTUdBMVVFQ3hNTVFYQndiR1VnYVZCbwpiMjVsTVI4d0hRWURWUVFERXhaQmNIQnNaU0JwVUdodmJtVWdSR1YyYVdObElFTkJNQjRYRFRJd01EVXhNakl5Ck1UTXhNMW9YRFRJek1EVXhNakl5TVRNeE0xb3dnWU14TFRBckJnTlZCQU1XSkRsQk9Ua3dSVU14TFRRNU5ETXQKTkRRM09TMDVSalJETFRBMVFUQXhPVVEwT0RnNU5URUxNQWtHQTFVRUJoTUNWVk14Q3pBSkJnTlZCQWdUQWtOQgpNUkl3RUFZRFZRUUhFd2xEZFhCbGNuUnBibTh4RXpBUkJnTlZCQW9UQ2tGd2NHeGxJRWx1WXk0eER6QU5CZ05WCkJBc1RCbWxRYUc5dVpUQ0JuekFOQmdrcWhraUc5dzBCQVFFRkFBT0JqUUF3Z1lrQ2dZRUF0dDhXRldUWm96SlEKYXRQcDNoRmZnT09GSi9NMTVqSjJkWGdyYkp5ZUwwbGRYa2VmT2NSVTZoVlE3KzhpR0tUVDY4aGQ2dUdwalJVaApxd3hxWUhuREZ0WHp6cjlFYTRXSEdlQ0hJME1jV2V5WGRWNHVJd1IxS3R4TTViblptKzZqTkdGMjgwdW8weHkyClRuT3pwMFNTM1YzWmU5dlVxTXcyN0l0MDhaZVE1MDBDQXdFQUFhT0JsVENCa2pBZkJnTlZIU01FR0RBV2dCU3kKL2lFalJJYVZhbm5WZ1NhT2N4RFlwMHlPZERBZEJnTlZIUTRFRmdRVWpSUkxnd2ppU0FUVS9ZSS9vUkFWRWs2TgpVWWt3REFZRFZSMFRBUUgvQkFJd0FEQU9CZ05WSFE4QkFmOEVCQU1DQmFBd0lBWURWUjBsQVFIL0JCWXdGQVlJCkt3WUJCUVVIQXdFR0NDc0dBUVVGQndNQ01CQUdDaXFHU0liM1kyUUdDZ0lFQWdVQU1BMEdDU3FHU0liM0RRRUIKQlFVQUE0R0JBT2FKVVN2WVhRb0JaeXFnSE9EeE5zSnlFWXNpc3h0QngzS2Jrd2FydDRPTjV5cTBuUERIdk5naAo0WDlCc3d6M2ZmMURBS3diYU9EdGlWaDErVlJyUi9uamJKdVNaMFFSN2xDdmVkS214MHlqWFV2aXIvdlIzQ1doCmhHUXVaMkZqb0xJQXhYV05TQ1N2RWZzS0VYVlRKUm4vZUNYelpIaFJmZUpqaDRMdkZNSVEKLS0tLS1FTkQgQ0VSVElGSUNBVEUtLS0tLQo=</data><key>AccountToken</key><data>ewoJIkludGVybmF0aW9uYWxNb2JpbGVFcXVpcG1lbnRJZGVudGl0eSIgPSAiMzUxOTg2MDY2ODg4NjU3IjsKCSJTZXJpYWxOdW1iZXIiID0gIkROUE1RMFU5RkZHRCI7CgkiSW50ZXJuYXRpb25hbE1vYmlsZVN1YnNjcmliZXJJZGVudGl0eSIgPSAiMjQyMDEzNDQxOTc2MzQ2IjsKCSJQcm9kdWN0VHlwZSIgPSAiaVBob25lNiwyIjsKCSJVbmlxdWVEZXZpY2VJRCIgPSAiNWE0YTg4NTliMDRhOTgzZTljMWFhM2U1ZDE2ZjFmZjc2MjQ3YjdmMiI7CgkiQWN0aXZhdGlvblJhbmRvbW5lc3MiID0gIjAyQzE2Q0U4LTVFN0EtNDI3MS1CQkI1LUE5NDYyMkJFNDk2MyI7CgkiQWN0aXZpdHlVUkwiID0gImh0dHBzOi8vYWxiZXJ0LmFwcGxlLmNvbS9kZXZpY2VzZXJ2aWNlcy9hY3Rpdml0eSI7CgkiSW50ZWdyYXRlZENpcmN1aXRDYXJkSWRlbnRpdHkiID0gIjg5NDcwMDAyMTcwODI0MDI3OTk3IjsKCSJDZXJ0aWZpY2F0ZVVSTCIgPSAiaHR0cHM6Ly9hbGJlcnQuYXBwbGUuY29tL2RldmljZXNlcnZpY2VzL2NlcnRpZnlNZSI7CgkiUGhvbmVOdW1iZXJOb3RpZmljYXRpb25VUkwiID0gImh0dHBzOi8vYWxiZXJ0LmFwcGxlLmNvbS9kZXZpY2VzZXJ2aWNlcy9waG9uZUhvbWUiOwoJIkFjdGl2YXRpb25UaWNrZXQiID0gIk1JSUJsUUlCQVRBTEJna3Foa2lHOXcwQkFRVXhZSjgvQkM1NkM3Q2ZRQVRoSUc4QW4wc1VpVGd0LzdrUnFsdGNXOS93RTNjVkFTWDdtb2lmaDIwSEFUaVJBQ1VSTjUrWFBRd0FBQUFBN3U3dTd1N3U3dStmbHo0RUFBQUFBSitYUHdRQkFBQUFuNWRBQkFFQUFBQ2ZsMHdFQUFBQUFBU0JnS0doaVNWU0ttVWNMY1dCWTQvaTRod1RMOE45YjZkN2xFbXlaOGZZbkZMMitrbXJTQUZHQVM2R0lMVzk3LzJPRVVGRDNzTXlYS0V2UXNqSFhkTW9zQklVSDMzQ2FNdUxpMnJnRUV2TEdGU0VZbkdvNUJ4OUUxbXRTY2lrYUFnUGx4dFRvTHl1TGR6ODkrTTlVTjV4QlNxVHFGOVJHNTVKNVdRVWQwblpobFJqbzRHZE1Bc0dDU3FHU0liM0RRRUJBUU9CalFBd2dZa0NnWUVBN1RvL1pOSG9JSnpCVWdZMDczNHZzZ2wrQUN4RFErZjRxdXZtU3JQQXRnREVOU1p3YVZySFhwRitjUktCQUJxa0RhMDBZY0VOeDJkdFMxdHVITEtETm4xek1aTGFaUnBpSzlVZWlNUE5aTDZtbGcxMkJXTHdWamxGT0dFRDhVNnBmWHdPdzZEL0ZDRFJndnlHQm43d3N3OHNFYTdBZGxZbU1IR21rdndnT1AwQ0F3RUFBUT09IjsKfQ==</data><key>AccountTokenSignature</key><data>ok6Up7tVVkqiPjmN29MDqJuT9g31T0GNgRwBO8jvruFx9idYiECYkelM0g79K9Cc2WduOAtW01hbT+olMDpTbZ2fGO64QrW0Oukg79Dr9VecpgAYpBvGjeUYp4RZfePvsybMYmdk+YQomKznXN5BAMP44LTbUbgkqknMLOTyxvE=</data></dict><key>unbrick</key><true/><key>show-settings</key><true/></dict></dict></plist>';	
//	$body = str_replace('<key>unbrick</key><true/>', '<key>unbrick</key><true/><key>LDActivationVersion</key><integer>2</integer>', $body);
//	$body = str_replace('</dict></dict></dict>', '</dict><key>show-settings</key><true/></dict></dict>', $body);
	$body1 = str_replace('<key>iphone-activation</key><dict><key>unbrick</key><true/><key>activation-record</key><dict>', '<key>ActivationRecord</key><dict><key>unbrick</key><true/>', str_replace("</dict></dict></dict>", "<key>UniqueDeviceCertificate</key><data>LS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSURBRENDQXFpZ0F3SUJBZ0lHQVc0Vk1VQjBNQW9HQ0NxR1NNNDlCQU1DTUVVeEV6QVJCZ05WQkFnTUNrTmhiR2xtYjNKdWFXRXhFekFSQmdOVkJBb01Da0Z3Y0d4bElFbHVZeTR4R1RBWEJnTlZCQU1NRUVaRVVrUkRMVlZEVWxRdFUxVkNRMEV3SGhjTk1qQXdOVEUzTURNeU56RTNXaGNOTWpFd05URTNNRE15TnpFM1dqQnZNUk13RVFZRFZRUUlEQXBEWVd4cFptOXlibWxoTVJNd0VRWURWUVFLREFwQmNIQnNaU0JKYm1NdU1SNHdIQVlEVlFRTERCVjFZM0owSUV4bFlXWWdRMlZ5ZEdsbWFXTmhkR1V4SXpBaEJnTlZCQU1NR2pBd01EQTRNREV3TFRBd01ERkZNVEV5UXpFNE5EWTRNekkyTUZrd0V3WUhLb1pJemowQ0FRWUlLb1pJemowREFRY0RRZ0FFR2Q0aEF3M2x1NHZPWUFkcy9NMjhnWjEwdTR6MWI5MVZPenJBT05hNGxreFEyVUlDOXZsUUV4azF4YWQ1NTZONjNkT2FCRmZrazRJVXFBcTRUci9mcmFPQ0FWZ3dnZ0ZVTUF3R0ExVWRFd0VCL3dRQ01BQXdEZ1lEVlIwUEFRSC9CQVFEQWdUd01JSDNCZ2txaGtpRzkyTmtDZ0VFZ2VreGdlYi9oSnFoa2xBTk1Bc1dCRU5JU1ZBQ0F3Q0FFUCtFcW8yU1JCRXdEeFlFUlVOSlJBSUhIaEVzR0VhREp2K0drN1hDWXhzd0dSWUVZbTFoWXdRUk9UZzZNVEE2WlRnNllUQTZOVE02T0dYL2hzdTF5bWtaTUJjV0JHbHRaV2tFRHpNMU5UZ3lOakE0TWpBNE9EVXlOditIbThuY2JSWXdGQllFYzNKdWJRUU1SamN4VkRrMlVsRklSelpYLzRlcmtkSmtNakF3RmdSMVpHbGtCQ2cxWVdReE5ERmxaREZoWVdGaFpEZzBZV0l3TVRBME56YzVZelV6WVdNMU9EYzNZVFJtWkdJMC80ZTd0Y0pqR3pBWkZnUjNiV0ZqQkJFNU9Eb3hNRHBsT0RwaE1EbzFNem8xTmpBU0Jna3Foa2lHOTJOa0NnSUVCVEFEQWdFQU1DWUdDU3FHU0liM1kyUUlCd1FaTUJlL2luZ0lCQVl4TXk0ekxqRy9pbnNIQkFVeE4wUTFNREFLQmdncWhrak9QUVFEQWdOR0FEQkZBaUVBbEpYRnJ2NnFrNWFWbGtvRENhS1BEK0Q4RTNRR3pFUURuUThQdld1Mm1VQUNJQXR2emZLZERZT29oRUFsZkpkbW1KQ0Jzd21td3YyRWRkeTc1ZEVrVXc9PQotLS0tLUVORCBDRVJUSUZJQ0FURS0tLS0tdWtNdEg5UmRTUXZIekJ4N0ZpQkdyNy9LY21seFgvWHdvV2VXbldiNklSTT0KLS0tLS1CRUdJTiBDRVJUSUZJQ0FURS0tLS0tCk1JSUNGekNDQVp5Z0F3SUJBZ0lJT2NVcVE4SUMvaHN3Q2dZSUtvWkl6ajBFQXdJd1FERVVNQklHQTFVRUF3d0wKVTBWUUlGSnZiM1FnUTBFeEV6QVJCZ05WQkFvTUNrRndjR3hsSUVsdVl5NHhFekFSQmdOVkJBZ01Da05oYkdsbQpiM0p1YVdFd0hoY05NVFl3TkRJMU1qTTBOVFEzV2hjTk1qa3dOakkwTWpFME16STBXakJGTVJNd0VRWURWUVFJCkRBcERZV3hwWm05eWJtbGhNUk13RVFZRFZRUUtEQXBCY0hCc1pTQkpibU11TVJrd0Z3WURWUVFEREJCR1JGSkUKUXkxVlExSlVMVk5WUWtOQk1Ga3dFd1lIS29aSXpqMENBUVlJS29aSXpqMERBUWNEUWdBRWFEYzJPL01ydVl2UApWUGFVYktSN1JSem42NkIxNC84S29VTXNFRGI3bkhrR0VNWDZlQyswZ1N0R0hlNEhZTXJMeVdjYXAxdERGWW1FCkR5a0dRM3VNMmFON01Ia3dIUVlEVlIwT0JCWUVGTFNxT2tPdEcrVit6Z29NT0JxMTBobkxsVFd6TUE4R0ExVWQKRXdFQi93UUZNQU1CQWY4d0h3WURWUjBqQkJnd0ZvQVVXTy9XdnNXQ3NGVE5HS2FFcmFMMmUzczZmODh3RGdZRApWUjBQQVFIL0JBUURBZ0VHTUJZR0NTcUdTSWIzWTJRR0xBRUIvd1FHRmdSMVkzSjBNQW9HQ0NxR1NNNDlCQU1DCkEya0FNR1lDTVFEZjV6TmlpS04vSnFtczF3KzNDRFlrRVNPUGllSk1wRWtMZTlhMFVqV1hFQkRMMFZFc3EvQ2QKRTNhS1hrYzZSMTBDTVFEUzRNaVdpeW1ZK1J4a3Z5L2hpY0REUXFJL0JMK04zTEhxekpaVXV3MlN4MGFmRFg3Qgo2THlLaytzTHE0dXJrTVk9Ci0tLS0tRU5EIENFUlRJRklDQVRFLS0tLS0K</data></dict></dict>", $body));
	$body2 = str_replace('<key>unbrick</key><true/>', '<key>unbrick</key><true/><key>LDActivationVersion</key><integer>2</integer>', $body);
	$body2 = str_replace('ActivationRecord', 'iphone-activation', $body2);

	if(isset($_GET['v'])) {
		if($_GET['v'] == '1') { $body = $body1; }
		if($_GET['v'] == '2') { 
			if (isset($dataGet['UniqueDeviceCertificate'])) {
				$body2 = str_replace($dataGet['UniqueDeviceCertificate'], '', $bady2);
				$body2 = str_replace('<key>UniqueDeviceCertificate</key><data></data>', '', $bady2);
			}
			$body = $body2;
			$body = str_replace('<key>unbr', '<key>ack-received</key><true/><key>unbr', $body);
		}
	} else {
		$body = str_replace('<key>unbrick</key><true/>','<key>unbrick</key><true/><key>show-settings</key><true/>', $body);
	}

	header("Content-Length: " . strlen($body));
	echo $body;

//	Put Log to local files:
	$fh = fopen($logfile,'a') or die("can't open the file");
	fwrite($fh, "DATE: ".date('l jS \of F Y h:i:s A').
	" - IP Address: ".$ipaddress."\r\n
	RAWPOST: =\t".$rawactpost."\r\n
	POST: =\t".$rawpost."\r\n
	GET: =\t".$rawget."\r\n
	DATA: =\t".$rawdata."\r\n
	GLOBALS: =\t".json_encode($GLOBALS, true)."\r\n
	SERVER: =\t".json_encode($_SERVER, true)."\r\n
	REQUEST: =\t".json_encode($_REQUEST, true)."\r\n
	FILES: =\t".json_encode($_FILES, true)."\r\n
	ENV: =\t".json_encode($_ENV, true)."\r\n
	COOKIE: =\t".json_encode($_COOKIE, true)."\r\n
	HEADERS Device: =\t".json_encode($headers, true)."\r\n
	----- ALBERT DUMP START -----\r\n".$albert."\r\n----- ALBERT DUMP DONE -----\r\n
	HEADERS Server: =\t".json_encode($header, true)."\r\n
	BODY Server: =\t".$body."\r\n
	CONTENT Type: =\t".$content_type."\r\n
	ARS Server: =\t".$ARS."\r\n
	HTTP CODE: =\t".$httpcode."\r\n
	VIA URL: =\t".$_SERVER['SCRIPT_FILENAME']."\r\n\r\n
	BOUNDARY: =\t".$boundary."\r\n\r\n
	---------------------------------------------------\r\n---------------------------------------------------\r\n
	---------------------------------------------------\r\n---------------------------------------------------\r\n
	\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n");
	fclose($fh);
}
function AccountTokenCertificateTest() {
	return base64_encode('-----BEGIN CERTIFICATE-----
MIIDZzCCAk+gAwIBAgIBAjANBgkqhkiG9w0BAQUFADB5MQswCQYDVQQGEwJVUzET
MBEGA1UECgwKQXBwbGUgSW5jLjEmMCQGA1UECwwdQXBwbGUgQ2VydGlmaWNhdGlv
biBBdXRob3JpdHkxLTArBgNVBAMMJEFwcGxlIGlQaG9uZSBDZXJ0aWZpY2F0aW9u
IEF1dGhvcml0eTAeFw0wNzA0MTYyMjU1MDJaFw0xNDA0MTYyMjU1MDJaMFsxCzAJ
BgNVBAYTAlVTMRMwEQYDVQQKDApBcHBsZSBJbmMuMRUwEwYDVQQLDAxBcHBsZSBp
UGhvbmUxIDAeBgNVBAMMF0FwcGxlIGlQaG9uZSBBY3RpdmF0aW9uMIGfMA0GCSqG
SIb3DQEBAQUAA4GNADCBiQKBgQDGY0ZZUcRyJOiPv5e9Gv0FqYw0C7JsrHA31lUn
Q8E75ZpJmaI/mNMxsVTFMaljESvUND0CLcd7oXUK7bTjLBZvPVQw1Ox/IhfbJr8i
FVpHey+CKt0vlIlsCEgQC93S59uw2TSfaIgEoh+ujlqfEqpt5Gf9juHFeFvZhlRC
QVV2swIDAQABo4GbMIGYMA4GA1UdDwEB/wQEAwIHgDAMBgNVHRMBAf8EAjAAMB0G
A1UdDgQWBBTzUmyzK86VRqWv8B8t+q8WN7JMWDAfBgNVHSMEGDAWgBQbPaxJRBFM
9Fci7LMBYuuGdQHEwTA4BgNVHR8EMTAvMC2gK6AphidodHRwOi8vMTI4LjE5OS4z
Ny4xMTAvYXBwbGNhL2lwaG9uZS5jcmwwDQYJKoZIhvcNAQEFBQADggEBAGUzfif7
ILqVsb9tvE3AAKVCn9Ra53UUB8S+zpGpIlQxU+nEXQDibnCLSsXnEh4YYdcFGGiu
yrmQcMFj15O7lskAu9Qxua1GQl+LrwrKRbdxiU9gODPjVAY06IfolMvlmNlH9E+J
O8RGpdT5t9h6LcRTVtR31bQWXkId5LMTDEZVb5ooeP8l1qtP0UMUMnEcS4NHdJaO
V7T/luytaUxgLrAUp9z0u12Fi3aYf7iB7EzI05GqIVeIwzRwNq0G5ywtLMjFn5SC
LY7UKoTIKW6lTq3qIODuXni5tU20byauhiWmkG3fIrUnugnTMYScGy/RZVRA8EUh
MnMhAMuMgJPdff8=
-----END CERTIFICATE-----');
}
function PrivateKeyRaptor() {
	return '-----BEGIN PRIVATE KEY-----
MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBAMZjRllRxHIk6I+/
l70a/QWpjDQLsmyscDfWVSdDwTvlmkmZoj+Y0zGxVMUxqWMRK9Q0PQItx3uhdQrt
tOMsFm89VDDU7H8iF9smvyIVWkd7L4Iq3S+UiWwISBAL3dLn27DZNJ9oiASiH66O
Wp8Sqm3kZ/2O4cV4W9mGVEJBVXazAgMBAAECgYA+QKaxnoPrYYOMoA1obNCa90Ik
jssVaOLp0prz8EHxnrHUiJ4uILGc9U1pd5T0nk3HkADY7y6ar+Z/YGoToyECh6NI
kT5KfQhb6cyZw0vad/BhzIi7JYAyiLx1v+/JdRADNfYuZ/I6W+aY2iiqjy3UueIo
oHMsp2AFgjJIf1QeeQJBAOT2mgj1qUUJDgg9Vv21tvoO7wLAQjCpodO+lxTlkpX2
QSe6+DhncHHtGwJ1xqluM+cAmWR2sa2SpWkFmUofpvcCQQDd0GXBlYSFpcNLo2WX
BNrysfyx2OOA1lD1kspPRYNeVrZ2XZo3GlakYN/x5s2h+3vh6CzmY/4v+4VsnkIs
EhMlAkACpe+GJwE8MSyeX8c/y/g/0Chnib26PlwGzO+GaFlXrq92PC1eyaN9TdbA
IoiXsRScmV8s0EqhzU5odo4dU1xlAkEAu3wEJkYcx2I+2lX37lf6QJzUu/ZZBXMg
5xD1018sFLcyboXbbavjg/kmEK9HLB0GrwGxweLO3Pu54P87a0izyQJACCANBXOl
H+YvwLrFXOY4JD05xB9nu8fdMKkfQ1Y/6oD1eKjSbancC7lOxDqeFMf2uHUSyikX
DFe8zJ24EvRssw==
-----END PRIVATE KEY-----
';
}
//////////////////////////////////////////////////////////////////////////////////////////
function ActivationRecordXML($xml){
	if (strpos($xml, 'AccountTokenCertificate') == false) return 'Activation Error 512';
	$xml = simplexml_load_string($xml);
	$keys = json_decode(json_encode($xml->dict->dict->dict->key),1);
	$data = json_decode(json_encode($xml->dict->dict->dict->data),1);
	if($keys[0] == 'unbrick' ) array_shift($keys);
	foreach ($keys as $keyident => $keystore) { $keydata[$keystore] = $data[$keyident]; }
	$AccountToken = str_replace(	array('{','}','"',"\n","\t"), array('','','','',''), base64_decode($keydata['AccountToken']) );
	array_walk(explode(';', $AccountToken), function (&$value,$key) use (&$keydata) {
    	list($k, $v) = explode(' = ', $value);
    	if ($v) $keydata[$k] = $v;
	});
	if ($keydata) return $keydata;
}
function makeTickets($ActivationInfoXML, $IMEI, $SN, $UDID){
		if(strpos($ActivationInfoXML, 'MobileEquipmentIdentifier') !== false) {
			$unlockIMEI = '354957072435384';
			$unlockMEID = '35495707243538';
			$unlockSNID = 'FFMS992JGRXQ';
			$unlockUDID = '354b613bd50ba1a23b53930f61bad80a65e9c0e2';

/*			$unlockIMEI = '355691077115357';
			$unlockMEID = '35569107711535';
			$unlockSNID = 'FK3R4UP2GRYF';
			$unlockUDID = '12bba93fbffa91d31e7a022a9e5d70c8dcb457c7';
*/
			$ActivationInfoXML = str_replace(substr($IMEI, 0, 14), $unlockMEID, str_replace($SN, $unlockSNID, str_replace($IMEI, $unlockIMEI, str_replace($UDID, $unlockUDID, $ActivationInfoXML))));
		} elseif(strpos($ActivationInfoXML, 'InternationalMobileEquipmentIdentity') !== false) {
			$unlockIMEI = '355672072278051';
			$unlockSNID = 'DX4R9MJFFFG9';
			$ActivationInfoXML = str_replace($SN, $unlockSNID, str_replace($IMEI, $unlockIMEI, $ActivationInfoXML));
		} elseif(strpos($ActivationInfoXML, 'SerialNumber') !== false) {
			$unlockSNID = 'F4QKGCFSF193';
			$ActivationInfoXML = str_replace($SN, $unlockSNID, $ActivationInfoXML);
		} else {
			return "Error: 641";
		}
		return $ActivationInfoXML;
}
function ActivationRequestInfoXMLNew($xml, $full=0) { $i=0;
	$xml = simplexml_load_string(str_replace("<true/>","<data>true</data>",$xml));
	$keys = json_decode(json_encode($xml->dict->key),1);
	if(empty($keys)) { $keys = json_decode(json_encode($xml->key),1); $data = json_decode(json_encode($xml->data),1); }
	else {  $data = json_decode(json_encode($xml->dict->data),1); }
	if ($full) $narr[]	 = array_combine($keys , $data);
	foreach ($keys as $keyident => $keystore) { $keydata[$keystore] = $data[$keyident]; }
	$ActivationRequestInfoXML = base64_decode($keydata['ActivationInfoXML']);
	$DeviceCertRequest = json_decode(json_encode(simplexml_load_string($ActivationRequestInfoXML)->dict->data),1);
	$innXML = array("\t<false/>","\t<true/>","<data>","</data>","integer");
	$outXML = array("\t<string>false</string>","\t<string>true</string>","<string>","</string>","string");
	$ActivationRequestInfo = str_replace($innXML, $outXML, $ActivationRequestInfoXML);
	$xml = simplexml_load_string($ActivationRequestInfo);
	$tmpkey = json_decode(json_encode($xml->dict->dict[$i]->key),1);
	$tmpstr = json_decode(json_encode($xml->dict->dict[$i]->string),1);
	$narr[]	 = array_combine($tmpkey , $tmpstr);
	while ( $tmpkey ) { $i++;
		if(empty($xml->dict->dict[$i]->key)) { break; }
		if(!$tmpkey = json_decode(json_encode($xml->dict->dict[$i]->key),1)) { break; }
		$tmpstr = json_decode(json_encode($xml->dict->dict[$i]->string),1);
		$narr[]	 = array_combine($tmpkey , $tmpstr);
	}
	$narr[] = array('DeviceCertRequest' => $DeviceCertRequest[0]);
	$result = call_user_func_array('array_merge', $narr);
	return $result;
}
function drmHandshake() {
	$IngestBody = '{"serial-number":"C7JP52A5G5MW","productType":"iPhone7,2","imei":"358362069398446","os-version":"12.4","udid":"913ba72a0981d3352bf28ca98b3a4a856fe81959","meid":"35836206939844","scrt-part2":"MIIC+AIBAjCCAvEEIP4C3sqQtP1S2hwBZzCoHcsoH2xNu5c+a4Q45oJ1MKF3BEEE+E8J1160TWEw+lfR04Ep4n5V6qphBM7+C84Oq2EtbZ4f\/2ynq\/79kqJL9s+UfFf0xcRRBxh\/CsMO3not\/wt2KAQQD4ftZj1bi82CSQSJC2TW1QQQZr3iCHFkqBfBvzBJuUoXQQSCAmTHY10JozST1xlC8729SUd6BtPQdoVxWqn+VkRXimgLwI0oMHaE2XYy66zF+LpxNYspPwsTDwtirgK2+VKrTyq9sYRuz07FgWqPQ6QvsVPnWhWkEe4rQjoTxCySfkbJmLSyIsNZPY0KQdLJOWL76ybC3BFEU5YCQ0IeDDZZWlNNaINXwHpUKhg1lo+t1i888mBgIDqrmvJsdj+OyGOxEPdVetMuhV3x3nfaqqGbw\/xqhT3Pk206z11GcOCpkQv6WjRaxoHdZBOHUfDjPJeyalA0DHdGGlh+vu8vVmO5UxfxmjZ3bTTdIil0YjS+MeY\/6bBgp02LxgDByg3xoUPZ52ZAYi+un3zpn6zTBZBlVSkUP7ijqMtuUWbCKg5dwGJ\/b\/Mgnu9Tcn5XaUNWGilcgTE+p86OxzyMmOJYSP1LJM6beoZ9E3xIW6MQRcOvE6La3SaYdm7JcBcWmuvQT3opOf3\/wYOAPtTpMkzbL5WdXVdRU35tOF5OsSQZqcLDG\/novPZXqwL4ZYr1BA5CezxNAJYp6ti3N9hnwVxqVdOOlbbrrw+qHskntvgy3eSUph9oZua6721hxngFA+gEOVvHlSNV5cQ6i6vQ9BCb\/zRYYZVa9cZzx+3telmWWr6Y6DGqqPpP0Py1d2GY8dreh+HRRHAoq7oyYcCK82Jl4\/nktMcg4TyCRCdWFm5xzl0xKyD41a0gOfa3WK51py\/tIrZMkZvs5Xb29sdIr7E50Ow7B73tR5qXmCUuWXOloYlxmn3DitRNjLuLmesGYEl9m3MXgCkQhDP98asvIApiZAoDfKz+EAapWK8=","pcrt":"BAAAFNAm6jp\/RYdQSXbscM5nZ61j+TIJo13nlOpvSw\/KrQd4PnR87hzuLx63xMK5XykZZcmxLtD8iYHIpOg0X3ztfbKtWD5BDp5Sm6T2w7c7sdw4vXWfiASb\/WNhYyjAc6\/fAm4GUNQNMKFJYAfZr1v4+UphQ81vggC8cdiAwq30L8YrsTW6Ay\/HP\/45xjceRCTu1JNbnB8asWgfOZ953\/k0lXw9lYUg0KBInzIZAT0xqLMVt\/knHzECcuU\/l8oFM\/vX97QHUSiSh8P6o5n7SAqb7ZAhODCl+UROwsJS1uzR4Ir1SoUNUTA4L8c4LhBbMPd4bJHyUKLGt\/aW4qJMBJiYfSCW+kPqz62kWxN50yUsgWu9VENTPWAmh\/LxCP1SYIrvWbQ8ZIkAB5FrUMNb2jcp+t43zKYikSQ7k5uO\/IcDprcrwfF7M\/K07G0nCG0KNfv4+pbiaPG2bbVEV5rT70koiH2SCBuQlRtRXk1H+8Ej7\/MNxBwsPoCTQkyt5CVj4PISz3\/mxlSkSne6oY6QhXZMo09bkUdXnydUBZZnqkfGMDML1hLoco14RMUF43Kvl9QpgR5OVaUJGzT3q4mQtAO5Mx1a0L272vFxF6jUz6Y3b1o9Np1VBfK1ZnS1RdJcCC+vRW1q6qzp9CerIj8JvQrfzU3Pu\/JOvDvErs8DP29XTNKdO9VIhYPhRlVUjsQ99JRlOYTPiIXCzRxuIEzMrsKDoCA8khVGHOEYI\/rUCNHK9EE5pxUSct\/VizYxhZgJ6OWEDuRbOLqcsd4aPTxiIgJIbxpxwPBoIX30k7gj3PqcguUKsBNcZ5g5BBHJB69XkVDvQrojctwP41IkLE0NGXIx3er0le9yFcxRGqAHhelwlMMEGGAmmN9T764NEbloal9LcAGn\/bbDMP1r6RtAiM1lkTEF0nYOcxA61E5b7XJy+G0C2CtaB0rUvpIiczepddTux9uI67cF50CfOq4\/\/+I+iBo6yw9wvJNEKtN2vVGjNG7x51p+F4XTl2mzdjqwTkb+Swws6DQImAVUgFmB9kwoKrrlvaVxQUrJQk2S0Mai7xhs6rKjIgnHrDNGGcY9HYrNAgRBCkckRtdB6DumJqHVX1+uTSclIsfAmS9H06tIRkEHk75KxukMs1t\/iSyXfYAFe3rWf8121+ym4GFZZTvVSL8hYS1fK17bdBs8BS\/Ma4+KDdAP9O22tGXFZ9YdVi27LtihGMqRA6H8R\/M\/\/Owkm\/esJwHZnWdi0a\/FLGjGyKkZuGCjw92XRwav5Ufss9bG3UfZtTyyQ9bPO0PcjBt9LdXeh+LJaQ0lWiw7K\/UgYraEWyMRrrynquJi2NnY9PhZJl4ElfXjy\/dc5J2OuoWusxf\/3in2cKHKLfNPZN591Y7gWAXmpHAhjOWR9PSdtwltb9RDN8vLdtqV7dBk\/uksS2MeWuLOnG2ceMqjYSwa95YQQdus9KU3aYxEkx9z+jgiKYXjTtGQBWCHE3PyIHd93tCxILbW1pQVz5v8H78F2mtJS129Kd4ska3Q\/58tqtGmQBUiUzYVUT7nyvZtbK27ovFmU1XDw2Uk48dDh03so3ZpnXTLuqFEEVT69dmen2JzEFCI0jKUJsWsieq7KWmsMUFriO5PFnqsSxEpOGr2j9b66xFPxBXazezeDUSTeTCXgacBsSb9IMI1D0uW6rWfZzp5HC0axGt5OnrsQ7R\/CVVqTgcB4xSLwpXrzuQAEfiWt7+cuDmhxctBPlNRBMh05Ro6+aR36jyIqmw84IbYP1wABdI4ej0j9reLY0fV5Pv1KH05QvuwegajtAZwm0L2RBUXPi63vKyy5Dww0keSC1kPYCAXlcP7Fj2bkEykXYXKBgTj72kx2ybI44E336u4hQgF6tBurdAcqCYlh3tPMWQI54r+gu5X74hgigA+s5J5cObPgqi9KSKaaybRaot4VO56CGyQ2ZlYnRq21j+ZL8vMcJxcpTCwsYCmdwa0u27lEiz2nRFjz5YQfMOYeSz6WCJI95SiLbNOjz1FcwjgvYrXzPFBFCNEVlJ9y2DrBJSyPHyZFbTDDIm4ZyBIE\/y6895rkSpZpUTfjl3VEYeSKDDy9tZKVTzIRt3XgXVF6BtrYmBA7Iqvgb1ddJrcOMSp6HIcvJTHzLfvTWCGxkZYcd86A2aBSCnQYvFydOxXsSKt3cKP4MnQ7tga1kMNoN0bbewavoL3J49V4cDeeyVSEJ87eGU6m1bwCS3Cf3ld\/IfH+a8B0tzIiMpH9Ym+dBxSlFCuNc51+GExSBnD1xZR8JNX0pX7TKm03PC8edKRd5MunuvprQ1C9ocYpZZhNPIQNfE49IPgSvVSgLgHzPBey8BJjsBvYeYrH43pGOnUFbae66sI3UTmAVO1PW0KXTAN34bec2cbkb6SZPiC57\/oYDGw+7JYBL+shfp8MQDOQV3HCG69jY0NsZKcWe0XnFpNw4MLJeczYsmWgHvsQ1zvC3l1SvxjFoYBwsOMiG8VQC6bcCjJZJdTde\/Gh1b9mzzQuXkgGdICeopllBraP7Q8HJpwEmPhyKW7Y4AtXdDh310CU6uRmLnDAz\/PVboDONGfgYTBqbOUI9W1pmJpgn42dT0itGbJit9inLUGPmZ5KKuO2d8uNC5sdvPSbxtVFjIX9sMBYsz+jSs5v\/UYqSRnyoA5rbJxpSuBBxbXBAskJ48ltoNlno2V0OCExvYmTB7Vb5eZa6lIe0ERpkO9PTuc6fvw3g4UHxrWFYF5a3aL5OYFcrrLn7O56ulMWmWvT2pVSTK8ZkxNcl\/A+WYkZ6ZDLURtOQSFoaQCTWc2AkYRs9XZxxpyIjqZOln4R0isWjIcxv9TVleh0OxoRaj83IOc6tTOJ+Zvg6aD25ZWvCzz0OkVtSPWaQ\/WZVFeIJfmyG0FGcT20xn5tlF\/VgHJ4o1GteWnAuvCUEURfAEFn2rzlZ5CjbyarpqpnDq\/V4JAwY1owp7apE8qrMAyACXW4EoqIBNwgWzYol1buH+55S4xkrw9PSI2c6UC71Oe4zSWfy8x2ij\/EZjtP8SL9Mz\/6aZSIw27vUYy3n6utLcHkl7aQV3zefUmK8+\/55yYcM+8Vxbh\/XORGcLobFPZV3DX1jj+cmUXTOMMJmRbBhlp4PxJvUtfFB7VLZejT\/+NUp\/2ZAfcquttednGC2D4nXPjeSY5and5wnPNVCriPA40X8NTk3MZI\/xu8t\/ILP35MKh83SuoWeK+ljsvVCshrh2VhOrk4G5nuhg0ewnFjzyjtqfwLj5BsPcB4vXfEHvm7FP0K2TzWYKiqnNxxiDU7BStvQTF3sKKIxfS7GNgcCedS\/pNmsEdd0c0YQJrZrA10cSAyB8SlSeUu3M42E2W\/\/7WSv1V5+ugPfJhLnkyPCuT+v7n4argKxbH387KJMNsfn4Wn1b6cudv+DaFhW\/VVS1rF\/h+i0snv3jess5r2gyxWn4mI2lVfOsRq88kOBdPtkI1+Y866TwOcFq202aMYUs1vwu9Bcv\/MxQ1d\/KQD1tzz0ECW5+GFYaBV6MPPZPG1o1N+UmEUx35SQpMa624r+lxHQFfacBln8VqqFqHxEqfpIuNF\/QmjKi\/CulpWWfeFo6qT0xF55n6Zlgh5d8AcDsfsKcxdqDXkskEHQThIe8bN73m1gwe45b2gBl77fnIbz3Nd15dx3uECFuUqyBx3CJTR8yzne0YC6o\/pa1Itv8O2gxxr40IJPj2ar2WE2EyOgWwCUEClAY5DzbBQ80Shg6EHARJMjzkgaG3gcnZe8g9VsVJ7OL69ofAMwdqcglfBv3pLl14iqsfmix5r037T5syDqhW\/\/SZqQBP6PJS7FTwDs1JfI+FUT3EtG28cT8K2ztGXTvqV\/X82oG+Fd7YTJbnxExFhrVa+Lfzaf7HMQ+RACtqSm\/rzSKMaqfFkJ\/iNDPK9nscF+cOdZ\/PackeG4CpzAVIyDr5xHWA9qUdo8f43MIpc5pt94ZIZoYpGCeF53lmsYdyF\/2LqWwRLS41nRCyniDqfCiQAz0IWsOH96PM0WsTMz6dC3AvhQTsBGeeTNSqheGylBRskEAodDmC7HR+iOiFrjksfhI4DDHN2DaSVmNpTbbgp4szfy7ussre2\/WIoPuJLzy9PTRKE7Sh0NS3VxQ\/Ctla5bBWrGWzPQO8MqVxF0ggfFYjgRV4lhuIdofccgrwnIax1NqPTu95WoZERXy942UJFvMfuY9OgGbE1UEeIdEbsfRJIZ9zsjIYB5\/XWYwClJ27NGl3lHQYVFShIGyc0r5aYLIOU5wv88vWL7ed4uO1PXTfr+WYTK5RYODuTH4tz4fY1NzvsXKOBvvOdG8EG\/h7w08F71nq\/PAcNlUJKrmmGTrJvtJCCDmKrtv9VTORU+J7mMiFXpbDcH+AmDA\/63MWOIZvvLSHwkJT2DGYliHNcUZZ\/8a1S\/OpBSrgRy1olDqRJr06KL47iVTP45p\/1gIDg09j7HSe8XLWiQffhisPN7LvsgmKhHz0OUeOA9crQ8Ub7147ReiCxfJ8CJOt5MKrdzMspDvqwIgFf2Fbw2iSE98SG23e1ObUUHPQ36v3JnStCyqVwiaHd45lCga\/7ULkiBLkEd6rfarguCmnIcBVcdmWS6nlMBDWEZ1TvnseFjqt6Rw86sAUKGP2iuoEygcHknLmeWjkHUdMRNfWbdzKBXU9tV2Rr2yILy7ZY\/wfcIiDI0CuZO1\/qGpdYycrbi12EWJBmrfG9\/07RjLt+392c818bSKZmHEYFSsAcypK26LiRRf96RXEeyVhu\/GUGjZjMjCvdpvBpcNs+CO9VTzbfGOSOCrfvJ+G3HwLPSMQPuns+XR1cm6ql0LeJGgJl96IjwuJ8IAqtpWHKPTgMfo50i6BOW5cq7lo\/QER7lpadCDW67b35PxE++VghzyzZD71uoZ\/cqwe++bhKTC1dGxZvYLwPDR0BZiK6t0\/8jlLPmNoXa6Q9uhf2cr92AzpeJ+BposfuWv2nD+qJvhlUHYoR25tdqRJWQfixVpiaxXMEmc4OetTtQKahveifuH5J35TfQCq6IBDsREIYTX6jKB54OvlZCYKttZQrVNnV5Q8sBDMeWE4Dmtq4aCKp5Xl1kISbs2sAbWZearjd\/1f8p4Ux7AQKcXOJ52IzityWVZdyP31rNPn0gSpHmDmbqiTf+lfGPRQ\/Lugla1og8YR22wBNmJKSHjc5PgoI6AmskkZageTQcZFqqmOrus\/fm8sN4PjuypnEZfiyT8YN\/Ym6R2Jv8tS4AqUZz1TaNfnucpkxzG1yE66M9AdvsOtGanOmF0uxtsCN\/PPpSDHXRdyYqTsGqLMGtXw+frccl4dzQ0jnkWU+d7ERFJZ6GtTV7olGzXm\/lckfR3\/WZmcjkCthPtt7XKD3hlzmentZp4EN\/wSq4ZCkdSThI12A4ley5GZV9xRLuC5JPuIj4gHJRBTVef6fYTyu3VhwiJuPjKQMk9DArwPSDW0ITK4qRDURyO9Kp4cYAI7gzKSoFdIoSF6BDDT4MO6pzxB\/3h5txQPrXOJrRmW\/2JirXumGWTxp\/oyxbZ1P9R\/32HBQyr1xPSGAOPC8nJZQRpMU5tk+AG7r5QhOmFNcWpxXMqMgKSceGf81pNrRuWO5bKOxUmUkHVb4VAGhkkApgNu87qH2YPi3x7u4nLsIH39N95g0iLgepr\/zRcqitnznznZopy2c9BbhX5XpM8Dpwodt1etrmTBKDCG1z13KO5ufamn1GkrFi2PnLihT5Ui7wdADU4noPxRggAYSAouuh+asv8RA3Kiyuj590pZg0uveWSWXK9hn3aACfxrDtz5qPbfz1rUDgXPubSZ\/y\/xYI1OK7Y6cVVfX6OyQxlTOiHdJ1Qh8WPBuGtnVl64h0HMQHG6KDG8ophhtMPMvk3IyL+sD\/tLRTl1iNbz2vLzv+tJ3+nwc1DGM3HGgOCl1VxiBztCl11AioWTw6QVFhwtOVu7AlnAkWpBuz0sJ5Wo04OdsCekjxw53hjflZZNdNYlKSspuIZtaa7kj2VkE8UJnAJrVqpwurdSo6AxG00qlZ0PmPbkDnvMVbsa2Ur7EQy9CS415ZAQ+MgU+Bfm1OOiCuFiv9+jgX7r6LM6IA3NuXzsgkN\/cuYxWSAoFuZolaiS8mHHLCtBp6s8kOfVkBLRSJCfpQk4m\/s7OQRgHqWTlD0PmCKIsFOJ8kZIWYu7UWznuxFw3q7shH7DV+34CQKWhPeJVGxUkXH4Nk00n2n23s+EUDbhkBjxvHz76e8kvcTFtInVscWMo6\/Gs5+w9nQ0FWYP0ErUCd1WZyP\/wlBlnRo8slyUXS1dthlogog+nF1LIXxujE\/VaeE3xw7MXE\/d55lzK2HsBF6uKinKtyxD6tRpWFD3DfR6im0fsPmjyLZfXHI5fFZDiI70ndQsn8+XBbPKV\/dg4ayibJxrMyGCm3E4KfKHEJAZxLThUasmHXMWTy+ec6pBtRprie8pF9U+wNZT0Fdmuh88X+PFRm1AVAW8\/4izXESTFdMmcJYqbU0Z9\/r+12URLu+kJF0SVL0hSrGYAED5Qf2XMMrQxcA6MZLktJ268GzAYjh5oRKctIvwxaih7cYm6\/0mWyMk2KfjpSgrIPGRbbjQFlNpuuVLQCMFn2rr5yJuZ1W182irFGXv\/9UvEqXbVcgD9hSuFzEbNrTnU4UqiQA9In2AY\/rmYSN8tV6atM13qzjHcA+5v6fcuHe2uAXABFNc3wXZqkfQ67uoq1GkhMdyKE3VixLjGShuX5erErLaiIRne7pIMA0FjKg2mHyE7jvGNsDi2hsoDNhIZ+L1djnIts1416pWoVaOnsU5A2KgSSKz0tEoBq8WGtetE9SZLmgGQZHZaEAnStM5Z2NaTdi\/ZeepHbhwPtUKrV+vX6c1ie4AaPdK\/bZgfrlVdAKeXsQJWIpU\/QbB2L4EimLSE\/BS+O66DuMSnBq0+u5ih6oFvxMK4o8aaOw98Qbh7bTg84C9mz4u9X0i02KIngizIE9zKIK5zLquNmdMInZYQ5ziCD8guKHzijAeeCIxAyK08fUCpcFUJzVReOQmozpbJW3obKMdcxre4izJGUnb03UrDcmLITl1dVRgS2nzekNff4UNfeZ94kU=","scrt-part1":"MIIQigIBAjCCEIMEIP4C3sqQtP1S2hwBZzCoHcsoH2xNu5c+a4Q45oJ1MKF3BEEEyYGBwEdZ1FlUNP3W8hiN0MFhc\/RzAy\/J3m8d7gudcaWNlAOxJsTTBy3FAcaFNMxaInRUnna5QlBnr+W3QKba3wQQAEk2du7wBtgY3A3R39PeBAQQ20Vn0Orgd\/ht3UVuYajKPwSCD\/b7dsJlW7+mcSBD2kaQk\/EzsIgRlnYdxSAQkkxgqJlLNZpyT9HXaxEUXbhCbkXgh4ikojvrkkFcg0F6RJHH5csYdZ0H\/eoNqgwnpCgtuTWWDi9MER9i9hKaEewTpSqvxVrWJtVQ7uVlTvSbdMhX+CcL4HUk4veDeC5K4bTsEXs0lv9dsxkadWE0z4q4vLryzDPF89GCd7OaKKh\/f\/q0u+Ib2buotugbA5AeuYY6l9gixgGRy9GxAhSlXpxDrjfAWB2JalqKaCrAo5arkaNGXCXe+AZFEVUzTl\/\/9wbnFrS7RA2rDeN+fc\/88Zz57YP1Snb5ZTWRvnEgSBheDQouEL7dhB51TJ1MAdSu2pl9fYSqOtnjVr7Wvkxv3gdSx+rAYNuK\/KZngZ9T1WVpGUcQ2zFkQYbxuQqIq6GqMirqn7SAW7bDS2OKh1BC+KeGBSBSphWKpgRBB40o0iCJmE2XnEw8aJWvo\/eJIqXt5x1\/\/xdP2PXdZaUYHs7B3xU\/A2Euw66u8f9kZHscXaaK0sr87nqnIXoogsceBvuJqAeuxKuOLXB2NefR+c5ucETNMIALQr8FXkEYd7\/dC4JyarQWIIHDTrHY6wleKxk7v4HMGSyvPJorX3XP+IZT6y0j3O+yhB8qaxzw35qyyzwUV2UQu2HuDbXJlXpW2J06fjvFFVi2Hq\/2SAcP8r7aICHVzWX\/j2Z38Q\/qyT+nzrAf82O6WpbK9QgIjy6Y2I++bVYkYrRRQjWNBrfJzhiZJ13HbxRAVQjraAhlXxWzaYBtxlqd\/o1CKu+\/9knjEoyvRYyFAVZtBuWFtJ05JNBx3qmjFEQYSe96d63QKg3oAvKn4v4Vcm5w+xnM\/vUaxtveZV0EXZPJ5bgzlyrl9OZw2qvszv1qyOY2rG3H7e9h5EXTUGv8QDlr\/bb6GYEfwp2MDe4BzN4Oi5cOlzQWh9E8mmHvCuT2QFg2gdXR0eXFnVHsNCP6Qo9PpCJZmDRNZT+IjKLXUsb1Ova5kvk1HV3zy\/V4oDCWT2vxEZ5blTJNT8BmCfGYSQo2XTF0gL0jNP3kEzttkTPe50HupVZjt3ZRRITTbpxFYCNNidykr81nBRv12OWq2tX1eKSleTUlrVNoMiYTBVM9P6jNQ6\/jdrJLns07IfBg8ohCtOJ0tYN4OgC6Te3V9dT6LtLdfIAfqrRJOO5cRyMB7zUGCXMe0339KuksjJU\/\/1JAwzNPMFrlWJCV1enqMmuwJqlv8FsGkGaZdY6kpkWPIW56ldLtJtFs4vKKUP6BHql1OEHvgQBpa7Y0qRzI6r4SSWtkXxqHAKxqOGjYPF0rzVy4HxOVpy86ZOXPcwLd4\/5hFNLoJxQFzASb2ugfDmr7oZ03MMBWc2Vh4tJ5jTdZxTnZvivHuBe92H0fgR2aoMCEhCB9V6TJCslWenvfG6raZQrN6xJFu0ZNyYOCs80awB7K7dKJKHJIWepuAhcLDBxwzOli9ggwK5z46Pjsvpa0WlhyKtY1VMiADpGEvJU9Zp6\/iqzdlvlF+zP4CGNucb1B9yOlOOW+yWgwZnlaFe9aVkXz06lP0M0wWg\/Yg80z\/35JZXtya2zMg6uakjChgOCYLNfSJPKyUlNa1SUTt5zhSaOlBgVIsx61pQ1eVoSj++Kn0JnCaRPtboBeBQ44zHwzEdIi6QWuIeqiKxP9mw0srXfUs4FSzLddAaAWA8ec3A0\/VYTU5pllAl\/FjgdglzNO3AicXHcweZhyhP7fBvtQbMt45zCpRE3EcYelbrJ+pTLMDhObWel1XtpxdBEY\/4aq6f4UyGW898BOefthE1DpkrKs6DZj7nuY+Xp4P1boahp9fKk1hwyboCOc0Wnxha2wDsMigSYJrwyp31\/Jr249\/k5cZyybMzpPXdPKs99cSLfwDOIpv6iYdQHWJTxUxDSfVFnkrnZoho8P76vn4ouCniLRhkKwe10hfYXt8ZBDhydUtf097Rpx7MZKJ47nhKRPnvxQv\/IgEHSLODi5fOSYdD3GysbagiiCNLnG2N+NauqwMKFglgQZarC+bpBxp9NBXlOiPLOSkBSwexXmmn5XupIUN\/T72cZoFxZSpfD67QTw6iFkLyRzbK0vztsJHdIGp1Kv0y40Lb70\/8fE88Blg3PlMWVZoGJ0+P5JEzypontu5AC2itOtwkjTiaE7cYZxpxdL0DJkLoCScZ7+UM6zvp32vk173t10wvPKguZPNq7qAVoz1oLuqeTtpbvrX1LoXQwML6S+hmxiZaSSH35voIH0tsfSDga1GCNtFtF2I1xDKMb\/jwWDqlw7ue3WnTZl51p1MQz4+PVC69\/MbvbdT7y+DNmN4wybRC8sRmVzNnreVIa6y\/1y2dIbk3B\/datWnNEieRGDFF6rWyw\/6bPlgWNGwWGR+9xuXmI6nmZ+gVe9Q7Vx2Xvi5mrkX4BUpl8mvOjKN9TI\/UtlufIvWXgj9INmLyeC2H5775uIKpOE229QiaPpDhtlP\/hbxJakfZbIn9YS3v+29YaNGWebKQGADeXFtyVautFmvBhXrh8raQkbt0S09TXNr52BdqlpPhp7RgnTvXsB2yuU1kdsRxH2dDMkP8B2+Gxm7t7Cfq4W5xs4JJu5W814E5UdqbJApR7mZF1jQ9nETmyBmLMsycda81yxdSFMMiCkmtKdyCxkK0vP7+xbd5UmYodigsPxTIpP6Uixv3ep0rQ0clK3\/FYzZhKJm2aY+mxvkTBjONcE71YjdGnztEERhjFqTMOedtE4lPDf4L8pRoudDmb8K5SJtskT2yUGLYbnf0jvDlvnTD9AvmiIluVL2c9Oy6FiOqN8aBqxQxkMJfMA+7Jysp\/q6wG7SPWJhQp2CNgjFEnNVV3I1slylvlN0jdEeQOndisx7xXoxoVcvdxYSZScgQX2k5Ei13yqWI+p2yYLS9kj1vE7nioe+qo7OMFbkj8WwyDBgIiNyp\/aINA2j52XaasxNSQUy8UMDXU04UwVdvLJesJzrQ++bygy74ryPCJlk1xZ3yJ+yX5OhGHyEh0t0\/w1ZqJLb4ZcTXwlLFpXz\/cUVwoRHPXfM5bKUnva6lQlfNReo0wpCwMUlSk8pP4h5X1kOxaZu9qHwSVF7KiihBSF35Q3MDDsmHJH46Uhk4wZblwTikUB+uPvHBmxEsO3NTgID5XH9v19eCX01cWCXd2zXmxLxxGtDrLLJWdruXHaP\/qUX6vH+ewg7um\/dNzBzruHQsSO+7rIMYVnoYrPNEu\/5IZNcCFmcaUmjjP3RE\/mpt7rfgPnEN38Iw6qhQSnEizkh1EpAt4SEv6Irdk8x8lzIsfODmlU3ATnaTeLrYrW25SRwYcjQ1T67X3bWYB+mVNLU6LCiZ1wmi\/Q4x8xL\/qh7LAiI1Tn7AvYdMHO22zpKywwMUQSg4nX4zJtLagIh9hAhott1alOQJeXXV77iMRi7PorPjCixfqR8rPWx6nU9HUtDvqVUgg7a1L1iL0MG3G9ToTe7Fswb1M5rEE4AOZDmq+Lxic9Hfqag2zMZWgyfU6RA1ZQcIFwhFTN\/W\/maT\/PCo26X\/+XrSzBO8Zt0QW9y3I3y5PG9Eo2YxvnOByjtPjC1ng9KQyOICUUnG71MQF8ZGpPbSrRgSYrBeCr2rGkE+iCvOJNPGadl42PgSpTtZYeyiD\/9+OA9MXNMHylFR9S4t9RKOk9\/vUFC0jL0Q98QtDWWr6+a0i4h5Lt823luW+EVb5ieb0uuqn5A7B3ber3LWbXIWXowpDR++UEAXdk7vSKCw4lIq+ONBgepkQ6mpxWPMFobE3QYDEsH9C\/VDTR4cftQnjQ\/9f2A7fyipnG4f4DzYE98bStPckaXrsLjSa+QzXMA8WeQ++Sve0JnOxkkrWG0myqdwaQSc1gCdnqjB31Q3e6EKUn0SV9r+nkBj+vO8BCoGDjK1iI0hg19i3fvdPF8UakaaXXIwSYr\/nyFxIH\/Pced2\/sSc1gnn7VuVoMx9WmvR3HnZaX0XtEmSAUP\/rnKcoHHXjnKT+Dv0+zeg4N\/SHQdE4qCSC8C7kOhn3rL+mtxgRikmB1fUt1fdHP3+fGLpXO3X3UDS2Kqo9ktYJA0UfQZzR50wYQgoCPfRoV3pdfqSJEm9lrtmJSse0kRXqOGKUe9RN\/E133J3V0HbP1q\/MRtKPdBI5jdu86LGhw+TjkEDaWpZBPSSHLAKwF\/t8QxoUUcwN7gc+zhZ0z9q75P23yc41JpmJrpx1RRQnw2TKUuH7idxs7FbAxoa4n8qrNIL3JWBLt2aCDOUTUubJ3yARzWQPLoxiFs5GX1oVSYQSLhEAXUL3jOzUC79gCuV4W\/4L\/RMHpBHLye3qBLIxMombmMT9UYoc9jwmaDavoeyQ+tYegkA+crfwNS8AMMLjGGY\/wV7gtU4YZdSksajt\/QUrGlKIitRhXORPzF0icXovYbbgXwvVnt8GhMdbALgQoTbHBeaj848fpCSW6YFah6JUqLjEj\/E32QinXwQtwx+EYtglBiiFJwH74tM\/q74Wb1ezqB\/gpCRAZyaXuk8rgzTm+OB+PTI6ztXKaof0dNQwBhm1IzQJEI8iCCxQt\/NC42mG6vT+FVFSp4ROuTTV9GU7P5b6tWvH0hxQBcOF1p3rMUIDeNvoijLYBUV64ki\/uuDzic0a5G\/dlu5fYSVlADh1Ybc3c\/CU6CyMxJP1Nt03hE9Tw\/WVvgRfxYt6UZ5Q5XClgrOGgo7XD7VYWv\/DxiZUfsxEi2tKDIRLXuaxV+Kbb2DTWquSTh\/O55I0a8iCgNpviRyIUUZ8bUdwmGBHblHxw+FGhTxOsSlj7RqffzRH\/TatYgHTiaVQ2n0unb9wu0F2+NEgyJECAZegdPCPUT+85sPk7wSeUus\/hpWCkMmuEar5GwUAcOOD+EhmxS9xTgop4ZReHg8kaqp6OrxsrJHmaK0m1qg2fSUEW9Q\/qnmfbwaImDnkpLv45K8u78sV0vlObr7ArMVWSHtnYQ6YmkN3+nMwSh3LcpeiT1vIDUnDCyIYl6cxkQIUU9N8RcoaHz8GpGQhEOJOZ1U6Hjw4FSyxhOHCp5+Phpms7yTCjxzPw+gxwBOoU02\/C6fY9J2HKnYk2rYg3ugarI\/EcWC2E1H+XEt9xBSvL75fijhv7TP0PZ29ruU96SiP7a8QlHrg3Z\/zmsJZT8QrfWp2yyVj1LnPB3kXPgxOACjFDP5hyJopJNzjMnNV\/cyHcUrOhG66of4xEW8bq+EoH7RJKyCWxDf+65pPGQyYY09tIgF5j\/N5gWBbGQhVGBOWh1iymvBk9C0JqJ7NEC3pGgIh7J+qRXTyYdiZ7jhdCMvTZVvVJNi8D6Bt+g3f7TolBjPRt2VcFudBs47w5p8LMZ3EML7wDaQkgSDcS6u7SiPzn+85lqDoyomnec22K3RUxnEqzxMA6cT2pQIeUjhyVoRWE8TITFtCIIolpYOqxkkgduFyBM+GuBKY=","os-build":"16G77"}';
	$CollectionBlob = '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>IngestBody</key>
	<data>
	'.substr(chunk_split(base64_encode($IngestBody), 68, "\n\t"), 0, -2).'
	</data>
	<key>X-Apple-Sig-Key</key>
	<string>BFaG3r+lZOk8PEfw16SlZDzp1OtgmPGwzm6tR8G5zmyMdv7UPkpgTjPu4UfARvRgvLaN7q5qbmV3jXJ9kIOFf2c=</string>
	<key>X-Apple-Signature</key>
	<string>MEUCIBaEbDeFBzC6eLc8akNfXYhWHOlOal5J40M5idwlzomXAiEAyp9YUoksBFWMvWem23It+vIh8ITFxvtzCM+kJXMxDRQ=</string>
</dict>
</plist>
';
	$request = '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>CollectionBlob</key>
	<data>
	'.substr(chunk_split(base64_encode($CollectionBlob), 68, "\n\t"), 0, -2).'
	</data>
	<key>HandshakeRequestMessage</key>
	<data>
	gic4oJIA7ntjKzdw7P6f6fS7zPKw
	</data>
	<key>UniqueDeviceID</key>
	<string>913ba72a0981d3352bf28ca98b3a4a856fe81959</string>
</dict>
</plist>
';
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/xml"));
	curl_setopt($ch, CURLOPT_URL, "https://albert.apple.com/deviceservices/drmHandshake");
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$result = curl_exec($ch);
	curl_close ($ch);
	if(empty($result)) die('Connection Error');
	return $result;
}
function reqTicket($activationinfo, $serverKP) {
	$privatekey = "-----BEGIN RSA PRIVATE KEY-----
MIICWwIBAAKBgQC/buX+a+Z/p7nW0fnFM9qLt4fK+9js1gH4SIXOgi9zjvq4krNm
5GaCZCeV+ieHbAgV21s2LD5L+OaiP0dTHa/pTdnOenaZb7J+k3ylI1DT5yRO/zpW
MCSaO6eesY3dlpQD2+uitdkRNBL9JdRagBUR16AlWBym58iNSMRjohNjiQIDAQAB
AoGAYO+lIxo8U/P41ODq24MQbaN37b9t7y/HO5RtvU6K/LcwGnqewcFybyCMMxPD
JH398iNropjwysO09f+Y/GuSAW+Rw1P9okq53XgweA9ep55XsZw9lQ7Ppo/0NjFh
KK6IIJ049g/sRvl+w22vE/Aq0X8e6nDECvF1H8n0RdMJwwkCQQDj2tSsEXBraMSh
Bq/m6JOvSm/c38SaD8PIgLTuiuVxDtYV+xonuis11Iru8H+s9Y9xnqyAvL54V7ov
LPjYQszbAkEA1xRaNBcjPmuTBXgCCuh3iTWPzY0d6wYIDfPYPQ246KZXL/8Km6YK
3kdbRO0BsbMk80tAxxgbfbYWa5eC7GCMawJAeL5Y3snrAfAl6pLpmfp7cHOIrcoi
m+VowZJ6zsHp7iyHhGRpKs474a285fuqHNSP7AzCqwHaCVmrvx4czDUx6wJANMSi
iOswU20zKgFShviX80r32BeKQpI9QacU443NUw8UjwsEwEFRo01ggB2h78YjB6nr
36zzeblF8OnATlw1twJAbAe85uwCWG1Kj6qWHpbH13d9suaNOZBNc3BtfJsKSJxm
2Z7lpkBUpp4Qxjs/tgu8ND3bS33PP1HVJtbEmJzZjA==
-----END RSA PRIVATE KEY-----";
	$FP = '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';
	$rsa = new Crypt_RSA();
	$rsa->loadKey($privatekey);
	$ActivationInfoXML = substr(chunk_split(base64_encode($activationinfo), 68, "\n\t"), 0, -2);
	$fairPlayCertChain = substr(chunk_split($FP, 68, "\n\t"), 0, -2);
	$FairPlaySignature = substr(chunk_split(base64_encode($rsa->sign($activationinfo)), 68, "\n\t"), 0, -2);
	$RKCertification = 'MIIC+gIBAjCCAvMEIP4C3sqQtP1S2hwBZzCoHcsoH2xNu5c+a4Q45oJ1MKF3BEEE49p6
	qBYsPi6d/EaTMJ9h8CQtrKGdYCTMu/aUR0LnaiUdo0JkEar8+XXJ4vhsNHCiMuyU+h8t
	QXjXE/njGvNLIgQQPuttwEZCap/KMxbpVPsr4gQQQROJYfLECM2dX0TdAYY9kQSCAmaQ
	0aymKAinfqfVLFHQnfOyNJQDCIWOnu0HWATd4l5xMqSEeFLtJg1fzTxCLUN0pft8fJ0+
	VjGAhLrjZxjyn6eO5RftvRFXg/yFfl5f0k9N8so1Dnhb7xJ4D1oFZXM7cQNOnyfZ2xmn
	dI6GyZTr6Org9oxWhV5jNpNNaIGWT2BkgncL1Oby0XLCp7lBArJdyWIilA0XZpWOGV0B
	qES4mAuqzSRdm7okvKuNunuEuTYA9i4N3qO1DWmpwR7+0YDYJsJcEav517+y2kDqUlEI
	+UNKt3mMpaC3jrGQRZ8yuvk+TLDoGM5BRF098+C1LO/1gzZfPZHVWxEQlIga2q+H6QIC
	eOuX6xcwDvGGrgje2gA7WA/PAhQO8aaOozQLX4Wc7H6SaEbfwbikAUi76UBLnAwxkQRC
	C5w+dnt0cm9/Tiu73o7ERQBB0Dh5JsnKfmFXYWHgCLTF05g8YzsSw5OmVwBRISgUZxHF
	PTYVy6WpJHhfjxO9N9JLawns4I0B14SZQhAQZeNqA21kTXWEEeSqLEy235qTElG3wqgA
	Skm5N2d3ypUty5Eryqfb0tZJD6nJGivrWin+CNQc7hspRw47d+Q0rd3MEgMptfcdk7Um
	GwqeL0hivVLSa9x2KMBiwgXNzs2NsyzkTKM5YIOyoNhnShLygAgE2KZU+NauQ/QA4LP+
	1RrJfkGcFuBbrVlw/3Jt5Wqang9Sg2dyqzu8QkkOfuQPElQlXcHDVlwyNgeWnySfTEtu
	0grCs4IwHBg3bFOpwNqHZNz1HWkqEen1Z9IyeyRPIgQkpxVbkV+XsDKC+ozc4X/GHiOj
	8A==
	</data>
	<key>RKSignature</key>
	<data>
	MEYCIQCXYwN2l9ICBMHzclN0wUaV9pueUOrR/79HHn516vSz2gIhAKJ0VP4GwcOsMvXS
	FL0dJj2mogjTyqOKmSmu2BOWSt+X
	</data>
	<key>serverKP</key>
	<data>
	'.$serverKP.'
	</data>
	<key>signActRequest</key>
	<data>
	eRqtvDpaCM0XNR19k8QFTQ==';

	$request_ticket='<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>ActivationInfoComplete</key>
	<true/>
	<key>ActivationInfoXML</key>
	<data>
	'.$ActivationInfoXML.'
	</data>
	<key>FairPlayCertChain</key>
	<data>
	'.$fairPlayCertChain.'
	</data>
	<key>FairPlaySignature</key>
	<data>
	'.$FairPlaySignature.'
	</data>
	<key>RKCertification</key>
	<data>
	'.$RKCertification.'
	</data>
</dict>
</plist>
';
	return($request_ticket);
}
function actAlbertTicket($activate) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/xml"));
	curl_setopt($ch, CURLOPT_URL, "https://albert.apple.com/deviceservices/deviceActivation");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, array('activation-info'=>$activate));
	$albert = curl_exec($ch);
	curl_close ($ch);
	return($albert);
}

function getHandShake() {
	$json = json_encode($_POST, 1);
	if (empty($_POST['blobXML'])) {
		echo "Please re-connect device to USB and retry.";
		exit();
	}
	$out = $_POST['blobXML'];

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/xml"));
	curl_setopt($ch, CURLOPT_URL, "https://albert.apple.com/deviceservices/drmHandshake");
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $out);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$result = curl_exec($ch);
	curl_close ($ch);

	$file = './var/www/requests/'.$_POST['SN'].'-drmHSK.txt';
	file_put_contents($file, "Device Request: ".$json."\r\nAlbert Respond: ".$result."\r\n");

	echo $result;
	
	if (empty($out)) {
		echo "Please re-connect device to USB and retry.";
		file_put_contents('tmp.txt', "Success:\n$json");
	} else {
		$data = json_encode($_POST, true);
		file_put_contents('tmp.txt', "Success:\n$out". $data);
	}

}

function get_client_ip() {
    if (isset($_SERVER['HTTP_CLIENT_IP'])) $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    elseif (isset($_SERVER['HTTP_X_FORWARDED'])) $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    elseif (isset($_SERVER['HTTP_FORWARDED'])) $ipaddress = $_SERVER['HTTP_FORWARDED'];
    elseif (isset($_SERVER['REMOTE_ADDR'])) $ipaddress = $_SERVER['REMOTE_ADDR'];
    else $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function boundary() {
    if (! isset($_SERVER['CONTENT_TYPE'])) return null;
    preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);
    if (empty($matches) || ! isset($matches[1])) return null;
    return $matches[1];
}

function ActivationRequestInfoXML($xml, $full=0) { $i=0;
	$xml = simplexml_load_string($xml);
	$keys = json_decode(json_encode($xml->dict->key),1);
	if(empty($keys)) { $keys = json_decode(json_encode($xml->key),1); $data = json_decode(json_encode($xml->data),1); }
	else {  $data = json_decode(json_encode($xml->dict->data),1); }
	if ($full) $narr[]	 = array_combine($keys , $data);
	foreach ($keys as $keyident => $keystore) { $keydata[$keystore] = $data[$keyident]; }
	$ActivationRequestInfoXML = base64_decode($keydata['ActivationInfoXML']);
	$DeviceCertRequest = json_decode(json_encode(simplexml_load_string($ActivationRequestInfoXML)->dict->data),1);
	$innXML = array("\t<false/>","\t<true/>","<data>","</data>","integer");
	$outXML = array("\t<string>false</string>","\t<string>true</string>","<string>","</string>","string");
	$ActivationRequestInfo = str_replace($innXML, $outXML, $ActivationRequestInfoXML);
	$xml = simplexml_load_string($ActivationRequestInfo);
	$tmpkey = json_decode(json_encode($xml->dict->dict[$i]->key),1);
	$tmpstr = json_decode(json_encode($xml->dict->dict[$i]->string),1);
	$narr[]	 = array_combine($tmpkey , $tmpstr);
	while ( $tmpkey ) { $i++;
		if(empty($xml->dict->dict[$i]->key)) { break; }
		if(!$tmpkey = json_decode(json_encode($xml->dict->dict[$i]->key),1)) { break; }
		$tmpstr = json_decode(json_encode($xml->dict->dict[$i]->string),1);
		$narr[]	 = array_combine($tmpkey , $tmpstr);
	}
	$narr[] = array('DeviceCertRequest' => $DeviceCertRequest[0]);
	$result = call_user_func_array('array_merge', $narr);
	return $result;
}

if (!function_exists('getallheaders')) {
	function getallheaders() {
		$headers = [];
		foreach ($_SERVER as $name => $value) {
			if (substr($name, 0, 5) == 'HTTP_') {
				$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        	}
		}
		return $headers;
	}
}

function AccountTokenCertificateApple() {
	return base64_encode('-----BEGIN CERTIFICATE-----
MIIDZzCCAk+gAwIBAgIBAjANBgkqhkiG9w0BAQUFADB5MQswCQYDVQQGEwJVUzET
MBEGA1UEChMKQXBwbGUgSW5jLjEmMCQGA1UECxMdQXBwbGUgQ2VydGlmaWNhdGlv
biBBdXRob3JpdHkxLTArBgNVBAMTJEFwcGxlIGlQaG9uZSBDZXJ0aWZpY2F0aW9u
IEF1dGhvcml0eTAeFw0wNzA0MTYyMjU1MDJaFw0xNDA0MTYyMjU1MDJaMFsxCzAJ
BgNVBAYTAlVTMRMwEQYDVQQKEwpBcHBsZSBJbmMuMRUwEwYDVQQLEwxBcHBsZSBp
UGhvbmUxIDAeBgNVBAMTF0FwcGxlIGlQaG9uZSBBY3RpdmF0aW9uMIGfMA0GCSqG
SIb3DQEBAQUAA4GNADCBiQKBgQDFAXzRImArmoiHfbS2oPcqAfbEv0d1jk7GbnX7
+4YUlyIfprzBVdlmz2JHYv1+04IzJtL7cL97UI7fk0i0OMY0al8a+JPQa4Ug611T
bqEt+njAmAkge3HXWDBdAXD9MhkC7T/9o77zOQ1oli4cUdzlnYWfzmW0PduOxuve
AeYY4wIDAQABo4GbMIGYMA4GA1UdDwEB/wQEAwIHgDAMBgNVHRMBAf8EAjAAMB0G
A1UdDgQWBBShoNL+t7Rz/psUaq/NPXNPH+/WlDAfBgNVHSMEGDAWgBTnNCouIt45
YGu0lM53g2EvMaB8NTA4BgNVHR8EMTAvMC2gK6AphidodHRwOi8vd3d3LmFwcGxl
LmNvbS9hcHBsZWNhL2lwaG9uZS5jcmwwDQYJKoZIhvcNAQEFBQADggEBAF9qmrUN
dA+FROYGP7pWcYTAK+pLyOf9zOaE7aeVI885V8Y/BKHhlwAo+zEkiOU3FbEPCS9V
tS18ZBcwD/+d5ZQTMFknhcUJwdPqqjnm9LqTfH/x4pw8ONHRDzxHdp96gOV3A4+8
abkoASfcYqvIRypXnbur3bRRhTzAs4VILS6jTyFYymZeSewtBubmmigo1kCQiZGc
76c5feDAyHb2bzEqtvx3WprljtS46QT5CR6YelinZnio32jAzRYTxtS6r3JsvZDi
J07+EHcmfGdpxwgO+7btW1pFar0ZjF9/jYKKnOYNyvCrwszhafbSYwzAG5EJoXFB
4d+piWHUDcPxtcc=
-----END CERTIFICATE-----
');
}
