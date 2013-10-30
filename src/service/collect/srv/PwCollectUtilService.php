<?php
namespace admin\Lib\Service;

use \UploadFile as UploadFile;

require_once ($_SERVER['DOCUMENT_ROOT'] . "/thirdpart/phpQuery/phpQuery.php");

class CommonService{
	// 删除图片
	public function deleteImg(){
		$id = intval($_REQUEST['id']);
		if($id == 0)
			exit();
		
		$field = trim($_REQUEST['field']);
		if(empty($field))
			exit();
		
		$result = array(
				'isErr' => 0,
				'content' => '' 
		);
		
		$rel_mod = trim($_REQUEST['rel_mod']);
		if(! empty($rel_mod))
			$name = $rel_mod;
		else
			$name = $this->getActionName();
		
		$model = D($name);
		$pk = $model->getPk();
		$img = $model->where($pk . ' = ' . $id)->getField($field);
		
		if(! empty($img))
			@unlink(FANWE_ROOT . $img);
		
		if(false !== $model->where($pk . ' = ' . $id)->setField($field, '')){
			$result['content'] = $val;
		}else{
			$result['isErr'] = 1;
		}
		
		die(json_encode($result));
	}
	
	/**
	 * 上传图片的通公基础方法
	 *
	 * @param integer $water
	 *        	0:不加水印 1:打印水印
	 * @param string $dir
	 *        	上传的文件夹
	 * @param bool $is_thumb
	 *        	是否保存为缩略图
	 * @return array
	 */
	protected function uploadImages($dir = 'images', $is_thumb = false, $whs = array()){
		$upload = new UploadFile();
		
		// 设置上传文件大小
		$max_upload = intval(fanweC('MAX_UPLOAD'));
		
		if($max_upload > 0)
			$upload->maxSize = $max_upload * 1024; /* 配置于config */
			
		// 设置上传文件类型
		$upload_exts = fanweC('ALLOW_UPLOAD_EXTS');
		if(! empty($upload_exts))
			$upload->allowExts = explode(',', fanweC('ALLOW_UPLOAD_EXTS')); /* 配置于config */
		
		if($is_thumb){
			$upload->thumb = true;
			if($width > 0)
				$upload->thumbMaxWidth = $width;
			else
				$upload->thumbMaxWidth = $width;
		}
		
		if($is_thumb)
			$save_rec_Path = "./public/upload/" . $dir . "/" . toDate(gmtTime(), 'Ym/d') . "/origin/"; // 上传至服务器的相对路径
		else
			$save_rec_Path = "./public/upload/" . $dir . "/" . toDate(gmtTime(), 'Ym/d') . "/"; // 上传至服务器的相对路径
		
		$save_path = FANWE_ROOT . $save_rec_Path; // 绝对路径
		
		if(! is_dir($save_path))
			mk_dir($save_path);
		
		$upload->saveRule = "uniqid"; // 唯一
		$upload->savePath = $save_path;
		
		if($upload->uploadAll()){
			$upload_list = $upload->getUploadFileInfo();
			foreach($upload_list as $k => $file_item){
				if($is_thumb)				// 生成缩略图时
				{
					$file_name = $file_item['savepath'] . $file_item['savename']; // 上图原图的地址
					                                                              
					// 开始缩放处理产品大图
					if(isset($whs['big_width']))
						$big_width = $whs['big_width'];
					else
						$big_width = fanweC("BIG_WIDTH");
					
					if(isset($whs['big_height']))
						$big_height = $whs['big_height'];
					else
						$big_height = fanweC("BIG_HEIGHT");
					
					$big_save_path = str_replace("origin", "big", $save_path); // 大图存放图径
					
					if(! is_dir($big_save_path))
						mk_dir($big_save_path);
					
					$big_file_name = str_replace("origin", "big", $file_name);
					
					$big_save_path = str_replace("origin", "big", $savePath); // 大图存放图径
					if(! is_dir($big_save_path)){
						mk_dir($big_save_path);
					}
					$big_file_name = str_replace("origin", "big", $file_name);
					
					if(fanweC("AUTO_GEN_IMAGE") == 1)
						Image::thumb($file_name, $big_file_name, '', $big_width, $big_height);
					else
						@copy($file_name, $big_file_name);
						
						// 开始缩放处理产品小图
					if(isset($whs['small_width']))
						$small_width = $whs['small_width'];
					else
						$small_width = fanweC("SMALL_WIDTH");
					
					if(isset($whs['small_height']))
						$small_height = $whs['small_height'];
					else
						$small_height = fanweC("SMALL_HEIGHT");
					
					$small_save_path = str_replace("origin", "small", $save_path); // 小图存放图径
					
					if(! is_dir($small_save_path))
						mk_dir($small_save_path);
					
					$small_file_name = str_replace("origin", "small", $file_name);
					Image::thumb($file_name, $small_file_name, '', $small_width, $small_height);
					
					$big_save_rec_Path = str_replace("origin", "big", $save_rec_Path); // 大图存放的相对路径
					$small_save_rec_Path = str_replace("origin", "small", $save_rec_Path); // 大图存放的相对路径
					
					$upload_list[$k]['recpath'] = $save_rec_Path;
					$upload_list[$k]['big_recpath'] = $big_save_rec_Path;
					$upload_list[$k]['small_recpath'] = $small_save_rec_Path;
				}else{
					$upload_list[$k]['recpath'] = $save_rec_Path;
					$file_name = $file_item['savepath'] . $file_item['savename'];
				}
			}
			
			return $upload_list;
		}else{
			return false;
		}
	}
	
	public function uploadRemoteImage($url, $dir = 'images'){
		$upload = new UploadFile();
		
		// 设置上传文件类型
		$upload_exts = fanweC('ALLOW_UPLOAD_EXTS');
		if(! empty($upload_exts)){
			$upload->allowExts = explode(',', fanweC('ALLOW_UPLOAD_EXTS')); /* 配置于config */
		}
		
		$save_rec_Path = "/public/upload/" . $dir . "/" . toDate(gmtTime(), 'Ym/d') . "/"; // 上传至服务器的相对路径
		$save_path = FANWE_ROOT . $save_rec_Path; // 绝对路径
		
		if(! is_dir($save_path)){
			mk_dir($save_path);
		}
		
		$upload->resourceUrl = $url;
		$upload->saveRule = "uniqid"; // 唯一
		$upload->savePath = $save_path;
		$fileName = $upload->uploadRemote();
		if($fileName){
			return $save_rec_Path . $fileName;
		}else{
			return $url;
		}
	}
}

?>