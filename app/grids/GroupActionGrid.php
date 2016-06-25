<?php


class GroupActionGrid extends BaseGrid
{

	/** @return void */
	protected function build()
	{
		$this->setPrimaryKey('id');
		$this->addColumn('firstname', 'Name');
		$this->addColumn('surname', 'Surname');
		$this->addColumn('country_code', 'Country');
		$this->addColumn('birthday', 'Birthdate');

		$this->addGroupAction('export', 'Export', [$this, 'exportMany']);

		$this->addGroupAction('delete', 'Delete', [$this, 'deleteMany'])
			->setConfirmation('Do you really want to delete all chosen items?');

		$this->setDataLoader([$this, 'dataLoader']);
	}


	/**
	 * @param  GroupActionGrid $grid
	 * @param  array $columns
	 * @param  array $filters
	 * @param  array $order
	 * @return Nette\Database\Table\Selection
	 */
	public function dataLoader(GroupActionGrid $grid, array $columns, array $filters, array $order)
	{
		return $this->database->table('user')
			->select(implode(', ', $columns))
			->limit(12);
	}


	/**
	 * @param  Nette\Database\Table\ActiveRow[]
	 * @return void
	 */
	public function exportMany(array $records)
	{
		$ids = array();
		foreach ($records as $record) {
			$ids[] = $record->id;
		}

		$this->flashMessage('[DEMO] Exporting items ' . Nette\Utils\Json::encode($ids), 'success');
	}


	/**
	 * @param  Nette\Database\Table\ActiveRow[]
	 * @return void
	 */
	public function deleteMany(array $records)
	{
		$ids = array();
		foreach ($records as $record) {
			$ids[] = $record->id;
		}

		$this->flashMessage('[DEMO] Deleting items ' . Nette\Utils\Json::encode($ids), 'success');
	}

}
