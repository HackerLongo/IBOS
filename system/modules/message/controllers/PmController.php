<?php

namespace application\modules\message\controllers;

use application\core\utils\Env;
use application\core\utils\IBOS;
use application\core\utils\Page;
use application\core\utils\String;
use application\modules\message\model\MessageContent;
use application\modules\message\model\MessageUser;
use application\modules\user\model\User;

class PmController extends BaseController {

    /**
     * 私信列表页
     * @return void 
     */
    public function actionIndex() {
        $uid = IBOS::app()->user->uid;
        // 设置已读(右上角提示去掉)
        MessageUser::model()->setMessageIsRead( $uid, Env::getRequest( 'id' ), 1 );
        // 获取有多少个未读新对话
        $unreadCount = MessageContent::model()->countUnreadList( $uid );
        $pageCount = MessageContent::model()->countMessageListByUid( $uid, array( MessageContent::ONE_ON_ONE_CHAT, MessageContent::MULTIPLAYER_CHAT ) );
        $pages = Page::create( $pageCount );
        $list = MessageContent::model()->fetchAllMessageListByUid( $uid, array( MessageContent::ONE_ON_ONE_CHAT, MessageContent::MULTIPLAYER_CHAT ), $pages->getLimit(), $pages->getOffset() );
        $data = array(
            'list' => $list,
            'pages' => $pages,
            'unreadCount' => $unreadCount
        );
        $this->setPageTitle( IBOS::lang( 'PM' ) );
        $this->setPageState( 'breadCrumbs', array(
            array( 'name' => IBOS::lang( 'Message center' ), 'url' => $this->createUrl( 'mention/index' ) ),
            array( 'name' => IBOS::lang( 'PM' ) )
        ) );
        $this->render( 'index', $data );
    }

    /**
     * 私信详情页
     */
    public function actionDetail() {
        $uid = IBOS::app()->user->uid;
        $message = MessageContent::model()->isInList( String::filterCleanHtml( Env::getRequest( 'id' ) ), $uid, true );
        // 验证数据
        if ( empty( $message ) ) {
            $this->error( IBOS::lang( 'Private message not exists' ) );
        }
        $message['user'] = MessageUser::model()->getMessageUsers( String::filterCleanHtml( Env::getRequest( 'id' ) ), 'uid' );
        $message['to'] = array();
        // 添加发送用户ID
        foreach ( $message['user'] as $v ) {
            $uid != $v['uid'] && $message['to'][] = $v;
        }
        // 设置信息已读(私信列表页去掉new标识)
        MessageUser::model()->setMessageIsRead( $uid, Env::getRequest( 'id' ), 0 );
        $message['sinceid'] = MessageContent::model()->getSinceMessageId( $message['listid'], $message['messagenum'] );
        $this->setTitle( '与' . $message['to'][0]['user']['realname'] . '的私信对话' );
        $this->setPageTitle( IBOS::lang( 'Detail pm' ) );
        $this->setPageState( 'breadCrumbs', array(
            array( 'name' => IBOS::lang( 'Message center' ), 'url' => $this->createUrl( 'mention/index' ) ),
            array( 'name' => IBOS::lang( 'PM' ), 'url' => $this->createUrl( 'pm/index' ) ),
            array( 'name' => IBOS::lang( 'Detail pm' ) )
        ) );
        $this->render( 'detail', array( 'message' => $message, 'type' => intval( $_GET['type'] ) ) );
    }

    /**
     * ajax加载私信列表内容
     * @return void
     */
    public function actionLoadMessage() {
        $message = MessageContent::model()->fetchAllMessageByListId( intval( $_POST['listid'] ), IBOS::app()->user->uid, intval( Env::getRequest( 'sinceid' ) ), intval( Env::getRequest( 'maxid' ) ) );
        foreach ( $message['data'] as $key => $value ) {
            $message['data'][$key]['fromuser'] = User::model()->fetchByUid( $value['fromuid'] );
        }
        $data = array(
            'type' => intval( $_POST['type'] ),
            'message' => $message,
            'uid' => IBOS::app()->user->uid
        );
        $message['data'] = $message['data'] ? $this->renderPartial( 'message', $data, true ) : "";
        $this->ajaxReturn( $message );
    }

