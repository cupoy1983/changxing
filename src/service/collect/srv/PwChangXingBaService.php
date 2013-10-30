<?php
defined('WEKIT_VERSION') || exit('Forbidden');

/**
 * 长兴吧采集服务类
 * @author frankie
 *
 */

class PwChangXingBaService{
	
	const DEFAULT_USER_PASSWORD = "312312";
	const DEFAULT_EMAIL_PREFIX = "cx";
	const DEFAULT_EMAIL_SUFFIX = "@changxingba.com";
	const DEFAULT_GROUP_ID = "0";
	const DEFAULT_CLIENT_IP = "127.0.0.1";
	const DEFAULT_FORUM_ID = 2;
	
	public function getUrls($url){
		Wind::import('LIB:utility.collect.phpQuery.phpQuery');
		Wind::import('LIB:utility.collect.WebCrawler');
		
		$crawler = WebCrawler::getInstance();
		$options = array(
			// 设置url
			CURLOPT_URL => $url,
			// 设置header
			CURLOPT_HEADER => false,
			// 设置http header
			CURLOPT_HTTPHEADER => array(
				"User-Agent: Mozilla/5.0 (Windows NT 6.1; rv:24.0) Gecko/20100101 Firefox/24.0"
			),
			// 返回字符串
			CURLOPT_RETURNTRANSFER => true
		);
		$content = $crawler->getUrlContent($options, true);
		$doc = phpQuery::newDocument($content);
		
		foreach(pq("#thread_list li .threadlist_title a") as $key => $u){
			$u = $crawler->reviseUrl($url, pq($u)->attr("href"));
			$data[$key] = $u;
		}
		return $data;
	}
	
	/**
	 * 根据url采集帖子
	 * @param $url
	 */
	public function doCollect($url){
		Wind::import('LIB:utility.collect.phpQuery.phpQuery');
		Wind::import('LIB:utility.collect.WebCrawler');
		$result = array();
		$result["success"] = true;
		
		//返回false, 帖子Id存在, 跳过该条贴子
		//返回帖子Id, 继续帖子采集
		$tieId = $this->_getTieBaId($url);
		if($tieId == false){
			$result["success"] = false;
			$result["info"] = "已采集";
			return $result;
		}
		
		$crawler = WebCrawler::getInstance();
		$options = array(
			// 设置url
			CURLOPT_URL => $url,
			// 设置header
			CURLOPT_HEADER => false,
			// 设置http header
			CURLOPT_HTTPHEADER => array(
				"User-Agent: Mozilla/5.0 (Windows NT 6.1; rv:24.0) Gecko/20100101 Firefox/24.0" 
			),
			// 返回字符串
			CURLOPT_RETURNTRANSFER => true 
		);
		$content = $crawler->getUrlContent($options, true);
		$doc = phpQuery::newDocument($content);
		$title = pq("#j_core_title_wrap .core_title_txt")->text();
		
		foreach(pq("#j_p_postlist .l_post") as $key => $element){
			$tid = null;
			$baidu = json_decode(pq($element)->attr("data-field"));
			$userName = $baidu->author->name;
			$createdTime = strtotime($baidu->content->date);
			$element = pq($element)->find(".d_post_content");
			
			//查询或者新增贴吧用户
			$user = Wekit::load('user.PwUser')->getUserByName($userName);
			if(empty($user)){
				$userId = $this->_addUser($userName);
			}else{
				$userId = $user["uid"];
			}
			//若该用户不存在，跳过整条贴子(或跟帖)
			if($key == 0 && $userId <= 0){
				$result["success"] = false;
				$result["info"] = "未知用户发帖";
				break;
			}elseif($userId <= 0){
				continue;
			}
			$user = new PwUserBo($userId);
			
			//删除帖子中的A标签
			pq($element)->find('a')->remove();
			
			//采集图片数据
			$imgs = array();
			foreach(pq($element)->find("img") as $pic){
				if(pq($pic)->attr("class") == "BDE_Image"){
					$tmp = $this->_downloadImage(pq($pic)->attr("src"), $title);
					if($tmp != false){
						$files["Filedata"]["name"] = $title . ".png";
						$files["Filedata"]["type"] = "application/octet-stream";
						$files["Filedata"]["tmp_name"] = $tmp;
						$files["Filedata"]["size"] = abs(filesize($tmp));
						$imgId = $this->_addImage($user, $files);
						//[attachment=1]
						$img = "[attachment=" . $imgId . "]";
						pq($pic)->replaceWith($img);
						$imgs[$imgId]["desc"] = mb_substr($title, 0, 7);
					}
				}
			}
			$data["title"] = $title;
			$data["content"] = pq($element)->html();
			$data["content"] = strip_tags(preg_replace('/<br\\s*?\/??>/i', "\r\n", $data["content"]));
			$data["created_time"] = $createdTime;
			
			//无图片并且内容长度小于4，跳过整条贴子(或跟帖)
			$none = empty($imgs) && (mb_strlen($data["content"], "UTF-8") < 4);
			if($none && $key == 0){
				$result["success"] = false;
				$result["info"] = "内容无效";
				break;
			}elseif($none){
				continue;
			}
			//第一条为thread，五条之内为post，超过五条跳出循环
			if($key == 0){
				$tid = $this->_post($user, $data, $imgs);
				//未成功插入thread，跳过该帖子
				if($tid == false){
					$result["success"] = false;
					$result["info"] = "发表帖子错误";
					break;
				}else{
					$data["tid"] = $tid;
				}
			}elseif($key <= 6){
				$this->_reply($user, $data, $imgs);
			}else{
				break;
			}
		}
		//释放document内存
		$doc->unloadDocument();
		
		//储存该帖子Id供下次采集进行重复判定
		$TieId = $this->_saveTieBaId($tieId);
		
		return $result;
	}
	
