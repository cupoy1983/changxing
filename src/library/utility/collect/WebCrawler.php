<?php

/**
 * 爬虫程序 -- 原型
 *
 * frankie
 */
class WebCrawler{
	
	private static $webCrawler = null;
	
	/**
	 * 私有构造器
	 */
	private function __construct(){
	
	}
	
	public static function getInstance(){
		if(self::$webCrawler == null){
			return new WebCrawler();
		}
		return self::$webCrawler;
	}
	
	/**
	 * 从给定的url获取html内容
	 *
	 * @param string $isGBK
	 *        	是否是GBK网页
	 * @param string $option
	 *        	http选项
	 * @return string
	 */
	function getUrlContent($options, $isGBK = false){
		// 初始化一个 cURL 对象
		$curl = curl_init();
		// 设置option
		curl_setopt_array($curl, $options);
		
		// 运行cURL，请求网页
		$data = curl_exec($curl);
		if($data === FALSE){
			echo "cURL Error: " . curl_error($curl);
		}
		
		// 关闭URL请求
		curl_close($curl);
		if($isGBK){
			$data = mb_convert_encoding($data, "UTF-8", "gbk");
		}
		
		return $data;
	}
	
	/**
	 * 修正相对路径
	 *
	 * @param string $base_url        	
	 * @param array $url_list        	
	 * @return array
	 */
	
	function reviseUrl($base_url, $url_item){
		$url_info = parse_url($base_url);
		$base_url = $url_info["scheme"] . '://';
		if(isset($url_info["user"]) && isset($url_info["pass"])){
			$base_url .= $url_info["user"] . ":" . $url_info["pass"] . "@";
		}
		$base_url .= $url_info["host"];
		if(isset($url_info["port"])){
			$base_url .= ":" . $url_info["port"];
		}
		$home_url = $base_url;
		$base_url .= $url_info["path"];
		if(preg_match('/^http/', $url_item)){
			// 已经是完整的url
			$result = $url_item;
		}else{
			// 不完整的url
			if(substr($url_item, 0, 1) == '/'){
				$real_url = $home_url . $url_item;
			}else{
				$real_url = $base_url . '/' . $url_item;
			}
			
			$result = $real_url;
		}
		return $result;
	}
}

?>