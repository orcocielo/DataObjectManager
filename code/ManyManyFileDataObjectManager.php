<?php

class ManyManyFileDataObjectManager extends HasManyFileDataObjectManager
{

  protected static $only_related;
	private $manyManyParentClass;
	protected $manyManyTable;
	public $RelationType = "ManyMany";
	public $itemClass = 'ManyManyFileDataObjectManager_Item';
	protected $OnlyRelated = false;

	/**
	 * Most of the code below was copied from ManyManyComplexTableField.
	 * Painful, but necessary, until PHP supports multiple inheritance.
	 */
	

		
	function __construct($controller, $name, $sourceClass, $fileFieldName = null, $fieldList = null, $detailFormFields = null, $sourceFilter = "", $sourceSort = "", $sourceJoin = "") {

		parent::__construct($controller, $name, $sourceClass, $fileFieldName, $fieldList, $detailFormFields, $sourceFilter, $sourceSort, $sourceJoin);
		$manyManyTable = false;
		$classes = array_reverse(ClassInfo::ancestry($this->controllerClass()));
		foreach($classes as $class) {
			if($class != "Object") {
				$singleton = singleton($class);
				$manyManyRelations = $singleton->uninherited('many_many', true);
				if(isset($manyManyRelations) && array_key_exists($this->name, $manyManyRelations)) {
					$this->manyManyParentClass = $class;
					$manyManyTable = $class . '_' . $this->name;
					break;
				}
				$belongsManyManyRelations = $singleton->uninherited( 'belongs_many_many', true );
				 if( isset( $belongsManyManyRelations ) && array_key_exists( $this->name, $belongsManyManyRelations ) ) {
					$this->manyManyParentClass = $class;
					
					// @modification http://open.silverstripe.org/ticket/5194
					$manyManyClass = $belongsManyManyRelations[$this->name];
					$manyManyRelations = singleton($manyManyClass)->uninherited('many_many', true);
					foreach($manyManyRelations as $manyManyRelationship => $manyManyChildClass)
						if ($manyManyChildClass == $class)
							break;
					
					$manyManyTable = $manyManyClass . '_' . $manyManyRelationship;
					break;
				}
			}
		}
		if(!$manyManyTable) user_error("I could not find the relation $this->name in " . $this->controllerClass() . " or any of its ancestors.",E_USER_WARNING);		
		$this->manyManyTable = $manyManyTable;
		$tableClasses = ClassInfo::dataClassesFor($this->sourceClass);
		$source = array_shift($tableClasses);
		$sourceField = $this->sourceClass;
		if($this->manyManyParentClass == $sourceField)
			$sourceField = 'Child';
		$parentID = $this->controller->ID;
		
		$this->sourceJoin .= " LEFT JOIN \"$manyManyTable\" ON (\"$source\".\"ID\" = \"{$sourceField}ID\" AND \"$manyManyTable\".\"{$this->manyManyParentClass}ID\" = '$parentID')";
		
		$this->joinField = 'Checked';
		if(isset($_REQUEST['ctf'][$this->Name()]['only_related']))
		  $this->OnlyRelated = $_REQUEST['ctf'][$this->Name()]['only_related'];

		$this->addPermission('only_related');
		
		// If drag-and-drop is enabled, we need to turn on the only related filter
		if($this->ShowAll() && SortableDataObject::is_sortable_many_many($this->sourceClass()))
			  $this->OnlyRelated = '1';
		
	}
	
	public function setParentClass($class)
	{
		parent::setParentClass($class);
		$this->joinField = "Checked";
	}
	
		
	protected function loadSort() {

		if($this->ShowAll()) 
			$this->setPageSize(999);

		$original_sort = $this->sourceSort;
		$frontendSort = empty($_REQUEST['ctf'][$this->Name()]['sort'])
			? null
			: $_REQUEST['ctf'][$this->Name()]['sort'];
    	if (SortableDataObject::is_sortable_many_many($this->sourceClass(), $this->manyManyParentClass)) {
			list($parentClass, $componentClass, $parentField, $componentField, $table) = singleton($this->controllerClass())->many_many($this->Name());
			$sort_column = "$table.SortOrder";
			if (empty($frontendSort) || $frontendSort == $sort_column) {
				$this->sort = $sort_column;
				$this->sourceSort = "\"$table\".\"SortOrder\" " . SortableDataObject::$sort_dir;
			}
		} elseif ($this->Sortable() && (empty($frontendSort) || $frontendSort == "SortOrder")) {
			$this->sort = "SortOrder";
			$this->sourceSort = "\"SortOrder\" " . SortableDataObject::$sort_dir;
		} elseif ($frontendSort) {
			$this->sourceSort = "\"{$frontendSort}\" {$this->sort_dir}";
		} elseif (empty($original_sort)) {
			$this->sourceSort = singleton($this->sourceClass())->stat('default_sort');
		}

		if ($original_sort && $original_sort != $this->sourceSort) $this->sourceSort .= ', '.$original_sort;
	}
	
	
	public function setOnlyRelated($bool) 
	{
	   if(!isset($_REQUEST['ctf'][$this->Name()]['only_related']))
  	   $this->OnlyRelated = $bool;
	}
	