	//查询该帖子是否已采集
	//已采集 - false
	//未采集 - number http://tieba.baidu.com/p/2619377881
	private function _getTieBaId($url){
		$TieId = substr($url, strrpos($url, "/") + 1);
		
		$collected = $this->_getTiebaCollectedDao()->get($TieId);
		if(!empty($collected)){
			return false;	
		}else{
			return $TieId;
		}
	}
	
	//
	private function _saveTieBaId($id){
		$this->_getTiebaCollectedDao()->add(array("tie_id"=>$id));
	}
	
	//新增采集中不存在的用户
	private function _addUser($userName){
		Wind::import('SRC:service.user.dm.PwUserInfoDm');
		$dm = new PwUserInfoDm();
		$email = self::DEFAULT_EMAIL_PREFIX . substr(md5(Pw::getTime() . $userName . WindUtility::generateRandStr(8)), 10, 15) . self::DEFAULT_EMAIL_SUFFIX;
		$dm->setUsername($userName)->setPassword(self::DEFAULT_USER_PASSWORD)->setEmail($email)->setRegdate(Pw::getTime())->setRegip(self::DEFAULT_CLIENT_IP);
		$dm->setGroupid(self::DEFAULT_GROUP_ID);
		
		$groupService = Wekit::load('usergroup.srv.PwUserGroupsService');
		$memberid = $groupService->calculateLevel(0);
		$dm->setMemberid($memberid);
		
		$result = Wekit::load('user.PwUser')->addUser($dm);
		if($result instanceof PwError){
			return 0;
		}
		//添加站点统计信息
		Wind::import('SRV:site.dm.PwBbsinfoDm');
		$bbsDm = new PwBbsinfoDm();
		$bbsDm->setNewmember($dm->getField('username'))->addTotalmember(1);
		Wekit::load('site.PwBbsinfo')->updateInfo($bbsDm);
		return $result;
	}
	
