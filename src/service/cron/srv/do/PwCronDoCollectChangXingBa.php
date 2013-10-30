<?php
/**
 * 长兴贴吧采集时间程序
 */

Wind::import('SRV:cron.srv.base.AbstractCronBase');

class PwCronDoCollectChangXingBa extends AbstractCronBase{
	
	public function run($cronId) {
		$urls = $this->_getChangXingBaService()->getUrls("http://tieba.baidu.com/f?kw=%B3%A4%D0%CB&tp=0&pn=100");
		foreach($urls as $url){
			$result = $changxingba = $this->_getChangXingBaService()->doCollect($url);
			if($result["success"]){
				sleep(1);
			}
		}
	}
	
	private function _getChangXingBaService() {
		return Wekit::load('collect.srv.PwChangXingBaService');
	}
}
?>