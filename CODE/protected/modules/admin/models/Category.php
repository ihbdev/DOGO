<?php
class Category extends CActiveRecord
{	
	/*
	 * Config maximun rank in a group
	 */
	const MAX_RANK=4;	
	/*
	 * Config code error when delete category
	 */
	const DELETE_OK=1;
	const DELETE_HAS_CHILD=2;
	const DELETE_HAS_ITEMS=3;
	/*
	 * Config code (id) of the groups category which have parent_id=0
	 */
	const GROUP_ROOT=0;
	const GROUP_ADMIN_MENU=1;
	const GROUP_USER_MENU=2;
	const GROUP_NEWS=3;
	const GROUP_PRODUCT=4;
	const GROUP_MANUFACTURER=5;
	/*
	 * Config default controller and action when create admin menu
	 */
	const ADMIN_MENU_CONTROLLER_DEFAULT='news';
	const ADMIN_MENU_ACTION_DEFAULT='index';
	/*
	 * Config special
	 * SPECIAL_REMARK when group is news, category news is viewed at homepage
	 */
	const SPECIAL_REMARK=0;	
	private $config_other_attributes=array('introimage','params','action','controller','description','modified','max_rank');	
	private $list_other_attributes;
	
	public $list_special;
	public $group;
	// Template var that store data when tree traversal
	public $tmp_list;
	// Store old order view
	public $old_order_view;
	// Store old parent id
	public $old_parent_id;
	//Store name
	public $old_name;
	/*
	 * Get all specials of class Category
	 * Use in drop select when create, update banner
	 */
	static function getList_label_specials()
 	{
	return array(
			self::SPECIAL_REMARK=>'Hiển thị ở trang chủ',
		);
 	}
 	 /*
 	 * Get specials of a object category
 	 * Use in page lit admin
 	 */
	public function getLabel_specials()
 	{
		$label_specials=array();
		foreach ($this->list_special as $special) {
			$list_label_specials=self::getList_label_specials();
			$label_specials[]= $list_label_specials[$special];
		}
		return $label_specials;
 	}
 	 /*
 	 * Special is encoded before save in database
 	 * Function get all code of the special
 	 */
	static function getCode_special($index=null)
 	{
 		$result=array();
 		$full=range(0,pow(2,sizeof(self::getList_label_specials()))-1);
 		if($index === null){
 			$result=$full;
 		}
 		else {			
 			foreach ($full as $num){
 				if(in_array($index, iPhoenixStatus::decodeStatus($num))){
 					$result[]=$num;
 				}
 			}
 		}
 		return $result;
 	}
	/*
	 * Returns all categories in the group 
	 */
	public function getList_Categories(){
		$result=array();	
		if($this->group==0){
			$max_rank=1;
		}
		else {
			if(Category::model()->findByPk($this->group)!=null){
				$root=Category::model()->findByPk($this->group);
				$max_rank=$root->max_rank;
			}
			else {
				return $result;
			}
		}
		$this->tmp_list=array();
		$this->treeTraversal($this->group, 0, $max_rank);
		$result=$this->tmp_list;
		return $result;
	}
	/*
	 * Returns all child of the category.
	 */
	public function getChild_categories(){
		if(!isset($this->id)){	
			return array();
		}
		else {
			$this->tmp_list=array();
			$this->treeTraversal($this->id, 0, PHP_INT_MAX);
			$result=$this->tmp_list;
			return $result;
		}
	}
	/*
	 * Return ancestor categories of the category 
	 * Use in bread crumb
	 */
	public function getBread_crumb(){
		$bread_crumb=array();
		$check=true;
		$current_id=$this->id;
		while ($check){
			$current=Category::model()->findByPk($current_id);
			$bread_crumb[]=$current_id;
			if($current->parent_id == Category::GROUP_NEWS){
				$check=false;
			}
			else 
				$current_id=$current->parent_id;
		}
		return $bread_crumb;
	}
	/*
	 * Return ancestor of the category which has level 1 in the group.
	 */
	public function getRoot(){
		$check=true;
		$current_id=$this->id;
		while ($check){
			$current=Category::model()->findByPk($current_id);
			if($current->parent_id == Category::GROUP_ADMIN_MENU || $current->parent_id == Category::GROUP_USER_MENU){
				$check=false;
			}
			else 
				$current_id=$current->parent_id;
		}
		return $current_id;
	}
	/*
	 * Return group 
	 */
	public function findGroup(){
		$check=true;
		$current_id=$this->id;
		while ($check){
			$current=Category::model()->findByPk($current_id);
			if($current->parent_id == 0){
				$check=false;
			}
			else 
				$current_id=$current->parent_id;
		}
		return $current_id;
	}
	/*
	 * Returns the rank of category 
	 */
	public function getRank(){
		$result=0;
		foreach ($this->child_categories as $cat){
			if($cat['level'] > $result) $result=$cat['level'];
		}
		return $result;
	}
	/*
	 * Returns order view of brother categories
	 */
	public function getList_order_view(){
		$result=array();	
		$list=Category::model()->findAll('parent_id='.$this->parent_id);
		foreach ($list as $cat){
			$result[$cat->id]=$cat->order_view;
		}
		return $result;
	}
	/*
	 * Returns all categories that can be parent of the category.
	 */
	public function getParent_categories(){
		
		$this->tmp_list=array($this->group=>array('level'=>0,'name'=>'Thư mục gốc'));	
		if($this->group==0){
			return $this->tmp_list;
		}
		else {
			$root=Category::model()->findByPk($this->group);
			$max_rank=$root->max_rank-1;
		}
		if($max_rank > 0){
			$this->treeTraversal($this->group, 0, $max_rank);
		}
		$result=$this->tmp_list;
		
		$black_list=array();
		//Remove the category
		$black_list[]=$this->id;
		//Remove all child of category
		foreach ($this->child_categories as $cat_id=>$cat){
			$black_list[]=$cat_id;
		}
		foreach ($black_list as $cat_id) {
			unset($result[$cat_id]);
		}		
		return $result;
	}
	/*
	 * Recursive algorithms for tree traversals
	 */
	public function treeTraversal($group,$level,$rank){
		$new_level=$level+1;
		$criteria=new CDbCriteria;
		$criteria->compare('parent_id', $group);
		$criteria->order='order_view';
		$list_category=Category::model()->findAll($criteria);
		foreach ($list_category as $category){
			$category->group=$this->group;
			//Get route and params if group is menu
			if($this->group==Category::GROUP_ADMIN_MENU || $this->group==Category::GROUP_USER_MENU){
				$this->tmp_list[$category->id]=array('level'=>$new_level,'name'=>$category->name,'url'=>$category->url,'root'=>$category->root);
			}
			elseif($this->group==Category::GROUP_NEWS){
				$this->tmp_list[$category->id]=array('level'=>$new_level,'name'=>$category->name,'special'=>$category->special,'lang'=>$category->lang);
			}
			else {
				$this->tmp_list[$category->id]=array('level'=>$new_level,'name'=>$category->name);
			}
			if($new_level<$rank){
			$this->treeTraversal($category->id, $new_level, $rank);
			}
		}
	}
	/*
	 * Returns the level of the category in group
	 */
	public function getLevel(){
		foreach ($this->list_categories as $id=>$category) {
			if($this->id==$id) return $category['level'];
		}
	}
	
