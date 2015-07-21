<?php

/**
 * 组织架构模块岗位控制器文件
 *
 * @author banyanCheung <banyan@ibos.com.cn>
 * @link http://www.ibos.com.cn/
 * @copyright Copyright &copy; 2012-2013 IBOS Inc
 */
/**
 * 组织架构模块岗位控制器类
 * 
 * @package application.modules.dashboard.controllers
 * @author banyanCheung <banyan@ibos.com.cn>
 * @version $Id: PositionController.php 4553 2014-11-18 05:46:11Z zhangrong $
 */

namespace application\modules\dashboard\controllers;

use application\core\utils\Cache as CacheUtil;
use application\core\utils\Convert;
use application\core\utils\Env;
use application\core\utils\IBOS;
use application\core\utils\Org;
use application\core\utils\Page;
use application\core\utils\String;
use application\modules\position\model\Position;
use application\modules\position\model\PositionRelated;
use application\modules\position\model\PositionResponsibility;
use application\modules\position\utils\Position as PositionUtil;
use application\modules\user\model\User;
use application\modules\user\utils\User as UserUtil;

class PositionController extends OrganizationBaseController {

	/**
	 *
	 * @var string 下拉列表中的<option>格式字符串 
	 */
	public $selectFormat = "<option value='\$catid' \$selected>\$spacer\$name</option>";

	/**
	 * 浏览操作
	 * @return void
	 */
	public function actionIndex() {
		$catId = intval( Env::getRequest( 'catid' ) );
		// 搜索处理
		if ( Env::submitCheck( 'search' ) ) {
			$key = $_POST['keyword'];
			$list = Position::model()->fetchAll( "`posname` LIKE '%{$key}%'" );
		} else {
			$catContidion = empty( $catId ) ? '' : "catid = {$catId}";
			$count = Position::model()->count( $catContidion );
			$pages = Page::create( $count );
			$list = Position::model()->fetchAllByCatId( $catId, $pages->getLimit(), $pages->getOffset() );
			$data['pages'] = $pages;
		}
		// 岗位人数，不再用数据库的number字段 ////by hzh
		foreach ( $list as $k => $pos ) {
			$list[$k]['num'] = User::model()->countNumsByPositionId( $pos['positionid'] );
		}
		$data['catid'] = $catId;
		$catData = PositionUtil::loadPositionCategory();
		$data['catData'] = $catData;
		$data['list'] = $list;
		$data['category'] = String::getTree( $catData, $this->selectFormat );
		$this->setPageTitle( IBOS::lang( 'Position manager' ) );
		$this->setPageState( 'breadCrumbs', array(
			array( 'name' => IBOS::lang( 'Organization' ), 'url' => $this->createUrl( 'department/index' ) ),
			array( 'name' => IBOS::lang( 'Position manager' ) )
		) );
		$this->render( 'index', $data, false, array( 'category' ) );
	}

	/**
	 * 新增操作
	 * @return void 
	 */
	public function actionAdd() {
		if ( Env::submitCheck( 'posSubmit' ) ) {
			// 获取基本数据
			if ( isset( $_POST['posname'] ) ) {
				$data["posname"] = $_POST['posname'];
				$data["sort"] = $_POST['sort'];
				$data["catid"] = intval( Env::getRequest( 'catid' ) );
				$data["goal"] = ''; // 岗位说明，已去掉
				$data["minrequirement"] = ''; // 最低要求，已去掉
			}
			// 获取插入ID，以便后续处理
			$newId = Position::model()->add( $data, true );
			CacheUtil::update( 'position' );
			$newId && Org::update();
			$this->success( IBOS::lang( 'Save succeed', 'message' ), $this->createUrl( 'position/edit', array( 'op' => 'member', 'id' => $newId ) ) );
		} else {
			// 分类ID （如果有）
			$catid = intval( Env::getRequest( 'catid' ) );
			// 岗位分类缓存
			$catData = PositionUtil::loadPositionCategory();
			$data['category'] = String::getTree( $catData, $this->selectFormat, $catid );
			$this->render( 'add', $data );
		}
	}

