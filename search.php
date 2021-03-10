<?php
/*********************************************************************\
|                                          |
|---------------------------------------------------------------------|
| EXTREMLYMTORRENTS.WS HACKMASTER 2021
| along with this program; If not, see <http://www.gnu.org/licenses/> |
\*********************************************************************/
?>
<?php
class CodemonsterDlmSearchextremlymtorrents {
		private $url_domain = 'https://extremlymtorrents.ws';
		private $qurl = '/search/%s';
		public $preparedUrl = '';
		private $debug = false;
		private $tempFile = '/tmp/_DLM_extremlymtorrents.log';
       
        private function DebugLog($str) 
		{
			if ($this->debug==true) 
			{
				file_put_contents($this->tempFile,$str."\r\n\r\n",FILE_APPEND);
			}
        }

        public function __construct() 
		{
			if(file_exists($this->tempFile))
			{
				unlink($this->tempFile);
			}

			if ($this->debug==true) 
			{
				ini_set('display_errors', 0);
				ini_set('log_errors', 1);
				ini_set('error_log', $this->tempFile);
			}
			
			$this->qurl=$this->url_domain.$this->qurl;
        }

        public function prepare($curl, $query) 
		{
			$this->preparedUrl = sprintf($this->qurl, urlencode($query));
			$this->configureCurl($curl, $this->preparedUrl);                
        }

		public function parse($plugin, $response) 
		{
			$this->processResultList($plugin, $response);
        }
		
		/* Begin private methods */
		
		private function configureCurl($curl, $url)
		{
			$headers = array
				(
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*;q=0.8',
					'Accept-Language: en-us;q=0.7,en;q=0.3',
					'Accept-Encoding: deflate',
					'Accept-Charset: windows-1251,utf-8;q=0.7,*;q=0.7'
				);
				
				curl_setopt($curl, CURLOPT_HTTPHEADER,$headers); 
				curl_setopt($curl, CURLOPT_URL, $url);
				curl_setopt($curl, CURLOPT_FAILONERROR, 1);
				curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
				curl_setopt($curl, CURLOPT_TIMEOUT, 120);
				curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
				curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); 
		}
		
		public function executeCurl($url)
		{
			$curl = curl_init();
			$this->configureCurl($curl, $url);
			$content = curl_exec($curl);
			curl_close($curl);
			//$this->DebugLog($content);
			return $content;
		}
		
		private function processResultList($plugin, $response)
		{
            $regex_records = '<tr.*>(.*)<\/tr>';
			$regex_bitTorrentInfoHash = 'btih:(.*)&';
			
			if(preg_match_all("/$regex_records/Us", $response, $recordDetails, PREG_SET_ORDER)) 
			{
				foreach ($recordDetails as $recordDetail)
				{
					$regex_getFields = '<td.*<\/td>.*<a.*href="(.*)".*>(.*)<\/a>.*<\/td>.*<td.*>.*<a href="(magnet.*btih:(.*)&.*)".*<\/td>.*<td.*">(.*)<\/td>.*<td.*">(.*)<\/td>.*<td.*>.*>(\d*)<.*<\/td>';

					if(preg_match_all("/$regex_getFields/Us", $recordDetail[1], $data, PREG_SET_ORDER))
					{
						foreach ($data as $torrentData)
						{
							$recordToAdd = array();

							$recordToAdd["page"] = $this->url_domain.$torrentData[1]; //1. url
							$recordToAdd["seeds"] = $torrentData[7]; //2. seeds
							$recordToAdd["leechs"] = $torrentData[7]; //3. leechs
							$recordToAdd["datetime"] = $this->getDate($torrentData[6]); //4. date
							$recordToAdd["title"] = $torrentData[2]; //5. title
							$recordToAdd["download"] = $torrentData[3]; //6. magnet
							$recordToAdd["category"] = "TV shows"; //7. category

							if(preg_match_all("/(.*) (.*)/", $torrentData[5], $sizes, PREG_SET_ORDER)) 
							{
								foreach($sizes as $size) 
								{
									$recordToAdd["size"] = floor($this->getSize($size[1], $size[2])); //8. size
								}
							}
							else
							{
								$recordToAdd["size"] = 0;
							}

							$recordToAdd["hash"] = $torrentData[4]; //9. hash

							// $output = implode(', ', array_map(
							// 	function ($v, $k) { return sprintf("%s='%s'", $k, $v); },
							// 	$recordToAdd,
							// 	array_keys($recordToAdd)
							// ));
							// $this->DebugLog($output);
							
							$this->addItemToPlugin($plugin, $recordToAdd);
						}
					}					
				}
			}
		}

		private function getDate($dateString)
		{
			// casting various date formats to synology-friendly as UTC
			
			// hours
			$hours = 0;
			if (preg_match('/(\d*)h/i', $dateString, $dateparts)==1)
			{
				$hours = $dateparts[1];
			}
			
			// days
			$days = 0;
			if (preg_match('/(\d*)d/i', $dateString, $dateparts)==1)
			{
				$days = $dateparts[1];
			}

			// week
			$weeks = 0;
			if (preg_match('/(\d*) week/i', $dateString, $dateparts)==1)
			{
				$weeks = $dateparts[1];
			}

			// month
			$months = 0;
			if (preg_match('/(\d*) mo/i', $dateString, $dateparts)==1)
			{
				$months = $dateparts[1];
			}

			// year
			$years = 0;
			if (preg_match('/(\d*) year/i', $dateString, $dateparts)==1)
			{
				$years = $dateparts[1];
			}

			return date('Y-m-d', strtotime('-'.$hours.' hours, -'.$days.' days, -'.$weeks.' week, -'.$months.' month, -'.$years.' year'));
		}
		
		private function getSize($size, $size_dim)
		{
			switch ($size_dim)
			{
				 case 'KB':
					 return $size * 1024;
				 case 'MB':
					 return $size * 1024 * 1024;
				 case 'GB': 
					 return $size * 1024 * 1024 * 1024;
			}
			return $size;
		}

		private function addItemToPlugin($plugin, $recordToAdd)
		{
			if (array_key_exists('title', $recordToAdd) && strlen($recordToAdd["title"]) > 0) 
			{
				$plugin->addResult($recordToAdd["title"],
					$recordToAdd["download"],
					(float)$recordToAdd["size"],
					date('Y-m-d',strtotime(str_replace("'", "", $recordToAdd["datetime"]))),
					$recordToAdd["page"],
					$recordToAdd["hash"],
					(int)$recordToAdd["seeds"],
					(int)$recordToAdd["leechs"],
					$recordToAdd["category"]);
			}
		}
		
		/* End private methods */
}
?>