    /**
     * 回复操作
     * @return void
     */
    public function actionReply() {
        $_POST['replycontent'] = String::filterCleanHtml( $_POST['replycontent'] );
        $_POST['id'] = intval( $_POST['id'] );

        if ( !$_POST['id'] || empty( $_POST['replycontent'] ) ) {
            $this->ajaxReturn( array( 'IsSuccess' => false, 'data' => IBOS::lang( 'Message content cannot be empty' ) ) );
        }
        $res = MessageContent::model()->replyMessage( $_POST['id'], $_POST['replycontent'], IBOS::app()->user->uid );
        if ( $res ) {
            $this->ajaxReturn( array( 'IsSuccess' => true, 'data' => IBOS::lang( 'Private message send success' ) ) );
        } else {
            $this->ajaxReturn( array( 'IsSuccess' => false, 'data' => IBOS::lang( 'Private message send fail' ) ) );
        }
    }

    /**
     * Ajax发送私信
     * @return void 
     */
    public function actionPost() {
        if ( Env::submitCheck( 'formhash' ) ) {
            $return = array( 'data' => IBOS::lang( 'Operation succeed', 'message' ), 'IsSuccess' => true );
            // 后台再次安全验证
            if ( empty( $_POST['touid'] ) ) {
                $return['data'] = IBOS::lang( 'Message receiver cannot be empty' );
                $return['IsSuccess'] = false;
                $this->ajaxReturn( $return );
            }
            if ( trim( String::filterCleanHtml( $_POST['content'] ) ) == '' ) {
                $return['data'] = IBOS::lang( 'Message content cannot be empty' );
                $return['IsSuccess'] = false;
                $this->ajaxReturn( $return );
            }
            // --------------
            $_POST['touid'] = implode( ',', String::getUid( $_POST['touid'] ) );
            // Todo::发信人数检查?
            if ( isset( $_POST['type'] ) ) {
                !in_array( $_POST['type'], array( MessageContent::ONE_ON_ONE_CHAT, MessageContent::MULTIPLAYER_CHAT ) ) && $_POST['type'] = null;
            } else {
                $_POST['type'] = null;
            }
            $_POST['content'] = String::filterDangerTag( $_POST['content'] );
            $res = MessageContent::model()->postMessage( $_POST, IBOS::app()->user->uid );
            if ( $res ) {
                $this->ajaxReturn( $return );
            } else {
                $return['IsSuccess'] = false;
                $return['data'] = MessageContent::model()->getError( 'message' );
                $this->ajaxReturn( $return );
            }
        }
    }

    /**
     * 设置当前用户私信列表为已读
     * @return void 
     */
    public function actionSetAllRead() {
        $res = MessageUser::model()->setMessageAllRead( IBOS::app()->user->uid );
        if ( $res ) {
            $this->ajaxReturn( array( 'IsSuccess' => true ) );
        } else {
            $this->ajaxReturn( array( 'IsSuccess' => false ) );
        }
    }

    /**
     * 设置列表私信为已读
     * @return void
     */
    public function actionSetIsRead() {
        $res = MessageUser::model()->setMessageIsRead( IBOS::app()->user->uid, Env::getRequest( 'id' ) );
        $this->ajaxReturn( array( 'IsSuccess' => !!$res ) );
    }

    /**
     * 删除私信
     * @return void
     */
    public function actionDelete() {
        $res = MessageUser::model()->deleteMessageByListId( IBOS::app()->user->uid, String::filterCleanHtml( Env::getRequest( 'id' ) ) );
        $this->ajaxReturn( array( 'IsSuccess' => !!$res ) );
    }

}