	/**
	 * 岗位编辑
	 * @return void
	 */
	public function actionEdit() {
		$id = Env::getRequest( 'id' );
		if ( Env::getRequest( 'op' ) == 'member' ) {
			$this->member();
		} else {
			if ( Env::submitCheck( 'posSubmit' ) ) {
				if ( isset( $_POST['posname'] ) ) {
					$data["posname"] = $_POST['posname'];
					$data["sort"] = $_POST['sort'];
					$data["catid"] = intval( Env::getRequest( 'catid' ) );
					$data["goal"] = ''; // 岗位说明，已去掉
					$data["minrequirement"] = ''; // 最低要求，已去掉
					Position::model()->modify( $id, $data );
				}

				// 新增成员
				if ( isset( $_POST['member'] ) ) {
					UserUtil::setPosition( $id, $_POST['member'] );
				}
				CacheUtil::update( 'position' );
				Org::update();
				$this->success( IBOS::lang( 'Save succeed', 'message' ), $this->createUrl( 'position/index' ) );
			} else {
				$pos = Position::model()->fetchByPk( $id );
				$data['id'] = $id;
				$data['pos'] = $pos;
				// 岗位分类缓存
				$catData = PositionUtil::loadPositionCategory();
				$data['category'] = String::getTree( $catData, $this->selectFormat, $pos['catid'] );

				$this->render( 'edit', $data );
			}
		}
	}

	/**
	 * 删除操作
	 * @return void 
	 */
	public function actionDel() {
		if ( IBOS::app()->request->getIsAjaxRequest() ) {
			$id = Env::getRequest( 'id' );
			$ids = explode( ',', trim( $id, ',' ) );
			foreach ( $ids as $positionId ) {
				// 删除岗位
				Position::model()->deleteByPk( $positionId );
				// 删除岗位对应授权
				IBOS::app()->authManager->removeAuthItem( $positionId );
				// 删除岗位职责
				PositionResponsibility::model()->deleteAll( '`positionid` = :positionid', array( ':positionid' => $positionId ) );
				// 删除辅助岗位关联
				PositionRelated::model()->deleteAll( 'positionid = :positionid', array( ':positionid' => $positionId ) );
				$relatedIds = User::model()->fetchUidByPosId( $positionId );
				// 更新用户岗位信息
				if ( !empty( $relatedIds ) ) {
					User::model()->updateByUids( $relatedIds, array( 'positionid' => 0 ) );
				}
			}
			CacheUtil::update( 'position' );
			// 更新组织架构
			Org::update();
			$this->ajaxReturn( array( 'isSuccess' => true ), 'json' );
		}
	}

	/**
	 * 成员
	 */
	public function member() {
		$id = Env::getRequest( 'id' );
		if ( !empty( $id ) ) {
			if ( Env::submitCheck( 'postsubmit' ) ) {
				$member = Env::getRequest( 'member' );
				UserUtil::setPosition( $id, $member );
				$this->success( IBOS::lang( 'Save succeed', 'message' ) );
			} else {
				// 该岗位下人员
				$uids = User::model()->fetchUidByPosId( $id, false );
				// 搜索处理
				if ( Env::submitCheck( 'search' ) ) {
					$key = $_POST['keyword'];
					$uidStr = implode( ',', $uids );
					$users = User::model()->fetchAll( "`realname` LIKE '%{$key}%' AND FIND_IN_SET(`uid`, '{$uidStr}')" );
					$pageUids = Convert::getSubByKey( $users, 'uid' );
				} else {
					$count = count( $uids );
					$pages = Page::create( $count, self::MEMBER_LIMIT );
					$offset = $pages->getOffset();
					$limit = $pages->getLimit();
					$pageUids = array_slice( $uids, $offset, $limit );
					$data['pages'] = $pages;
				}
				$data['id'] = $id;
				// for input
				$data['uids'] = $uids;
				// for js
				$data['uidString'] = '';
				foreach ( $uids as $uid ) {
					$data['uidString'] .= "'u_" . $uid . "',";
				}
				$data['uidString'] = trim( $data['uidString'], ',' );
				// 当前页要显示的uid（只作显示，并不为实际表单提交数据）
				$data['pageUids'] = $pageUids;
				$this->render( 'member', $data );
			}
		} else {
			$this->error( '该岗位不存在或已删除！' );
		}
	}

}
