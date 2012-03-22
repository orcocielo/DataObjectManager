<?php

class HasManyFileDataObjectManager extends FileDataObjectManager
{

	public function FieldHolder() {
		$list = Object::create(
			"ListboxField", 
			$this->grid->getName(), 
			sprintf(_t('DOM.Selected','Selected %s'),$this->grid->Title()), 
			DataList::create($this->dataClass)
				->map('ID', 'Title')
				->toArray()
		)->setMultiple(true);
		$this->grid->setList(DataList::create($this->dataClass));		
		return "<div>{$list->FieldHolder()}</div>{$this->grid->FieldHolder()}";
	}

}