	/*
	 * PHP setter magic method for other attributes
	 */
	public function __set($name,$value)
	{
		if(in_array($name,$this->config_other_attributes))
			$this->list_other_attributes[$name]=$value;
		else 
			parent::__set($name,$value);
	}
	
	/*
	 * PHP getter magic method for other attributes
	 */
	public function __get($name)
	{
		if(in_array($name,$this->config_other_attributes))
			if(isset($this->list_other_attributes[$name])) 
				return $this->list_other_attributes[$name];
			else 
		 		return null;
		else
			return parent::__get($name);
	}
	
	
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return Category the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'tbl_category';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		return array(
			array('max_rank','required','on'=>'root'),
			array('max_rank','numerical','on'=>'root'),
			array('max_rank','validatorMaxRank','on'=>'root'),
			array('name,parent_id', 'required','message'=>'Dữ liệu bắt buộc'),
			array('parent_id','validatorParent'),
			array('name', 'length', 'max'=>256,'message'=>'Tối đa 32 kí tự'),
			array('description, name', 'length', 'max'=>512,'message'=>'Tối đa 32 kí tự'),
			array('order_view','required','message'=>'Dữ liệu bắt buộc','on'=>'menu,news'),
			array('order_view','numerical','on'=>'menu,news'),
			array('controller,action','required','on'=>'menu','message'=>'Dữ liệu bắt buộc'),
			array('params','safe','on'=>'menu'),
			array('list_special,lang','safe','on'=>'news')
		);
	}
	//Function validator role
	public function validatorMaxRank($attributes,$params){
		if($this->id > 0){
			if($this->rank>$this->max_rank) 
				$this->addError('max_rank', 'Nhóm thư mục này hiện đã vượt quá cấp mà bạn chọn.');
		}			
	}
	//Function validator role
	public function validatorParent($attributes,$params){
		if($this->group>0 && $this->id>0){
			$root=Category::model()->findByPk($this->group);
			$max_rank=$root->max_rank;
			$parent=Category::model()->findByPk($this->parent_id);
			$parent->group=$this->group;
			if(($parent->level+$this->rank)>=$root->max_rank){
				$this->addError('parent_id', 'Vượt quá cấp quy định. Bạn không thể chuyển tới thư mục này.');
			}
		}
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		return array(
			'author'=>array(self::BELONGS_TO,'User','created_by')
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'name' => 'Tên',
			'description' => 'Miêu tả',
			'parent_id'	=> 'Thuộc',
			'max_rank'=>'Mức cấp con',
			'order_view'=>'Thứ tự hiển thị',
			'params'=>'Cấu hình tham số 3 cho URL',
			'controller'=>'Cấu hình tham số 1 cho URL',
			'action'=>'Cấu hình tham số 2 cho URL',
			'list_special' => 'Nhóm hiển thị',
			'lang'=>'Ngôn ngữ'
		);
	}
	
	/**
	 * This event is raised after the record is instantiated by a find method.
	 * @param CEvent $event the event parameter
	 */
	public function afterFind()
	{
		//Store old order view	
		if($this->order_view !=""){
			$this->old_order_view=$this->order_view;
		}
		//Store old parent id
		if($this->parent_id != ""){
			$this->old_parent_id=$this->parent_id;
		}
		$this->old_name=$this->name;
		//Get list special
		if($this->special != ""){
			$this->list_special=iPhoenixStatus::decodeStatus($this->special);	
		}
		//Decode attribute other to set other attributes
		$this->list_other_attributes=(array)json_decode($this->other);	
		if(isset($this->list_other_attributes['modified']))
			$this->list_other_attributes['modified']=(array)json_decode($this->list_other_attributes['modified']);
		else 
			$this->list_other_attributes['modified']=array();
		return parent::afterFind();
	}
	
	/**
	 * This method is invoked before saving a record (after validation, if any).
	 * The default implementation raises the {@link onBeforeSave} event.
	 * You may override this method to do any preparation work for record saving.
	 * Use {@link isNewRecord} to determine whether the saving is
	 * for inserting or updating record.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 * @return boolean whether the saving should be executed. Defaults to true.
	 */
	public function beforeSave()
	{
		if(parent::beforeSave())
		{
			if($this->isNewRecord)
			{
				$this->created_date=time();
				$this->created_by=Yii::app()->user->id;
				//Set order view
				$this->order_view=sizeof($this->list_order_view)+1;
				if($this->parent_id == Category::GROUP_NEWS) 
					$this->list_special=array(Category::SPECIAL_REMARK);
				//Set alias
				/*
				if($this->group==self::GROUP_ADMIN_MENU)
					$this->alias='admin-menu-'.iPhoenixString::createAlias($this->name);
				elseif($this->group==self::GROUP_USER_MENU)
					$this->alias='user-menu-'.iPhoenixString::createAlias($this->name);	
				else
				*/ 
				$alias=iPhoenixString::createAlias($this->name);
				while(sizeof(Category::model()->findAll('alias ="'.$alias.'"'))>0){
					$suffix=rand(0, 100);
					$alias =$alias.'-'.$suffix;
				}
				$this->alias=$alias;
			}	
			else {
				$modified=$this->modified;
				$modified[time()]=Yii::app()->user->id;
				$this->modified = json_encode ( $modified );
				if($this->name != $this->old_name) {
					$alias=iPhoenixString::createAlias($this->name);
					while(sizeof(Category::model()->findAll('alias = "'.$alias.'"'))>0){
						$suffix=rand(0, 100);
						$alias =$alias.'-'.$suffix;
					}
					$this->alias=$alias;
				}
			}
			//Encode special
			if($this->group == self::GROUP_NEWS)
				$this->special=iPhoenixStatus::encodeStatus($this->list_special);
			//Encode other attributes  		
			$this->other = json_encode ( $this->list_other_attributes );
			return true;
		} else
			return false;
	}
	// Handler when change order view of a category
	public function changeOrderView() {
		if(!isset($this->old_parent_id) || $this->old_parent_id == ""){
			$this->old_parent_id=0;
		}
			//Change order view
		if ($this->parent_id == $this->old_parent_id) {
			if ($this->order_view < $this->old_order_view) {
				foreach ( $this->list_order_view as $id => $order ) {
					if ($id != $this->id && $order >= $this->order_view) {
						$category = Category::model ()->findByPk ( $id );
						if ($category->order_view < $this->old_order_view )
							$category->order_view = $order + 1;
						if (! $category->save ())
							return false;
					}
				}
			}
			if ($this->order_view > $this->old_order_view) {
				foreach ( $this->list_order_view as $id => $order ) {
					if ($id != $this->id && $order <= $this->order_view) {
						$category = Category::model ()->findByPk ( $id );
						if ($category->order_view > $this->old_order_view )
							$category->order_view = $order - 1;
						if (! $category->save ())
							return false;
					}
				}
			}
		} else {
			//Fix order view in old parent category
			$list = Category::model ()->findAll ( 'parent_id=' . $this->old_parent_id );
			foreach ( $list as $cat ) {
				if ($cat->order_view > $this->old_order_view) {
					$cat->order_view = $cat->order_view - 1;
					if (!$cat->save ())
						return false;
				}
			}
			//Fix order view in new parent category
			foreach ( $this->list_order_view as $id => $order ) {
				if ($id != $this->id && $order >= $this->order_view) {
					$category = Category::model ()->findByPk ( $id );
					$category->order_view = $order + 1;
					if (! $category->save ())
						return false;
				}
			}
		}
	}
	
	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search()
	{
		// Warning: Please modify the following code to remove attributes that
		// should not be searched.

		$criteria=new CDbCriteria;

		$criteria->compare('id',$this->id);
		$criteria->compare('alias',$this->alias,true);
		$criteria->compare('type',$this->type);
		$criteria->compare('name',$this->name,true);
		$criteria->compare('parent_id',$this->parent_id);
		$criteria->compare('child_id',$this->child_id);
		$criteria->compare('other',$this->other,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}
	/*
	 * Recursive algorithms for tree traversals
	 */
	public function checkDelete($id){
		$list_category=Category::model()->findAll('parent_id = '.$id);
		if(sizeof($list_category)>0){
			return self::DELETE_HAS_CHILD;
		}
		switch($this->group){
			case self::GROUP_NEWS:
				$list_news=News::model()->findAll('catid = '. $id);
				if(sizeof($list_news)>0) return self::DELETE_HAS_ITEMS;
		}
		return self::DELETE_OK;
	}
	/*
	 * Recursive algorithms for tree traversals
	 */
	public function codeUrl($type,$value=array()){
		switch ($type) {
			case 'controller': 
				if($this->group == Category::GROUP_ADMIN_MENU)
					return array('news'=>'Tin tức','manufacturer'=>'Nhà sản xuất','product'=>'Sản phẩm','order'=>'Đơn hàng','user'=>'Người dùng','config'=>'Quản lý hệ thống','qa'=>'Hỏi đáp','album'=>'Album','galleryVideo'=>'Video','banner'=>'Banner quảng cáo','register'=>'Đăng kí học','contact'=>'Liên hệ');
				else 
					return array('news'=>'Tin tức','product'=>'Sản phẩm','qa'=>'Hỏi đáp','album'=>'Album','galleryVideo'=>'Video','register'=>'Đăng kí học','contact'=>'Liên hệ');
				break;
			case 'action':
				switch ($value['controller']) {					
					case 'news':
						if($this->group == Category::GROUP_ADMIN_MENU)							
							return array('present'=>'Các trang giới thiệu','index'=>'Quản lý danh sách tin tức','create'=>'Tạo tin mới','manager_category_vi'=>'Quản lý danh mục tiếng việt','manager_category_en'=>'Quản lý danh mục tiếng anh','manager_present'=>'Quản lý trang giới thiệu','view_category'=>'Hiển thị danh mục tin');
						else 
							return array('present'=>'Các trang giới thiệu','view_category'=>'Hiển thị danh mục tin');
						break;
					case 'product':								
						if($this->group == Category::GROUP_ADMIN_MENU)							
							return array('index'=>'Quản lý danh sách sản phẩm','create'=>'Thêm sản phẩm mới','manager_category'=>'Quản lý danh mục sản phẩm');
						else 
							return array('present'=>'Hiển thị các danh mục','view_category'=>'Hiển thị sản phẩm trong danh mục');
						break;
					case 'order':							
						return array('index'=>'Quản lý đơn hàng');
						break;	
					case 'manufacturer':								
						return array('manager_category'=>'Quản lý danh sách nhà sản xuất');
						break;
					case 'qa':	
						if($this->group == Category::GROUP_ADMIN_MENU)						
							return array('index'=>'Quản lý hỏi đáp','view_qa'=>'Trang danh sách hỏi đáp');
						else 
							return array('view_qa'=>'Trang danh sách hỏi đáp');
						break;
					case 'contact':	
						if($this->group == Category::GROUP_ADMIN_MENU)							
							return array('index'=>'Quản lý liên hệ','view_contact'=>'Trang liên hệ');
						else 
							return array('view_contact'=>'Trang liên hệ');
						break;
					case 'register':
						if($this->group == Category::GROUP_ADMIN_MENU)								
							return array('index'=>'Quản lý đăng kí học','view_register'=>'Trang đăng kí');
						else 	
							return array('view_register'=>'Trang đăng kí');
						break;
					case 'user':								
						return array('index'=>'Quản lý danh sách người dùng','create'=>'Thêm người dùng mới');
						break;
					case 'album':	
						if($this->group == Category::GROUP_ADMIN_MENU)								
							return array('index'=>'Quản lý  danh sách album','create'=>'Thêm album mới','view_album'=>'Trang danh sách album');
						else 	
							return array('view_album'=>'Trang danh sách album');
						break;
					case 'banner':								
						return array('index'=>'Quản lý danh sách banner','create'=>'Thêm banner mới');
						break;
					case 'galleryVideo':
						if($this->group == Category::GROUP_ADMIN_MENU)								
							return array('index'=>'Quản lý danh sách video','create'=>'Thêm video mới','view_video'=>'Trang danh sách video');
						else 
							return array('view_video'=>'Trang danh sách video');
						break;
					case 'config':								
						return array('menu'=>'Quản lý menu','clear_image'=>'Dọn dẹp ảnh rác');
						break;
					default:
						return array('index'=>'Danh sách','create'=>'Thêm');
						break;
				}
				break;			
		}
	}
	/*
	 * Get list params for menu
	 */
	static function getListParams($controller,$action){
		$result=array();
		switch ($controller){
			case 'news':
				switch ($action) {
					case 'view_category': 
						$group=new Category();		
						$group->group=Category::GROUP_NEWS;
						$list_category=$group->list_categories;
						foreach ($list_category as $id=>$info_cat){
							$cat=Category::model()->findByPk($id);
							$index=json_encode(array('cat_alias'=>$cat->alias));
							$view = "";
							for($i=1;$i<$info_cat['level'];$i++){
								$view .="---";
							}
							$label=$view." ".$info_cat['name']." ".$view;
							$result[$index]=$label;
						}
						return $result;
					case 'present': 
						$criteria=new CDbCriteria;
						$criteria->addInCondition('catid',array(News::PRESENT_CATEGORY,News::PRESENT_CATEGORY_EN));
						$criteria->compare('status',News::STATUS_ACTIVE);
						$list_news=News::model()->findAll($criteria);
						foreach ($list_news as $news){
							$index=json_encode(array('cat_alias'=>$news->category->alias,'news_alias'=>$news->alias));
							$result[$index]=$news->title;
						}
						return $result;
					default:
						return $result;
				}
				break;
		case 'product':
				switch ($action) {
					case 'view_category': 
						$group=new Category();		
						$group->group=Category::GROUP_PRODUCT;
						$list_category=$group->list_categories;
						foreach ($list_category as $id=>$info_cat){
							$cat=Category::model()->findByPk($id);
							$index=json_encode(array('cat_alias'=>$cat->alias));
							$view = "";
							for($i=1;$i<$info_cat['level'];$i++){
								$view .="---";
							}
							$label=$view." ".$info_cat['name']." ".$view;
							$result[$index]=$label;
						}
						return $result;
					case 'present': 
						$criteria=new CDbCriteria;
						$criteria->compare('catid',Product::PRESENT_CATEGORY);
						$criteria->compare('status',Product::STATUS_ACTIVE);
						$list_product=Product::model()->findAll($criteria);
						foreach ($list_product as $product){
							$index=json_encode(array('cat_alias'=>$product->category->alias,'product_alias'=>$product->alias));
							$result[$index]=$product->title;
						}
						return $result;
					default:
						return $result;
				}
				break;
			case 'config':
				switch ($action) {
					case 'menu': 
						$result=array();
						//Config admin menu
						$value=json_encode(array('group'=>Category::GROUP_ADMIN_MENU));
						$result[$value]='Quản lý menu trang quản trị';
						//Config user menu
						$value=json_encode(array('group'=>Category::GROUP_USER_MENU));
						$result[$value]='Quản lý menu trang front end';
						return $result;
					default:
						return $result;
				}
				break;
			default:
				return $result;
		}
	}
	/*
	 * Create route for url of menu
	 */
	public function getRoute(){
		$config=array(
			'news'=>array(
				'index'=>'/admin/news/index',
				'create'=>'/admin/news/create',
				'manager_category_en'=>'/admin/category',
				'manager_category_vi'=>'/admin/category',
				'view_category'=>'/site/news',
				'present'=>'/site/news',
				'manager_present'=>'/admin/news/index'
			),
			'product'=>array(
				'index'=>'/admin/product/index',
				'create'=>'/admin/product/create',
				'manager_category'=>'/admin/category',
				'view_category'=>'/site/product',
				'present'=>'/site/product',
				'manager_present'=>'/admin/product/index',
				'upload'=>'admin/product/import'
			),
			'manufacturer'=>array(
				'manager_category'=>'/admin/category',
			),
			'order'=>array(
				'index'=>'/admin/order/index',
			),	
			'qa'=>array(
				'index'=>'/admin/qA/index',
				'create'=>'/admin/qA/create',
				'view_qa'=>'site/qa'
			),
			'contact'=>array(
				'index'=>'/admin/contact/index',
				'view_contact'=>'site/contact'
			),
			'register'=>array(
				'index'=>'/admin/register/index',
				'view_register'=>'site/register'
			),
			'user'=>array(
				'index'=>'/admin/user/index',
				'create'=>'/admin/user/create',
			),
			'album'=>array(
				'index'=>'/admin/album/index',
				'create'=>'/admin/album/create',
				'view_album'=>'site/album'
			),
			'banner'=>array(
				'index'=>'/admin/banner/index',
				'create'=>'/admin/banner/create',
			),
			'galleryVideo'=>array(
				'index'=>'/admin/galleryVideo/index',
				'create'=>'/admin/galleryVideo/create',
				'view_video'=>'site/video'
			), 
			'config' => array ('menu' => '/admin/category', 'clear_image' => '/admin/image/clear') );
		return $config [$this->controller] [$this->action];
	}
	/*
	 * Create params for url of menu
	 */
	public function getUrl() {
		if ($this->group == Category::GROUP_ADMIN_MENU || $this->group == Category::GROUP_USER_MENU) {
			$config = array (
					'news' => array (
						'manager_category_en' => array ('group' => Category::GROUP_NEWS, 'lang'=> 'en' ),
						'manager_category_vi' => array ('group' => Category::GROUP_NEWS, 'lang'=>'vi' ),
						'manager_present' => array ('catid' => News::PRESENT_CATEGORY)
					),
					'product' => array (
						'manager_category' => array ('group' => Category::GROUP_PRODUCT ),
						'manager_present' => array ('catid' => Product::PRESENT_CATEGORY)
					),	
					'manufacturer' => array (
						'manager_category' => array ('group' => Category::GROUP_MANUFACTURER ),
					),		
			);
			if ($this->params != "") {
				$params = ( array ) json_decode ( $this->params );
			} elseif (isset ( $config [$this->controller] [$this->action] ))
				$params = $config [$this->controller] [$this->action];
			if (isset ( $params ))
				$url = Yii::app ()->createUrl ( $this->route, $params );
			else
				$url = Yii::app ()->createUrl ( $this->route );
			return $url;
		}
		elseif($this->findGroup() == Category::GROUP_NEWS){
			
 			$cat_alias=$this->alias;
 			$url=Yii::app()->createUrl("/site/news",array('cat_alias'=>$cat_alias));
			return $url;
		}
		elseif($this->findGroup() == Category::GROUP_PRODUCT){

 			$cat_alias=$this->alias;
 			$url=Yii::app()->createUrl("/site/product",array('cat_alias'=>$cat_alias));
			return $url;
		}
		else 
		{
			return "#";
		}
	}
	/**
	 * Get active menu
	 */
	static function findActiveAdminMenu(){
		$model=new Category();
		$model->group=Category::GROUP_ADMIN_MENU;
		$list=$model->list_Categories;	
		$result=array();
		foreach ($list as $id=>$menu){
			if($menu['url']== Yii::app()->request->requestUri)
			{
				$current=Category::model()->findByPk($id);
				$result[]=(int)$current->root;
			}
		}
		return $result;
	}
	
}