	//将远程图片下载到临时目录
	private function _downloadImage($url, $name){
		$cachePath = $_SERVER['DOCUMENT_ROOT'] . "/data/tmp/";
		$name = substr(md5(Pw::getTime() . $name . WindUtility::generateRandStr(8)), 10, 15);
		$a = parse_url($url);
		$suffix = substr($a["path"], strrpos($a["path"], "."));
		$fileName = $name . $suffix;
		$options = array(
			// 设置url
			CURLOPT_URL => $url,
			//设置header
			CURLOPT_HEADER => false,
			//返回字符串
			CURLOPT_RETURNTRANSFER => true 
		);
		
		Wind::import('LIB:utility.collect.WebCrawler');
		$webCrawler = WebCrawler::getInstance();
		$content = $webCrawler->getUrlContent($options, false);
		
		if(! is_dir($cachePath)){
			mkdir($cachePath, 0777, true);
		}
		
		if(self::build_file($content, $cachePath . $fileName) == false){
			return false;
		}else{
			return $cachePath . $fileName;
		}
	}
	
	//将数据生成文件
	protected static function build_file($file, $filename){
		$write = @fopen($filename, "w");
		if($write == false){
			return false;
		}
		if(fwrite($write, $file) == false){
			return false;
		}
		if(fclose($write) == false){
			return false;
		}
		return true;
	}
	
	private function _addImage($user, $files){
		$fid = self::DEFAULT_FORUM_ID;
		
		Wind::import('SRV:upload.action.PwAttMultiUpload');
		Wind::import('LIB:upload.PwUpload');
		$bhv = new PwAttMultiUpload($user, $fid);
		
		$upload = new PwUpload($bhv);
		if(($result = $upload->check()) === true){
			$result = $upload->remoteExecute($files);
		}
		if($result !== true){
			return false;
		}else{
			$data = $bhv->getAttachInfo();
		}
		
		return $data["aid"];
	}
	
	private function _post($user, $data, $imgs){
		$fid = self::DEFAULT_FORUM_ID;
		Wind::import('SRV:forum.srv.PwPost');
		Wind::import('SRV:forum.srv.post.PwTopicPost');
		$postAction = new PwTopicPost($fid, $user);
		
		$postAction->setSpecial("default");
		$pwPost = new PwPost($postAction, $user);
		
		Wind::import('SRV:forum.srv.post.do.PwPostDoAtt');
		$pwPostAtt = new PwPostDoAtt($pwPost, $imgs);
		$pwPost->appendDo($pwPostAtt);
		
		$postDm = $pwPost->getDm();
		$postDm->setTitle($data["title"])->setContent($data["content"])->setCreatedTime($data["created_time"])->setReplyNotice(1);
		
		if(($result = $pwPost->execute($postDm)) !== true){
			return false;
		}else{
			return $pwPost->getNewId();
		}
	}

	private function _reply($user, $data, $imgs){
		$fid = self::DEFAULT_FORUM_ID;
		$tid = $data["tid"];
		Wind::import('SRV:forum.srv.PwPost');
		Wind::import('SRV:forum.srv.post.PwReplyPost');
		$postAction = new PwReplyPost($tid, $user);
		$pwPost = new PwPost($postAction, $user);
		
		Wind::import('SRV:forum.srv.post.do.PwPostDoAtt');
		$pwPostAtt = new PwPostDoAtt($pwPost, $imgs);
		$pwPost->appendDo($pwPostAtt);
	
		$title = "";
		$postDm = $pwPost->getDm();
		$postDm->setTitle($title)->setContent($data["content"])->setCreatedTime($data["created_time"]);
		if (($result = $pwPost->execute($postDm)) !== true) {
			return false;
		}
		$pid = $pwPost->getNewId();
	}
	
	private function _getTiebaCollectedDao() {
		return Wekit::loadDao ('SRV:collect.dao.PwTiebaCollectedDao');
	}
}