<?php
Wind::import('SRC:library.base.PwBaseDao');

/**
 * 贴吧采集标记DAO
 * @author frankie
 *
 */
class PwTiebaCollectedDao extends PwBaseDao {
	
	protected $_table = 'tieba_collected';
	protected $_pk = 'id';
	protected $_dataStruct = array('id','tie_id');
	
	public function add($data){
		return $this->_add($data);
	}
	
	public function get($tieId) {
		$sql = $this->_bindSql('SELECT * FROM %s WHERE tie_id = ? ' ,$this->getTable());
		$smt = $this->getConnection()->createStatement($sql);
		return $smt->getOne(array($tieId));
	}
	
}