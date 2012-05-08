<?php

class OrderController extends Controller
{
	/**
	 * @var string the default layout for the views. Defaults to '//layouts/column2', meaning
	 * using two-column layout. See 'protected/views/layouts/column2.php'.
	 */
	public $layout='main';

	/**
	 * @return array action filters
	 */
	public function filters()
	{
		return array(
			'accessControl', // perform access control for CRUD operations
		);
	}

	/**
	 * Specifies the access control rules.
	 * This method is used by the 'accessControl' filter.
	 * @return array access control rules
	 */
	public function accessRules()
	{
		return array(
			array('allow',  // allow all users to perform 'index' and 'view' actions
				'actions'=>array('index','create','suggestTitle'),
				'roles'=>array('create'),
			),
			array('allow',  // allow all users to perform 'index' and 'view' actions
				'actions'=>array('view','create'),
				'users'=>array('@'),
			),
			array('allow',  // allow all users to perform 'index' and 'view' actions
				'actions'=>array('reverseStatus','delete','reverseProcessStatus','checkbox'),
				'roles'=>array('update'),
			),
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}

	public function actionView($id)
	{
		$model=$this->loadModel($id);			
		$this->render('view',array(
			'model'=>$model
		));	
	}

	/**
	 * Deletes a particular model.
	 * If deletion is successful, the browser will be redirected to the 'admin' page.
	 * @param integer $id the ID of the model to be deleted
	 */
	public function actionDelete($id)
	{
		if(Yii::app()->request->isPostRequest)
		{
			// we only allow deletion via POST request
			$this->loadModel($id)->delete();

			// if AJAX request (triggered by deletion via admin grid view), we should not redirect the browser
			if(!isset($_GET['ajax']))
				$this->redirect(isset($_POST['returnUrl']) ? $_POST['returnUrl'] : array('admin'));
		}
		else
			throw new CHttpException(400,'Invalid request. Please do not repeat this request again.');
	}

	/**
	 * Lists all models.
	 */
	public function actionIndex()
	{
		$this->initCheckbox();
		$model=new Order('search');
		$model->unsetAttributes();  // clear any default values
		$model->status=Order::STATUS_PENDING;
		if(isset($_GET['Order']))
			$model->attributes=$_GET['Order'];
			$model->start_time = $_GET['order_start_time'];
			$model->stop_time = $_GET['order_stop_time'];
		$this->render('index',array(
			'model'=>$model
		));
	}
	/**
	 * Reverse processing status of order
	 */
	public function actionReverseStatus($id)
	{
		$src=Order::reverseStatus($id);
			if($src) 
				echo json_encode(array('success'=>true,'src'=>$src));
			else 
				echo json_encode(array('success'=>false));		
	}
	
	/**
	 * Reverse processing status of order
	 */
	public function actionReverseProcessStatus($id)
	{
		$src=Order::reverseProcessStatus($id);
			if($src) 
				echo json_encode(array('success'=>true,'src'=>$src));
			else 
				echo json_encode(array('success'=>false));		
	}
	
	/**
	 * Suggests title of news.
	 */
	public function actionSuggestTitle()
	{
		if(isset($_GET['q']) && ($keyword=trim($_GET['q']))!=='')
		{
			$titles=Order::model()->suggestTitle($keyword);
			if($titles!==array())
				echo implode("\n",$titles);
		}
	}
	/**
	 * Returns the data model based on the primary key given in the GET variable.
	 * If the data model is not found, an HTTP exception will be raised.
	 * @param integer the ID of the model to be loaded
	 */
	public function loadModel($id)
	{
		$model=Order::model()->findByPk($id);
		if($model===null)
			throw new CHttpException(404,'The requested page does not exist.');
		return $model;
	}

	/**
	 * Performs the AJAX validation.
	 * @param CModel the model to be validated
	 */
	protected function performAjaxValidation($model)
	{
		if(Yii::app()->getRequest()->getIsAjaxRequest())
		{
			echo CActiveForm::validate($model);
			Yii::app()->end();
		}
	}
public function actionCheckbox($action)
	{
		$this->initCheckbox();
		$list_checked = Yii::app()->session["checked-order-list"];
		switch ($action) {
			case 'delete' :
				if (Yii::app ()->user->checkAccess ( 'update')) {
					foreach ( $list_checked as $id ) {
						$item = Order::model ()->findByPk ( $id );
						if (isset ( $item ))
							if (! $item->delete ()) {
								echo 'false';
								Yii::app ()->end ();
							}
					}
					Yii::app ()->session ["checked-order-list"] = array ();
				} else {
					echo 'false';
					Yii::app ()->end ();
				}
				break;
		}
		echo 'true';
		Yii::app()->end();
		
	}
	/*
	 * Init checkbox
	 */
	public function initCheckbox(){
		if(!isset(Yii::app()->session['checked-order-list']))
			Yii::app()->session['checked-order-list']=array();		
		if(isset($_POST['list-checked'])){
			$list_new=array_diff ( explode ( ',', $_POST['list-checked'] ), array ('' ));
		 	$list_old=Yii::app()->session['checked-order-list'];
		 	$list=$list_old;
          	foreach ($list_new as $id){
          		if(!in_array($id, $list_old))
               		$list[]=$id;
          	}
          	Yii::app()->session['checked-order-list']=$list;
		 }
		if(isset($_POST['list-unchecked'])){
			$list_unchecked=array_diff ( explode ( ',', $_POST['list-unchecked'] ), array ('' ));
		 	$list_old=Yii::app()->session['checked-order-list'];
		 	$list=array();
          	foreach ($list_old as $id){
          		if(!in_array($id, $list_unchecked)){
               		$list[]=$id;
          		}
          	}
          	Yii::app()->session['checked-order-list']=$list;
		 }
	}
}
