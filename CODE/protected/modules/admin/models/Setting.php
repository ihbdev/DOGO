<?php

class Setting extends CActiveRecord
{
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return 'tbl_setting';
	}
	public function rules()
	{
		return array(
			array('name,value','required'),
			array('name','unique')
		);
	}
	public function attributeLabels()
	{
		return array(
			'name' => Language::t('Tên tham số'),
			'value' => Language::t('Giá trị'),
		);
	}
/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search() {
		$criteria = new CDbCriteria ();
		$criteria->compare ( 'name', $this->name );
		if (isset ( $_GET ['pageSize'] ))
			Yii::app ()->user->setState ( 'pageSize', $_GET ['pageSize'] );
		return new CActiveDataProvider ( $this, array ('criteria' => $criteria, 'pagination' => array ('pageSize' => Yii::app ()->user->getState ( 'pageSize', Yii::app ()->params ['defaultPageSize'] ) ), 'sort' => array ('defaultOrder' => 'id DESC' )    		
		));
	}
/**
	 * Suggests a list of existing names matching the specified keyword.
	 * @param string the keyword to be matched
	 * @param integer maximum number of tags to be returned
	 * @return array list of matching username names
	 */
	public function suggestName($keyword,$limit=20)
	{
		$list_news=$this->findAll(array(
			'condition'=>'name LIKE :keyword',
			'order'=>'name DESC',
			'limit'=>$limit,
			'params'=>array(
				':keyword'=>'%'.strtr($keyword,array('%'=>'\%', '_'=>'\_', '\\'=>'\\\\')).'%',
			),
		));
		$names=array();
		foreach($list_news as $news)
			$names[]=$news->name;
			return $names;
	}
	public static function s($name) {
			$criteria = new CDbCriteria ();
			$criteria->compare('name', $name);
			$model = self::model ()->find ( $criteria );
			if(isset($model))
				return $model->value;
			else 	
				return null;
	}
}