	public function OnlyRelated()
	{
	   return self::$only_related !== null ? self::$only_related : $this->OnlyRelated;
	}
	
	public function getQueryString($params = array())
	{ 
		$only_related = isset($params['only_related'])? $params['only_related'] : $this->OnlyRelated();
		return parent::getQueryString($params)."&ctf[{$this->Name()}][only_related]={$only_related}";
	}
	
	public function OnlyRelatedLink()
	{
	   return $this->RelativeLink(array('only_related' => '1'));
	}
	
	public function AllRecordsLink()
	{
	   return $this->RelativeLink(array('only_related' => '0'));
	}
	
		
	function getQuery($limitClause = null) {
		if($this->customQuery) {
			$query = $this->customQuery;
			$query->select[] = "\"{$this->sourceClass}\".\"ID\" AS \"ID\"";
			$query->select[] = "\"{$this->sourceClass}\".\"ClassName\" AS \"ClassName\"";
			$query->select[] = "\"{$this->sourceClass}\".\"ClassName\" AS \"RecordClassName\"";
		}
		else {
			$query = singleton($this->sourceClass)->extendedSQL($this->sourceFilter, $this->sourceSort, $limitClause, $this->sourceJoin);
			
			// Add more selected fields if they are from joined table.

			$SNG = singleton($this->sourceClass);
			foreach($this->FieldList() as $k => $title) {
				if(! $SNG->hasField($k) && ! $SNG->hasMethod('get' . $k)) {
					$query->select[] = $k;
					// everything we add to select must be added to groupby too...
					$query->groupby[] = $k;
				}
			}
			$parent = $this->controllerClass();
			$mm = $this->manyManyTable;
			$when_clause = "CASE WHEN \"$mm\".\"{$this->manyManyParentClass}ID\" IS NULL THEN '0' ELSE '1' END";
			$query->select[] = "$when_clause AS \"Checked\"";
			// everything we add to select must be added to groupby too...
			$query->groupby[] = $when_clause;
			
			if($this->OnlyRelated())
				$query->where[] = "(\"$mm\".\"{$this->manyManyParentClass}ID\" IS NOT NULL)";
		}
		return clone $query;
	}


	function getParentIdName($parentClass, $childClass) {
		return $this->getParentIdNameRelation($parentClass, $childClass, 'many_many');
	}
			
	function ExtraData() {
		$items = array();
		foreach($this->unpagedSourceItems as $item) {
			if($item->{$this->joinField})
				$items[] = $item->ID;
		}
		$list = implode(',', $items);
		$value = ",";
		$value .= !empty($list) ? $list."," : "";
		$inputId = $this->id() . '_' . $this->htmlListEndName;
		$controllerID = $this->controller->ID;
		return <<<HTML
		<input name="controllerID" type="hidden" value="$controllerID" />
		<input id="$inputId" name="{$this->name}[{$this->htmlListField}]" type="hidden" value="$value"/>
HTML;
	}
	
	protected function getSortableOwner()
	{
	   if($this->sortableOwner) return $this->sortableOwner;
	   
	   // Find the class who owns the relation
	   $parent = null;
	   foreach(array_reverse(ClassInfo::ancestry($this->controllerClass())) as $class) {
	   		if(SortableDataObject::is_sortable_many_many($this->sourceClass(), $class)) {
	   			$this->sortableOwner = $class;
	   			return $this->sortableOwner;
	   		}
	   }
	   return false;
	}
	
	public function Sortable()
	{
	   return (SortableDataObject::is_sortable_many_many($this->sourceClass())) || (SortableDataObject::is_sortable_class($this->sourceClass()));
	}
	
	public function SortableClass()
	{
	   return $this->manyManyParentClass."-".$this->sourceClass();
	}
}

class ManyManyFileDataObjectManager_Item extends FileDataObjectManager_Item {
	
	function MarkingCheckbox() {
		$name = $this->parent->Name() . '[]';
		$disabled = $this->parent->hasMarkingPermission() ? "" : "disabled='disabled'";
		
		if($this->parent->IsReadOnly)
			return "<input class=\"checkbox\" type=\"checkbox\" name=\"$name\" value=\"{$this->item->ID}\" disabled=\"disabled\"/>";
		else if($this->item->{$this->parent->joinField})
			return "<input class=\"checkbox\" type=\"checkbox\" name=\"$name\" value=\"{$this->item->ID}\" checked=\"checked\" $disabled />";
		else
			return "<input class=\"checkbox\" type=\"checkbox\" name=\"$name\" value=\"{$this->item->ID}\" $disabled />";
	}
}




?>