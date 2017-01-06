<?php

/***************************************************************************
 *   Copyright (C) 2009-2011 by Geo Varghese(www.seopanel.in)  	   *
 *   sendtogeo@gmail.com   												   *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU General Public License as published by  *
 *   the Free Software Foundation; either version 2 of the License, or     *
 *   (at your option) any later version.                                   *
 *                                                                         *
 *   This program is distributed in the hope that it will be useful,       *
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of        *
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         *
 *   GNU General Public License for more details.                          *
 *                                                                         *
 *   You should have received a copy of the GNU General Public License     *
 *   along with this program; if not, write to the                         *
 *   Free Software Foundation, Inc.,                                       *
 *   59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.             *
 ***************************************************************************/

# class defines all moz api controller functions
class MozController extends Controller{
	
	// function to get moz rank
	function __getMozRankInfo ($urlList = array(), $accessID = "", $secretKey = "", $returnLog = false) {
		$mozRankList = array();
		
		if (SP_DEMO && !empty($_SERVER['REQUEST_METHOD'])) return $mozRankList;
		
		if (empty($urlList)) return $mozRankList;
		
		// Get your access id and secret key here: https://moz.com/products/api/keys
		$accessID = !empty($accessID) ? $accessID : SP_MOZ_API_ACCESS_ID;
		$secretKey = !empty($secretKey) ? $secretKey : SP_MOZ_API_SECRET;
		
		// if empty no need to crawl
		if (empty($accessID) || empty($secretKey)) return $mozRankList;
		
		// Set your expires times for several minutes into the future.
		// An expires time excessively far in the future will not be honored by the Mozscape API.
		$expires = time() + 300;
		
		// Put each parameter on a new line.
		$stringToSign = $accessID."\n".$expires;
		
		// Get the "raw" or binary output of the hmac hash.
		$binarySignature = hash_hmac('sha1', $stringToSign, $secretKey, true);
		
		// Base64-encode it and then url-encode that.
		$urlSafeSignature = urlencode(base64_encode($binarySignature));
		
		// Add up all the bit flags you want returned.
		// Learn more here: https://moz.com/help/guides/moz-api/mozscape/api-reference/url-metrics
		$cols = "103079231488";
		
		// Put it all together and you get your request URL.
		$requestUrl = SP_MOZ_API_LINK . "/url-metrics/?Cols=".$cols."&AccessID=".$accessID."&Expires=".$expires."&Signature=".$urlSafeSignature;
		
		// Put your URLS into an array and json_encode them.
		$encodedDomains = json_encode($urlList);
		
		$spider = new Spider();
		$spider->_CURLOPT_POSTFIELDS = $encodedDomains;
		$ret = $spider->getContent($requestUrl);
		
		// parse rank from the page
		if (!empty($ret['page'])) {
			$rankList = json_decode($ret['page']);
			
			// if no errors occured
			if (empty($rankList->error_message)) {
			
				// loop through rank list
				foreach ($rankList as $rankInfo) {
					
					$mozRankInfo = array(
						'moz_rank' => round($rankInfo->umrp, 2),
						'domain_authority' => round($rankInfo->pda, 2),
						'page_authority' => round($rankInfo->upa, 2),
					);
					
					$mozRankList[] = $mozRankInfo;
				}
				
			} else {
				$crawlInfo['crawl_status'] = 0;
				$crawlInfo['log_message'] = $rankList->error_message;
			}
			
		} else {
			$crawlInfo['crawl_status'] = 0;
			$crawlInfo['log_message'] = $ret['errmsg'];
		}
		
		// update crawl log
		$crawlLogCtrl = new CrawlLogController();
		$crawlInfo['crawl_type'] = 'rank';
		$crawlInfo['ref_id'] = $encodedDomains;
		$crawlInfo['subject'] = "moz";
		$crawlLogCtrl->updateCrawlLog($ret['log_id'], $crawlInfo);
	
		return $returnLog ? array($mozRankList, $crawlInfo) : $mozRankList;
	}	
	
}
?>